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
#[Description('List bank transactions that have not yet been matched to an order payment. Optionally include transactions that were marked as ignored.')]
class ListUnmatchedTransactionsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_ignored' => $schema->boolean()
                ->description('Set true to also include ignored transactions')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'data' => $schema->array(),
            'summary' => $schema->object([]),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'include_ignored' => ['boolean'],
        ]);

        $query = BankTransaction::whereNull('matched_payment_id')
            ->with('bankAccount');

        if ($validated['include_ignored'] ?? false) {
            $query->whereIn('reconciliation_status', ['unmatched', 'ignored']);
        } else {
            $query->where('reconciliation_status', 'unmatched');
        }

        $transactions = $query->orderByDesc('transaction_date')->get();

        $totalAmount = $transactions->sum(function ($txn) {
            return abs($txn->amount);
        });

        $data = $transactions->map(function ($txn) {
            return [
                'id' => $txn->id,
                'description' => $txn->description,
                'merchant_name' => $txn->merchant_name,
                'amount' => (float) $txn->amount,
                'amount_formatted' => '-£'.number_format(abs($txn->amount), 2),
                'transaction_date' => $txn->transaction_date->toIso8601String(),
                'expense_category' => $txn->expense_category,
                'account_name' => $txn->bankAccount?->name,
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => "Found {$transactions->count()} unmatched transaction".($transactions->count() !== 1 ? 's' : '').'.',
            'data' => $data,
            'summary' => [
                'count' => $transactions->count(),
                'total_amount' => round($totalAmount, 2),
                'total_amount_formatted' => '£'.number_format($totalAmount, 2),
            ],
        ]);
    }
}
