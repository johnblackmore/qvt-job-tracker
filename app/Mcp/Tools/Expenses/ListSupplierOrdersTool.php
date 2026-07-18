<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\SupplierOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List supplier orders with optional filters for status, date range, and search.')]
class ListSupplierOrdersTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Search by reference, invoice number, or supplier name')->nullable(),
            'status' => $schema->string()->description('Filter by status: draft, ordered, received, partially_received, paid, cancelled')->nullable(),
            'date_from' => $schema->string()->description('Filter orders from this date (YYYY-MM-DD)')->nullable(),
            'date_to' => $schema->string()->description('Filter orders to this date (YYYY-MM-DD)')->nullable(),
            'per_page' => $schema->integer()->description('Results per page (default 20)')->default(20),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'orders' => $schema->array($schema->object([])),
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
            'status' => ['nullable', 'string', 'in:draft,ordered,received,partially_received,paid,cancelled'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = SupplierOrder::with('supplier')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($ssq) => $ssq->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['date_from'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '>=', $d))
            ->when($validated['date_to'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '<=', $d))
            ->latest('order_date')
            ->paginate($validated['per_page'] ?? 20);

        return Response::structured([
            'status' => 'completed',
            'message' => "Found {$orders->total()} supplier orders.",
            'orders' => $orders->items(),
            'total' => $orders->total(),
        ]);
    }
}
