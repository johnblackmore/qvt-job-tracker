<?php

namespace App\Mcp\Tools\Banking;

use App\Models\BankTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List bank transactions with optional filtering by date range, expense category, reconciliation status, and amount. Returns paginated transactions.')]
class ListTransactionsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'expense_category' => $schema->string()
                ->description('Filter by expense category (stock, equipment, travel, fuel, subsistence, utilities, professional_fees, insurance, other)')
                ->nullable(),
            'reconciliation_status' => $schema->string()
                ->description('Filter by reconciliation status (unmatched, matched, ignored)')
                ->nullable(),
            'since' => $schema->string()
                ->description('Transactions on or after this date (YYYY-MM-DD)')
                ->nullable(),
            'until' => $schema->string()
                ->description('Transactions on or before this date (YYYY-MM-DD)')
                ->nullable(),
            'min_amount' => $schema->number()
                ->description('Minimum transaction amount (use negative for debits, e.g. -100)')
                ->nullable(),
            'max_amount' => $schema->number()
                ->description('Maximum transaction amount')
                ->nullable(),
            'search' => $schema->string()
                ->description('Search in description and merchant name')
                ->nullable(),
            'per_page' => $schema->integer()
                ->description('Items per page (max 100)')
                ->default(20),
            'page' => $schema->integer()
                ->description('Page number')
                ->default(1),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'data' => $schema->array(),
            'pagination' => $schema->object([]),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'expense_category' => ['nullable', 'string', Rule::in(BankTransaction::allCategories())],
            'reconciliation_status' => ['nullable', 'string', 'in:unmatched,matched,ignored'],
            'since' => ['nullable', 'date', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date', 'date_format:Y-m-d'],
            'min_amount' => ['nullable', 'numeric'],
            'max_amount' => ['nullable', 'numeric'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
        ]);

        $query = BankTransaction::query()->with('bankAccount');

        if (! empty($validated['expense_category'])) {
            $query->where('expense_category', $validated['expense_category']);
        }

        if (! empty($validated['reconciliation_status'])) {
            $query->where('reconciliation_status', $validated['reconciliation_status']);
        }

        if (! empty($validated['since'])) {
            $query->whereDate('transaction_date', '>=', $validated['since']);
        }

        if (! empty($validated['until'])) {
            $query->whereDate('transaction_date', '<=', $validated['until']);
        }

        if (isset($validated['min_amount'])) {
            $query->where('amount', '>=', $validated['min_amount']);
        }

        if (isset($validated['max_amount'])) {
            $query->where('amount', '<=', $validated['max_amount']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('merchant_name', 'like', "%{$search}%");
            });
        }

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $transactions = $query->orderByDesc('transaction_date')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $transactions->map(function (BankTransaction $txn) {
            return [
                'id' => $txn->id,
                'amount' => (float) $txn->amount,
                'currency' => $txn->currency,
                'description' => $txn->description,
                'merchant_name' => $txn->merchant_name,
                'merchant_category' => $txn->merchant_category,
                'transaction_date' => $txn->transaction_date->toIso8601String(),
                'expense_category' => $txn->expense_category,
                'reconciliation_status' => $txn->reconciliation_status,
                'account_name' => $txn->bankAccount?->name,
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => "Retrieved {$transactions->count()} transactions (page {$transactions->currentPage()} of {$transactions->lastPage()}).",
            'data' => $data,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
