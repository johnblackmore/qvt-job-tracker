<?php

namespace App\Mcp\Tools\Banking;

use App\Banking\Services\ReconciliationService;
use App\Models\BankTransaction;
use App\Models\Payment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Manually match a bank transaction to an order payment. Preview shows both records for confirmation. Requires confirmation.')]
class ReconcilePaymentTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The bank transaction ID to match')
                ->required(),
            'payment_id' => $schema->integer()
                ->description('The payment ID to match the transaction to')
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
            'data' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:bank_transactions,id'],
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
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

        $transaction = BankTransaction::findOrFail($validated['transaction_id']);
        $payment = Payment::with('order.customer')->findOrFail($validated['payment_id']);

        if ($isPreview && ! $isConfirmed) {
            $msg = "I will link the following bank transaction to an order payment:\n\n"
                ."Transaction: {$transaction->description} ({$transaction->merchant_name})\n"
                .'Amount: -£'.number_format(abs($transaction->amount), 2)."\n"
                ."Date: {$transaction->transaction_date->format('j M Y')}\n\n"
                .'Payment: £'.number_format($payment->amount, 2)."\n"
                ."Order: {$payment->order?->reference_number} ({$payment->order?->customer?->name})\n"
                ."Paid: {$payment->paid_at->format('j M Y')}\n\n"
                .'Is that correct?';

            return Response::structured([
                'status' => 'preview',
                'message' => $msg,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'transaction_description' => $transaction->description,
                    'transaction_amount' => (float) $transaction->amount,
                    'payment_id' => $payment->id,
                    'payment_amount' => (float) $payment->amount,
                    'order_reference' => $payment->order?->reference_number,
                    'customer_name' => $payment->order?->customer?->name,
                ],
            ]);
        }

        $service = app(ReconciliationService::class);
        $service->manualMatch($transaction, $payment);

        return Response::structured([
            'status' => 'completed',
            'message' => "Linked \"{$transaction->description}\" to payment on order {$payment->order?->reference_number}.",
            'data' => [
                'transaction_id' => $transaction->id,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'order_reference' => $payment->order?->reference_number,
            ],
        ]);
    }
}
