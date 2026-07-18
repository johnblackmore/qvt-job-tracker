<?php

namespace App\Mcp\Tools\Expenses;

use App\Jobs\ProcessExpenseExtraction;
use App\Models\AiExtraction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('AI-powered extraction of invoice/receipt data from an uploaded file. Upload a PDF or image path, and the AI will extract supplier, date, line items, and totals for review before creating a record.')]
class AiExtractExpenseTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()->description('Absolute path to the invoice PDF or image on the server')->required(),
            'original_filename' => $schema->string()->description('Original filename for reference')->nullable(),
            'preview' => $schema->boolean()->description('Preview extracted data without saving')->default(true),
            'confirmed' => $schema->boolean()->description('Confirm to save the extraction result')->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error']),
            'message' => $schema->string(),
            'data' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'file_path' => ['required', 'string', 'max:1000'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error('Set preview=true to review or confirmed=true to proceed.');
        }

        if (! file_exists($validated['file_path'])) {
            return Response::error("File not found at {$validated['file_path']}. Please upload the file first.");
        }

        // Store the file in the local expenses directory
        $filename = $validated['original_filename'] ?? basename($validated['file_path']);
        $storagePath = 'expenses/ai-extractions/'.basename($validated['file_path']);
        copy($validated['file_path'], storage_path('app/private/'.$storagePath));

        if ($isPreview && ! $isConfirmed) {
            // Return preview with extracted data (simulated for now — actual AI extraction would happen here)
            return Response::structured([
                'status' => 'preview',
                'message' => "Invoice file \"{$filename}\" received.\n\nI will process this through the AI extraction pipeline to extract supplier, line items, and totals.\n\nConfirmed data will be stored as an AiExtraction record and the file will be available for form pre-fill.\n\nIs that correct?",
                'data' => [
                    'file_path' => $storagePath,
                    'original_filename' => $filename,
                    'status' => 'pending_review',
                ],
            ]);
        }

        $extraction = AiExtraction::create([
            'user_id' => $request->user()?->id,
            'assistant_name' => 'expenses-extractor',
            'provider' => 'pending',
            'model' => 'pending',
            'source_url' => $storagePath,
            'status' => 'processing',
            'extracted_data' => null,
            'raw_response' => null,
        ]);

        ProcessExpenseExtraction::dispatch($extraction->id);

        return Response::structured([
            'status' => 'completed',
            'message' => "File \"{$filename}\" uploaded and queued for AI extraction (ID: {$extraction->id}). Check the Expenses Assistant dashboard for results.",
            'data' => [
                'extraction_id' => $extraction->id,
                'file_path' => $storagePath,
                'status' => 'processing',
            ],
        ]);
    }
}
