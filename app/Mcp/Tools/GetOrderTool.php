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
#[Description('Fetch a single order by ID, including customer, linked quote, assigned staff, deposit status, and email history count.')]
class GetOrderTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The order ID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->nullable(),
            'order' => $schema->object([]),
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
        ]);

        $order = Order::with(['customer', 'quote', 'staff'])
            ->withCount('emailsSent')
            ->findOrFail($validated['id']);

        return Response::structured([
            'status' => 'completed',
            'message' => "Retrieved order {$order->reference_number}.",
            'url' => route('orders.show', $order),
            'order' => [
                'id' => $order->id,
                'reference_number' => $order->reference_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'deposit_required' => $order->deposit_required,
                'deposit_paid' => $order->deposit_paid,
                'balance_due' => $order->balance_due,
                'deposit_percent' => $order->deposit_percent,
                'scheduled_date' => $order->scheduled_date?->toDateString(),
                'completed_at' => $order->completed_at?->toIso8601String(),
                'notes' => $order->notes,
                'created_at' => $order->created_at->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                ] : null,
                'quote' => $order->quote ? [
                    'id' => $order->quote->id,
                    'reference_number' => $order->quote->reference_number,
                    'status' => $order->quote->status,
                ] : null,
                'staff' => $order->staff ? [
                    'id' => $order->staff->id,
                    'name' => $order->staff->name,
                ] : null,
                'emails_sent_count' => $order->emails_sent_count,
            ],
        ]);
    }
}
