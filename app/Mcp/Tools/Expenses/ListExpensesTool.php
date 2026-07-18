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
#[Description('List business expenses with optional filters for category, status, and date range.')]
class ListExpensesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Search by description, merchant, or reference')->nullable(),
            'category_id' => $schema->integer()->description('Filter by expense category ID')->nullable(),
            'status' => $schema->string()->description('Filter by status: draft, approved, paid, cancelled')->nullable(),
            'date_from' => $schema->string()->description('Filter from this date (YYYY-MM-DD)')->nullable(),
            'date_to' => $schema->string()->description('Filter to this date (YYYY-MM-DD)')->nullable(),
            'per_page' => $schema->integer()->description('Results per page (default 20)')->default(20),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'expenses' => $schema->array($schema->object([])),
            'total' => $schema->integer(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'status' => ['nullable', 'string', 'in:draft,approved,paid,cancelled'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $expenses = Expense::with('category')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('description', 'like', "%{$search}%")
                        ->orWhere('merchant_name', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%");
                });
            })
            ->when($validated['category_id'] ?? null, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['date_from'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($validated['date_to'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->latest('expense_date')
            ->paginate($validated['per_page'] ?? 20);

        return Response::structured([
            'status' => 'completed',
            'message' => "Found {$expenses->total()} expenses.",
            'expenses' => $expenses->items(),
            'total' => $expenses->total(),
        ]);
    }
}
