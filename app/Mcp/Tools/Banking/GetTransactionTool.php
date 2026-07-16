<?php

namespace App\Mcp\Tools\Banking;

use App\Models\BankTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get a single bank transaction by ID with full details including linked payment and raw provider data.')]
class GetTransactionTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The transaction ID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'transaction' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:bank_transactions,id'],
        ]);

        $transaction = BankTransaction::with(['bankAccount', 'matchedPayment'])->findOrFail($validated['id']);

        $matchedOrderId = $transaction->matchedPayment?->order_id;

        return Response::structured([
            'status' => 'completed',
            'message' => "Transaction: {$transaction->description} ({$transaction->merchant_name})",
            'transaction' => [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'description' => $transaction->description,
                'merchant_name' => $transaction->merchant_name,
                'merchant_category' => $transaction->merchant_category,
                'transaction_date' => $transaction->transaction_date->toIso8601String(),
                'settled_date' => $transaction->settled_date?->toIso8601String(),
                'notes' => $transaction->notes,
                'expense_category' => $transaction->expense_category,
                'reconciliation_status' => $transaction->reconciliation_status,
                'account_name' => $transaction->bankAccount?->name,
                'matched_order_id' => $matchedOrderId,
            ],
        ]);
    }
}
