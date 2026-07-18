<?php

namespace App\Mcp\Tools\Expenses;

use App\Models\SupplierOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update the status of a supplier order (e.g., ordered, received, paid). Requires confirmation.')]
class UpdateSupplierOrderStatusTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier_order_id' => $schema->integer()->description('The supplier order ID')->required(),
            'status' => $schema->string()->description('New status: draft, ordered, received, partially_received, paid, cancelled')->required(),
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
            'supplier_order_id' => ['required', 'integer', 'exists:supplier_orders,id'],
            'status' => ['required', 'in:draft,ordered,received,partially_received,paid,cancelled'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error('Set preview=true to review or confirmed=true to proceed.');
        }

        $order = SupplierOrder::findOrFail($validated['supplier_order_id']);

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will update supplier order {$order->reference_number} from \"{$order->status}\" to \"{$validated['status']}\".\n\nIs that correct?",
                'data' => [
                    'reference_number' => $order->reference_number,
                    'current_status' => $order->status,
                    'new_status' => $validated['status'],
                ],
            ]);
        }

        $order->update(['status' => $validated['status']]);

        return Response::structured([
            'status' => 'completed',
            'message' => "Supplier order {$order->reference_number} updated to \"{$validated['status']}\".",
            'url' => route('expenses.supplier-orders.show', $order),
        ]);
    }
}
