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
            'order' => [
                'id' => $order->id,
                'reference_number' => $order->reference_number,
                'supplier_name' => $order->supplier?->name,
                'order_date' => $order->order_date?->toDateString(),
                'invoice_date' => $order->invoice_date?->toDateString(),
                'invoice_number' => $order->invoice_number,
                'due_date' => $order->due_date?->toDateString(),
                'subtotal' => $order->subtotal,
                'vat_total' => $order->vat_total,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'notes' => $order->notes,
                'created_at' => $order->created_at?->toIso8601String(),
                'supplier' => $order->supplier ? [
                    'id' => $order->supplier->id,
                    'name' => $order->supplier->name,
                ] : null,
                'line_items' => $order->lineItems->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'vat_amount' => $item->vat_amount,
                    'line_total' => $item->line_total,
                    'line_type' => $item->line_type,
                    'allocations' => $item->allocations->map(fn ($allocation) => [
                        'id' => $allocation->id,
                        'amount' => $allocation->amount,
                    ]),
                ]),
                'documents' => $order->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'filename' => $doc->filename,
                    'original_filename' => $doc->original_filename,
                    'type' => $doc->type,
                ]),
                'bank_transaction' => $order->bankTransaction ? [
                    'id' => $order->bankTransaction->id,
                    'description' => $order->bankTransaction->description,
                    'amount' => $order->bankTransaction->amount,
                    'transaction_date' => $order->bankTransaction->transaction_date?->toDateString(),
                ] : null,
                'created_by' => $order->createdBy ? [
                    'id' => $order->createdBy->id,
                    'name' => $order->createdBy->name,
                ] : null,
            ],
        ]);
    }
}
