<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Create a new order for a customer, optionally linked to an accepted quote. Auto-calculates deposit. Requires confirmation.')]
class CreateOrderTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()
                ->description('The customer ID to associate the order with')
                ->required(),
            'quote_id' => $schema->integer()
                ->description('Optional accepted quote ID to pre-fill total and deposit from')
                ->nullable(),
            'notes' => $schema->string()
                ->description('Internal notes for the order')
                ->nullable(),
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
                'customer_id' => $schema->integer(),
                'customer_name' => $schema->string(),
                'quote_id' => $schema->integer()->nullable(),
                'status' => $schema->string(),
                'total_amount' => $schema->number()->nullable(),
                'deposit_required' => $schema->number()->nullable(),
                'balance_due' => $schema->number()->nullable(),
                'notes' => $schema->string()->nullable(),
                'created_at' => $schema->string()->nullable(),
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'quote_id' => ['nullable', 'integer', 'exists:quotes,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
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

        $customer = Customer::findOrFail($validated['customer_id']);
        $quote = null;

        if (! empty($validated['quote_id'])) {
            $quote = Quote::findOrFail($validated['quote_id']);
        }

        $reference = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        $totalAmount = $quote ? (float) $quote->grand_total : 0;
        $depositRequired = $quote ? round($totalAmount * 0.3, 2) : 0;
        $balanceDue = $totalAmount;

        if ($isPreview && ! $isConfirmed) {
            $previewMsg = "I will create a new order for {$customer->name}.\n\nReference: {$reference}\nTotal amount: £".number_format($totalAmount, 2)."\nDeposit required: £".number_format($depositRequired, 2)."\nBalance due: £".number_format($balanceDue, 2);

            if ($quote) {
                $previewMsg .= "\nLinked to quote: {$quote->reference_number}";
            }

            $previewMsg .= "\n\nIs that correct?";

            return Response::structured([
                'status' => 'preview',
                'message' => $previewMsg,
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'quote_id' => $quote?->id,
                    'reference_number' => $reference,
                    'total_amount' => $totalAmount,
                    'deposit_required' => $depositRequired,
                    'balance_due' => $balanceDue,
                    'notes' => $validated['notes'] ?? null,
                ],
            ]);
        }

        $order = Order::create([
            'customer_id' => $customer->id,
            'quote_id' => $quote?->id,
            'reference_number' => $reference,
            'status' => 'pending',
            'total_amount' => $totalAmount,
            'deposit_required' => $depositRequired,
            'deposit_paid' => 0,
            'balance_due' => $balanceDue,
            'notes' => $validated['notes'] ?? null,
            'staff_user_id' => $request->user()?->id,
        ]);

        if ($quote) {
            $quote->update(['converted_order_id' => $order->id]);
        }

        return Response::structured([
            'status' => 'completed',
            'message' => "I have created a new order ({$reference}) for {$customer->name}.",
            'url' => route('orders.show', $order),
            'order' => [
                'id' => $order->id,
                'reference_number' => $order->reference_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $customer->name,
                'quote_id' => $order->quote_id,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'deposit_required' => $order->deposit_required,
                'balance_due' => $order->balance_due,
                'notes' => $order->notes,
                'created_at' => $order->created_at->toIso8601String(),
            ],
        ]);
    }
}
