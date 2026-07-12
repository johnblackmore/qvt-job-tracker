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
#[Description('Record a deposit payment against an order. Recalculates balance due and may advance the order status. Requires confirmation.')]
class UpdateDepositTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The order ID to update')
                ->required(),
            'deposit_paid' => $schema->number()
                ->description('New total deposit amount paid (must be 0 to total_amount)')
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
                'deposit_paid' => $schema->number()->nullable(),
                'deposit_required' => $schema->number()->nullable(),
                'balance_due' => $schema->number()->nullable(),
                'deposit_percent' => $schema->number()->nullable(),
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
            'deposit_paid' => ['required', 'numeric', 'min:0'],
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
        $newDeposit = (float) $validated['deposit_paid'];
        $totalAmount = (float) $order->total_amount;

        if ($newDeposit > $totalAmount) {
            return Response::error(
                'Deposit paid (£'.number_format($newDeposit, 2).') cannot exceed the total order amount (£'.number_format($totalAmount, 2).').'
            );
        }

        $newBalance = round($totalAmount - $newDeposit, 2);
        $depositPercent = $order->deposit_required > 0
            ? round(($newDeposit / $order->deposit_required) * 100, 1)
            : 0;
        $newStatus = $order->status;

        if ($newDeposit > 0 && $order->status === 'pending') {
            $newStatus = 'deposit_paid';
        }

        if ($isPreview && ! $isConfirmed) {
            $statusMsg = $newStatus !== $order->status
                ? "\nOrder status will also change from '{$order->status}' to '{$newStatus}'."
                : '';

            return Response::structured([
                'status' => 'preview',
                'message' => "I will update the deposit for order {$order->reference_number}.\n\nDeposit paid: £".number_format($newDeposit, 2).' of £'.number_format($order->deposit_required, 2)."\nBalance due: £".number_format($newBalance, 2).' ('.$depositPercent.'%)'.$statusMsg."\n\nIs that correct?",
                'data' => [
                    'id' => $order->id,
                    'deposit_paid' => $newDeposit,
                    'deposit_required' => (float) $order->deposit_required,
                    'balance_due' => $newBalance,
                    'deposit_percent' => $depositPercent,
                    'status' => $newStatus,
                ],
            ]);
        }

        $order->update([
            'deposit_paid' => $newDeposit,
            'balance_due' => $newBalance,
            'status' => $newStatus,
        ]);

        $order->refresh();

        return Response::structured([
            'status' => 'completed',
            'message' => "Deposit updated for order {$order->reference_number}. Deposit: £".number_format($newDeposit, 2).' / £'.number_format($order->deposit_required, 2).' ('.$depositPercent.'%). Balance due: £'.number_format($newBalance, 2).'.',
            'url' => route('orders.show', $order),
            'order' => [
                'id' => $order->id,
                'reference_number' => $order->reference_number,
                'deposit_paid' => $order->deposit_paid,
                'deposit_required' => $order->deposit_required,
                'balance_due' => $order->balance_due,
                'deposit_percent' => $depositPercent,
                'status' => $order->status,
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ]);
    }
}
