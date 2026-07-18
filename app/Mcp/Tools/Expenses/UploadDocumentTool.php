<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\SupplierOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Upload a document (invoice PDF, receipt image) to a supplier order or expense and store it permanently.')]
class UploadDocumentTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'reconcilable_type' => $schema->string()->description('Type: supplier_order or expense')->required(),
            'reconcilable_id' => $schema->integer()->description('The ID of the supplier order or expense')->required(),
            'file_path' => $schema->string()->description('Absolute path to the file on the server')->required(),
            'original_filename' => $schema->string()->description('Original filename')->required(),
            'mime_type' => $schema->string()->description('MIME type of the file')->nullable(),
            'document_type' => $schema->string()->description('invoice, receipt, statement, other')->default('invoice'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'url' => $schema->string()->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'reconcilable_type' => ['required', 'in:supplier_order,expense'],
            'reconcilable_id' => ['required', 'integer'],
            'file_path' => ['required', 'string', 'max:500'],
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'document_type' => ['required', 'in:invoice,receipt,statement,other'],
        ]);

        $modelClass = $validated['reconcilable_type'] === 'supplier_order' ? SupplierOrder::class : Expense::class;
        $record = $modelClass::findOrFail($validated['reconcilable_id']);

        if (! file_exists(storage_path('app/private/'.$validated['file_path']))) {
            return Response::error('File not found at the specified path.');
        }

        $doc = ExpenseDocument::create([
            'documentable_type' => $modelClass,
            'documentable_id' => $record->id,
            'file_path' => $validated['file_path'],
            'original_filename' => $validated['original_filename'],
            'mime_type' => $validated['mime_type'] ?? null,
            'file_size' => filesize(storage_path('app/private/'.$validated['file_path'])),
            'document_type' => $validated['document_type'],
        ]);

        $route = $validated['reconcilable_type'] === 'supplier_order'
            ? route('expenses.supplier-orders.show', $record)
            : route('expenses.show', $record);

        return Response::structured([
            'status' => 'completed',
            'message' => "Document \"{$validated['original_filename']}\" uploaded to {$record->reference_number}.",
            'url' => $route,
        ]);
    }
}
