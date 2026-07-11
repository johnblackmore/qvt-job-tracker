<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List orders with optional filtering by status, customer, and date range. Returns paginated orders with links.')]
class ListOrdersTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Filter by order status (pending, deposit_paid, scheduled, in_progress, completed, cancelled)')
                ->nullable(),
            'customer_id' => $schema->integer()
                ->description('Filter by customer ID')
                ->nullable(),
            'since' => $schema->string()
                ->description('Filter orders created on or after this date (YYYY-MM-DD)')
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
            'status' => ['nullable', 'in:pending,deposit_paid,scheduled,in_progress,completed,cancelled'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'since' => ['nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
        ]);

        $query = Order::query()->with('customer');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['customer_id'])) {
            $query->where('customer_id', $validated['customer_id']);
        }

        if (! empty($validated['since'])) {
            $query->whereDate('created_at', '>=', $validated['since']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $orders = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $orders->map(function (Order $order) {
            return [
                'id' => $order->id,
                'reference_number' => $order->reference_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer?->name,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'balance_due' => $order->balance_due,
                'deposit_percent' => $order->deposit_percent,
                'scheduled_date' => $order->scheduled_date?->toDateString(),
                'url' => route('orders.show', $order),
            ];
        });

        return Response::structured([
            'status' => 'completed',
            'message' => "Retrieved {$orders->count()} orders (page {$orders->currentPage()} of {$orders->lastPage()}).",
            'data' => $data,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
