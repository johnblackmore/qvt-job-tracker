<?php

namespace App\Services\Ai\Assistants;

use App\Models\AiExtraction;
use App\Models\AiModelConfig;
use App\Models\User;
use App\Settings\AiAssistantConfigSettings;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Enums\StructuredMode;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use Smalot\PdfParser\Parser as PdfParser;

class ExpensesExtractorAssistant
{
    protected function resolveConfig(string $filePath): array
    {
        $settings = app(AiAssistantConfigSettings::class);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png']);

        $configRecord = null;

        if ($isImage && $settings->expenses_extractor_vision_config_id) {
            $configRecord = AiModelConfig::find($settings->expenses_extractor_vision_config_id);
        } elseif ($settings->expenses_extractor_config_id) {
            $configRecord = AiModelConfig::find($settings->expenses_extractor_config_id);
        }

        $fallback = config('ai.assistants.expenses-extractor');

        return [
            'provider' => $configRecord?->resolvedProvider() ?? $fallback['provider'],
            'model' => $configRecord?->model ?? $fallback['model'],
            'fallback' => $fallback,
            'isImage' => $isImage,
            'extension' => $extension,
        ];
    }

    public function extract(string $filePath, User $user): array
    {
        $config = $this->resolveConfig($filePath);

        $extraction = AiExtraction::create([
            'user_id' => $user->id,
            'assistant_name' => 'expenses-extractor',
            'provider' => $config['provider'],
            'model' => $config['model'],
            'source_url' => $filePath,
            'prompt_data' => null,
            'status' => 'processing',
        ]);

        $this->processExtractionRecord($extraction, $config);

        if ($extraction->status === 'failed') {
            throw new \RuntimeException($extraction->error_message ?? 'Extraction failed.');
        }

        return $extraction->extracted_data ?? [];
    }

    public function processExtraction(AiExtraction $extraction): void
    {
        $config = $this->resolveConfig($extraction->source_url);

        $extraction->update([
            'provider' => $config['provider'],
            'model' => $config['model'],
        ]);

        $this->processExtractionRecord($extraction, $config);
    }

    protected function processExtractionRecord(AiExtraction $extraction, array $config): void
    {
        $filePath = $extraction->source_url;

        try {
            $fullPath = Storage::disk('local')->path($filePath);

            if (! file_exists($fullPath)) {
                throw new \RuntimeException('The uploaded file could not be found on the server.');
            }

            $provider = $config['provider'];
            $model = $config['model'];
            $fallback = $config['fallback'];

            if ($config['isImage']) {
                $this->extractFromImage($fullPath, $config['extension'], $provider, $model, $fallback, $extraction);
            } elseif ($config['extension'] === 'pdf') {
                $this->extractFromPdf($fullPath, $provider, $model, $fallback, $extraction);
            } else {
                $this->extractFromTextFile($fullPath, $provider, $model, $fallback, $extraction);
            }
        } catch (\Throwable $e) {
            $extraction->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function extractFromImage(
        string $fullPath,
        string $extension,
        string $provider,
        string $model,
        array $fallback,
        AiExtraction $extraction,
    ): array {
        $mimeType = "image/{$extension}";
        $base64 = base64_encode(file_get_contents($fullPath));
        $promptText = 'Extract expense/invoice data from this image of a receipt or invoice.';

        $extraction->update(['prompt_data' => $promptText]);

        $response = Prism::structured()
            ->using($provider, $model)
            ->withSystemPrompt(view('ai.prompts.expenses-extraction')->render())
            ->withPrompt($promptText, [
                Image::fromBase64($base64, $mimeType),
            ])
            ->withSchema($this->buildSchema())
            ->usingStructuredMode(StructuredMode::Auto)
            ->usingTemperature($fallback['temperature'])
            ->withMaxTokens($fallback['max_tokens'])
            ->withClientOptions(['timeout' => 300, 'connect_timeout' => 30])
            ->asStructured();

        return $this->finalizeExtraction($response, $extraction);
    }

    private function extractFromPdf(
        string $fullPath,
        string $provider,
        string $model,
        array $fallback,
        AiExtraction $extraction,
    ): array {
        $text = $this->extractPdfText($fullPath);

        if (mb_strlen($text) > 8000) {
            $text = mb_substr($text, 0, 8000);
        }

        $extraction->update(['prompt_data' => $text]);

        if (mb_strlen(trim($text)) < 20) {
            throw new \RuntimeException(
                'Could not extract readable text from this PDF. The document may be scanned or image-based. Try uploading as an image (JPG/PNG) instead.'
            );
        }

        $response = Prism::structured()
            ->using($provider, $model)
            ->withSystemPrompt(view('ai.prompts.expenses-extraction')->render())
            ->withPrompt($this->buildExtractionPrompt($text))
            ->withSchema($this->buildSchema())
            ->usingStructuredMode(StructuredMode::Auto)
            ->usingTemperature($fallback['temperature'])
            ->withMaxTokens($fallback['max_tokens'])
            ->withClientOptions(['timeout' => 300, 'connect_timeout' => 30])
            ->asStructured();

        return $this->finalizeExtraction($response, $extraction);
    }

    private function extractFromTextFile(
        string $fullPath,
        string $provider,
        string $model,
        array $fallback,
        AiExtraction $extraction,
    ): array {
        $content = file_get_contents($fullPath);

        if ($content === false || empty($content)) {
            throw new \RuntimeException('The uploaded file is empty or could not be read.');
        }

        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) > 8000) {
            $text = mb_substr($text, 0, 8000);
        }

        $extraction->update(['prompt_data' => $text]);

        $response = Prism::structured()
            ->using($provider, $model)
            ->withSystemPrompt(view('ai.prompts.expenses-extraction')->render())
            ->withPrompt($this->buildExtractionPrompt($text))
            ->withSchema($this->buildSchema())
            ->usingStructuredMode(StructuredMode::Auto)
            ->usingTemperature($fallback['temperature'])
            ->withMaxTokens($fallback['max_tokens'])
            ->withClientOptions(['timeout' => 300, 'connect_timeout' => 30])
            ->asStructured();

        return $this->finalizeExtraction($response, $extraction);
    }

