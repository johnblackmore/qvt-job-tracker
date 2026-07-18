<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\Allocation;
use App\Models\Order;
use App\Models\SupplierOrderLineItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Allocate a supplier order line item to a customer order. Supports partial allocation. Requires confirmation.')]
class AllocateLineItemTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'line_item_id' => $schema->integer()->description('The supplier order line item ID')->required(),
            'order_id' => $schema->integer()->description('The customer order ID to allocate to')->required(),
            'amount' => $schema->number()->description('Amount to allocate (can be less than line total for partial)')->required(),
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
            'line_item_id' => ['required', 'integer', 'exists:supplier_order_line_items,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error('Set preview=true to review or confirmed=true to proceed.');
        }

        $lineItem = SupplierOrderLineItem::with('supplierOrder.supplier')->findOrFail($validated['line_item_id']);
        $order = Order::with('customer')->findOrFail($validated['order_id']);

        $alreadyAllocated = (float) $lineItem->allocations()->sum('amount');
        $remaining = (float) $lineItem->line_total - $alreadyAllocated;

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => sprintf(
                    "I will allocate £%s from line item \"%s\" to order %s (%s).\n\nLine total: £%s\nAlready allocated: £%s\nRemaining: £%s\nNew allocation: £%s\n\nIs that correct?",
                    number_format($validated['amount'], 2),
                    $lineItem->description,
                    $order->reference_number,
                    $order->customer?->name ?? 'Unknown',
                    number_format($lineItem->line_total, 2),
                    number_format($alreadyAllocated, 2),
                    number_format($remaining, 2),
                    number_format($validated['amount'], 2)
                ),
                'data' => [
                    'line_item_id' => $lineItem->id,
                    'description' => $lineItem->description,
                    'order_reference' => $order->reference_number,
                    'customer_name' => $order->customer?->name,
                    'amount' => $validated['amount'],
                    'line_total' => $lineItem->line_total,
                    'remaining_after' => $remaining - $validated['amount'],
                ],
            ]);
        }

        Allocation::create([
            'allocatable_from_type' => SupplierOrderLineItem::class,
            'allocatable_from_id' => $lineItem->id,
            'allocatable_to_type' => Order::class,
            'allocatable_to_id' => $order->id,
            'amount' => $validated['amount'],
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Allocated £'.number_format($validated['amount'], 2)." from \"{$lineItem->description}\" to order {$order->reference_number}.",
            'url' => route('expenses.supplier-orders.show', $lineItem->supplierOrder),
        ]);
    }
}
