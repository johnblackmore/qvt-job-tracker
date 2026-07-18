<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\Expense;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get a single expense with full details including line items, documents, and bank transaction.')]
class GetExpenseTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The expense ID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'url' => $schema->string()->nullable(),
            'expense' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:expenses,id'],
        ]);

        $expense = Expense::with([
            'category', 'lineItems', 'documents', 'bankTransaction', 'createdBy',
        ])->findOrFail($validated['id']);

        return Response::structured([
            'status' => 'completed',
            'message' => "Expense {$expense->reference_number}: ".($expense->merchant_name ?? $expense->description)." - £{$expense->total_amount}",
            'url' => route('expenses.show', $expense),
            'expense' => [
                'id' => $expense->id,
                'reference_number' => $expense->reference_number,
                'description' => $expense->description,
                'merchant_name' => $expense->merchant_name,
                'total_amount' => $expense->total_amount,
                'vat_total' => $expense->vat_total,
                'expense_date' => $expense->expense_date?->toDateString(),
                'payment_method' => $expense->payment_method,
                'payment_reference' => $expense->payment_reference,
                'paid_at' => $expense->paid_at?->toIso8601String(),
                'status' => $expense->status,
                'notes' => $expense->notes,
                'created_at' => $expense->created_at?->toIso8601String(),
                'category' => $expense->category ? [
                    'id' => $expense->category->id,
                    'name' => $expense->category->name,
                ] : null,
                'line_items' => $expense->lineItems->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'vat_amount' => $item->vat_amount,
                    'line_total' => $item->line_total,
                    'line_type' => $item->line_type,
                ]),
                'documents' => $expense->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'filename' => $doc->filename,
                    'original_filename' => $doc->original_filename,
                    'type' => $doc->type,
                ]),
                'bank_transaction' => $expense->bankTransaction ? [
                    'id' => $expense->bankTransaction->id,
                    'description' => $expense->bankTransaction->description,
                    'amount' => $expense->bankTransaction->amount,
                    'transaction_date' => $expense->bankTransaction->transaction_date?->toDateString(),
                ] : null,
                'created_by' => $expense->createdBy ? [
                    'id' => $expense->createdBy->id,
                    'name' => $expense->createdBy->name,
                ] : null,
            ],
        ]);
    }
}
