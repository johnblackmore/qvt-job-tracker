<?php

namespace App\Mcp\Tools\Expenses;

use App\Banking\Services\ReconciliationService;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\SupplierOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Reconcile a supplier order or expense by linking it to a bank transaction. Supports partial matching (e.g. credit on account). Requires confirmation.')]
class ReconcileExpenseTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'bank_transaction_id' => $schema->integer()->description('The bank transaction ID')->required(),
            'reconcilable_type' => $schema->string()->description('Type: supplier_order or expense')->required(),
            'reconcilable_id' => $schema->integer()->description('The ID of the supplier order or expense')->required(),
            'amount' => $schema->number()->description('Matched amount (can differ from total for partial matches)')->required(),
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
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'bank_transaction_id' => ['required', 'integer', 'exists:bank_transactions,id'],
            'reconcilable_type' => ['required', 'in:supplier_order,expense'],
            'reconcilable_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error('Set preview=true to review or confirmed=true to proceed.');
        }

        $txn = BankTransaction::findOrFail($validated['bank_transaction_id']);

        $modelClass = $validated['reconcilable_type'] === 'supplier_order' ? SupplierOrder::class : Expense::class;
        $expensable = $modelClass::findOrFail($validated['reconcilable_id']);

        if ($isPreview && ! $isConfirmed) {
            $label = $validated['reconcilable_type'] === 'supplier_order'
                ? "Supplier Order {$expensable->reference_number}"
                : "Expense {$expensable->reference_number}";

            return Response::structured([
                'status' => 'preview',
                'message' => sprintf(
                    "I will link the following bank transaction to %s:\n\nBank: \"%s\" (%s)\nAmount: -£%s\nDate: %s\n\n%s: £%s\nMatch amount: £%s\nDifference: £%s\n\nIs that correct?",
                    $label,
                    $txn->description,
                    $txn->merchant_name ?? 'Unknown',
                    number_format(abs($txn->amount), 2),
                    $txn->transaction_date->format('j M Y'),
                    $label,
                    number_format($expensable->total_amount, 2),
                    number_format($validated['amount'], 2),
                    number_format(abs($txn->amount) - $validated['amount'], 2)
                ),
                'data' => [
                    'bank_transaction_id' => $txn->id,
                    'bank_description' => $txn->description,
                    'bank_amount' => (float) $txn->amount,
                    'reconcilable_type' => $validated['reconcilable_type'],
                    'reconcilable_reference' => $expensable->reference_number,
                    'match_amount' => $validated['amount'],
                ],
            ]);
        }

        $service = app(ReconciliationService::class);
        $service->matchExpense($txn, $expensable, $validated['amount'], $request->user()?->id);

        $route = $validated['reconcilable_type'] === 'supplier_order'
            ? route('expenses.supplier-orders.show', $expensable)
            : route('expenses.show', $expensable);

        return Response::structured([
            'status' => 'completed',
            'message' => "Linked bank transaction \"{$txn->description}\" to {$expensable->reference_number} for £".number_format($validated['amount'], 2).'.',
            'url' => $route,
        ]);
    }
}
