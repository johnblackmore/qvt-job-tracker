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
#[Description('Get a single supplier order with full details including line items, documents, and allocations.')]
class GetSupplierOrderTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The supplier order ID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'url' => $schema->string()->nullable(),
            'order' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:supplier_orders,id'],
        ]);

        $order = SupplierOrder::with([
            'supplier', 'lineItems', 'documents', 'bankTransaction', 'createdBy',
            'lineItems.allocations',
        ])->findOrFail($validated['id']);

        return Response::structured([
            'status' => 'completed',
            'message' => "Supplier order {$order->reference_number}: {$order->supplier?->name} - £{$order->total_amount}",
            'url' => route('expenses.supplier-orders.show', $order),
            'order' => $order->toArray(),
        ]);
    }
}
