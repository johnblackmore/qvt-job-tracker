<?php

namespace App\Services\Ai\Assistants;

use App\Models\AiExtraction;
use App\Models\AiModelConfig;
use App\Models\User;
use App\Settings\AiAssistantConfigSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\StructuredMode;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class ProductUrlAssistant
{
    public function extract(string $url, User $user): array
    {
        $extraction = AiExtraction::create([
            'user_id' => $user->id,
            'assistant_name' => 'product-url-extractor',
            'source_url' => $url,
            'prompt_data' => null,
            'status' => 'processing',
        ]);

        try {
            $text = $this->fetchAndExtractText($url);

            $extraction->update(['prompt_data' => $text]);

            $fallback = config('ai.assistants.product-url-extractor');
            $settings = app(AiAssistantConfigSettings::class);
            $configRecord = $settings->product_url_extractor_config_id
                ? AiModelConfig::find($settings->product_url_extractor_config_id)
                : null;

            $provider = $configRecord?->provider ?? $fallback['provider'];
            $model = $configRecord?->model ?? $fallback['model'];

            $response = Prism::structured()
                ->using($provider, $model)
                ->withSystemPrompt(view('ai.prompts.product-extraction')->render())
                ->withPrompt($this->buildExtractionPrompt($text))
                ->withSchema(new ObjectSchema(
                    name: 'product',
                    description: 'Extracted product information from supplier webpage',
                    properties: [
                        new StringSchema('name', 'Product name/title'),
                        new StringSchema('sku', 'Product SKU or model number', true),
                        new StringSchema('description', 'Brief product description (max 500 characters)', true),
                        new NumberSchema('retail_price', 'Customer-facing retail price in GBP (£)', true),
                        new StringSchema('category_name', 'Best-guess product category', true),
                        new StringSchema('supplier_name', 'Supplier company name', true),
                        new StringSchema('supplier_sku', "Supplier's own SKU if different from product SKU", true),
                    ],
                ))
                ->usingStructuredMode(StructuredMode::Auto)
                ->usingTemperature($fallback['temperature'])
                ->withMaxTokens($fallback['max_tokens'])
                ->asStructured();

            $data = $response->structured ?? [];

            $extraction->update([
                'status' => 'completed',
                'raw_response' => $response->text,
                'extracted_data' => $data,
                'input_tokens' => $response->usage?->promptTokens,
                'output_tokens' => $response->usage?->completionTokens,
            ]);

            return $data;
        } catch (\Throwable $e) {
            $extraction->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($e instanceof ConnectionException) {
                throw new \RuntimeException(
                    'Could not connect to the URL. The connection timed out. Please check the URL and try again.'
                );
            }

            throw $e;
        }
    }

    public function fetchAndExtractText(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-GB,en;q=0.9',
        ])
            ->timeout(15)
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Could not access the URL (HTTP {$response->status()}). Check it's correct and publicly accessible."
            );
        }

        $html = $response->body();

        if (empty($html)) {
            throw new \RuntimeException('The URL returned an empty response.');
        }

        $text = $this->htmlToText($html);

        if (mb_strlen($text) > 8000) {
            $text = mb_substr($text, 0, 8000);
        }

        if (mb_strlen(trim($text)) < 50) {
            throw new \RuntimeException(
                'Could not extract enough readable content from that URL. The page may require JavaScript to display product information.'
            );
        }

        return $text;
    }

    private function htmlToText(string $html): string
    {
        $dom = new \DOMDocument;

        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        foreach (['script', 'style', 'nav', 'footer', 'header', 'aside', 'noscript'] as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $elements->item($i)?->parentNode?->removeChild($elements->item($i));
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $text = $body ? $body->textContent : $dom->textContent;

        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        $text = preg_replace('/[^\S\n]{2,}/', ' ', $text);

        return trim($text);
    }

    private function buildExtractionPrompt(string $text): string
    {
        return "Extract product information from this webpage content:\n\n{$text}";
    }
}
