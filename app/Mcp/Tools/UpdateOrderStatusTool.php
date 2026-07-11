<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update the status of an order (pending, deposit_paid, scheduled, in_progress, completed, cancelled). Stamps completed_at automatically. Requires confirmation.')]
class UpdateOrderStatusTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The order ID to update')
                ->required(),
            'status' => $schema->string()
                ->description('New status: pending, deposit_paid, scheduled, in_progress, completed, or cancelled')
                ->enum(['pending', 'deposit_paid', 'scheduled', 'in_progress', 'completed', 'cancelled'])
                ->required(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without saving.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute the action after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view the record in the staff admin area')->nullable(),
            'order' => $schema->object([
                'id' => $schema->integer(),
                'reference_number' => $schema->string(),
                'status' => $schema->string(),
                'completed_at' => $schema->string()->nullable(),
                'updated_at' => $schema->string()->nullable(),
            ])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:orders,id'],
            'status' => ['required', 'in:pending,deposit_paid,scheduled,in_progress,completed,cancelled'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error(
                'This action requires confirmation. Set preview=true to review what will happen, or confirmed=true to proceed.'
            );
        }

        $order = Order::findOrFail($validated['id']);
        $newStatus = $validated['status'];
        $oldStatus = $order->status;

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "I will update order {$order->reference_number} status from '{$oldStatus}' to '{$newStatus}'.\n\nIs that correct?",
                'data' => [
                    'id' => $order->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
            ]);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'completed' && $order->completed_at === null) {
            $updateData['completed_at'] = now();
        }

        $order->update($updateData);
        $order->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => "Order {$order->reference_number} status updated from '{$oldStatus}' to '{$newStatus}'.",
            'url' => route('orders.show', $order),
            'order' => [
                'id' => $order->id,
                'reference_number' => $order->reference_number,
                'status' => $order->status,
                'completed_at' => $order->completed_at?->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ]);
    }
}
