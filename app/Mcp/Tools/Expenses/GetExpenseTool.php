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
            'expense' => $expense->toArray(),
        ]);
    }
}
