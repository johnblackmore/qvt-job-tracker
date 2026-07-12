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
#[Description('Schedule or reschedule an installation date for an order. Validates the date is not in the past and may advance the order status. Requires confirmation.')]
class ScheduleInstallationTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The order ID to schedule')
                ->required(),
            'scheduled_date' => $schema->string()
                ->description('Installation date (YYYY-MM-DD). Must be today or in the future.')
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
                'scheduled_date' => $schema->string()->nullable(),
                'status' => $schema->string()->nullable(),
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
            'scheduled_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
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
        $newDate = $validated['scheduled_date'];
        $oldDate = $order->scheduled_date?->toDateString() ?? '(not set)';
        $newStatus = $order->status;

        if (in_array($order->status, ['pending', 'deposit_paid'])) {
            $newStatus = 'scheduled';
        }

        if ($isPreview && ! $isConfirmed) {
            $statusMsg = $newStatus !== $order->status
                ? "\nOrder status will also change from '{$order->status}' to '{$newStatus}'."
                : '';

            return Response::structured([
                'status' => 'preview',
                'message' => "I will schedule installation for order {$order->reference_number}.\n\nDate: {$oldDate} → {$newDate}".$statusMsg."\n\nIs that correct?",
                'data' => [
                    'id' => $order->id,
                    'old_scheduled_date' => $oldDate,
                    'new_scheduled_date' => $newDate,
                    'status' => $newStatus,
                ],
            ]);
        }

        $order->update([
            'scheduled_date' => $newDate,
            'status' => $newStatus,
        ]);

        $order->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => "Installation scheduled for order {$order->reference_number} on {$newDate}.",
            'url' => route('orders.show', $order),
            'order' => [
                'id' => $order->id,
                'reference_number' => $order->reference_number,
                'scheduled_date' => $order->scheduled_date?->toDateString(),
                'status' => $order->status,
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ]);
    }
}
