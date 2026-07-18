<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\Expense;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Create a new business expense. Requires confirmation after preview.')]
class CreateExpenseTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->description('What the expense was for')->required(),
            'merchant_name' => $schema->string()->description('The merchant or supplier name')->nullable(),
            'total_amount' => $schema->number()->description('Total amount in GBP')->required(),
            'vat_total' => $schema->number()->description('VAT amount')->default(0),
            'expense_date' => $schema->string()->description('Date of expense (YYYY-MM-DD). Defaults to today.')->nullable(),
            'expense_category_id' => $schema->integer()->description('Category ID')->nullable(),
            'payment_method' => $schema->string()->description('bank_transfer, credit_card, debit_card, cash, other')->nullable(),
            'status' => $schema->string()->description('draft, approved, paid')->default('draft'),
            'notes' => $schema->string()->description('Additional notes')->nullable(),
            'preview' => $schema->boolean()->description('Preview without saving')->default(true),
            'confirmed' => $schema->boolean()->description('Confirm to execute')->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error']),
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
            'description' => ['required', 'string', 'max:5000'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'vat_total' => ['nullable', 'numeric', 'min:0'],
            'expense_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'payment_method' => ['nullable', 'string', 'in:bank_transfer,credit_card,debit_card,cash,other'],
            'status' => ['required', 'in:draft,approved,paid'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error('Set preview=true to review or confirmed=true to proceed.');
        }

        $expenseDate = $validated['expense_date'] ?? now()->format('Y-m-d');
        $reference = 'EXP-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => sprintf(
                    "I will create an expense of £%s for \"%s\" on %s.\n\nReference: %s\nMerchant: %s\n\nIs that correct?",
                    number_format($validated['total_amount'], 2),
                    $validated['description'],
                    $expenseDate,
                    $reference,
                    $validated['merchant_name'] ?? 'N/A'
                ),
                'data' => [
                    'reference_number' => $reference,
                    'description' => $validated['description'],
                    'total_amount' => $validated['total_amount'],
                    'expense_date' => $expenseDate,
                ],
            ]);
        }

        $expense = Expense::create([
            'reference_number' => $reference,
            'description' => $validated['description'],
            'merchant_name' => $validated['merchant_name'] ?? null,
            'total_amount' => $validated['total_amount'],
            'vat_total' => $validated['vat_total'] ?? 0,
            'expense_date' => $expenseDate,
            'expense_category_id' => $validated['expense_category_id'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => "Expense {$reference} created: £".number_format($validated['total_amount'], 2)." for {$validated['description']}.",
            'url' => route('expenses.show', $expense),
            'expense' => $expense->toArray(),
        ]);
    }
}