    private function extractPdfText(string $fullPath): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($fullPath);
            $text = $pdf->getText();

            $text = preg_replace('/\s+/', ' ', $text);
            $text = preg_replace('/[^\S\n]{2,}/', ' ', $text);

            return trim($text);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Could not parse the PDF file: '.$e->getMessage()
            );
        }
    }

    private function buildSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'expense',
            description: 'Extracted expense/invoice data from uploaded document',
            properties: [
                new StringSchema('supplier_name', 'Supplier/merchant name from the invoice'),
                new StringSchema('invoice_number', 'Invoice or receipt reference number', true),
                new StringSchema('invoice_date', 'Invoice date in YYYY-MM-DD format', true),
                new StringSchema('due_date', 'Payment due date in YYYY-MM-DD format', true),
                new StringSchema('currency', 'Currency code (e.g. GBP)', true),
                new StringSchema('payment_terms', 'Payment terms if visible', true),
                new NumberSchema('subtotal', 'Subtotal amount before VAT', true),
                new NumberSchema('vat_total', 'Total VAT amount', true),
                new NumberSchema('total_amount', 'Total amount including VAT', true),
                new ArraySchema(
                    name: 'line_items',
                    description: 'Line items on the invoice',
                    items: new ObjectSchema(
                        name: 'line_item',
                        description: 'A single line item',
                        properties: [
                            new StringSchema('description', 'Item description'),
                            new NumberSchema('quantity', 'Item quantity', true),
                            new NumberSchema('unit_amount', 'Unit price', true),
                            new NumberSchema('vat_rate', 'VAT rate as decimal (e.g. 0.20)', true),
                            new NumberSchema('line_total', 'Line total including VAT', true),
                            new StringSchema('line_type', 'Either "business" or "personal"', true),
                        ],
                    ),
                    nullable: true,
                ),
            ],
        );
    }

    private function finalizeExtraction($response, AiExtraction $extraction): array
    {
        $data = $response->structured ?? [];

        $extraction->update([
            'status' => 'completed',
            'raw_response' => $response->text,
            'extracted_data' => $data,
            'input_tokens' => $response->usage?->promptTokens,
            'output_tokens' => $response->usage?->completionTokens,
        ]);

        return $data;
    }

    private function buildExtractionPrompt(string $text): string
    {
        return "Extract expense/invoice data from this document content:\n\n{$text}";
    }
}
