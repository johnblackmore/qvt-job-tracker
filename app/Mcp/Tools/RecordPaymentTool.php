<?php

namespace App\Mcp\Tools;

use App\Models\BankTransaction;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Record a payment against an existing order. Optionally link to a bank transaction. Supports bank transfer, card, cash, or other methods. Requires confirmation after preview.')]
class RecordPaymentTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_id' => $schema->integer()
                ->description('The order ID to record the payment against')
                ->required(),
            'amount' => $schema->number()
                ->description('The payment amount in GBP')
                ->required(),
            'method' => $schema->string()
                ->description('Payment method: bank_transfer, card, cash, or other')
                ->default('bank_transfer'),
            'reference' => $schema->string()
                ->description('Optional payment reference (bank transaction ID, etc.)')
                ->nullable(),
            'paid_at' => $schema->string()
                ->description('Date the payment was made (YYYY-MM-DD). Defaults to today.')
                ->nullable(),
            'transaction_id' => $schema->integer()
                ->description('Optional bank transaction ID to link this payment to (reconciles in one step)')
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
            'url' => $schema->string()->description('Link to view the order in the staff admin area')->nullable(),
            'payment' => $schema->object([
                'id' => $schema->integer(),
                'order_id' => $schema->integer(),
                'amount' => $schema->number(),
                'method' => $schema->string(),
                'reference' => $schema->string()->nullable(),
                'paid_at' => $schema->string(),
                'order_reference' => $schema->string(),
                'total_paid' => $schema->number(),
                'deposit_required' => $schema->number(),
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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:bank_transfer,card,cash,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'transaction_id' => ['nullable', 'integer', 'exists:bank_transactions,id'],
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

        $order = Order::with('customer')->findOrFail($validated['order_id']);
        $paidAt = $validated['paid_at'] ?? now()->format('Y-m-d');
        $currentTotalPaid = (float) $order->deposit_paid;
        $newTotalPaid = $currentTotalPaid + (float) $validated['amount'];
        $balanceDue = max(0, (float) $order->total_amount - $newTotalPaid);

        $transaction = null;
        if (! empty($validated['transaction_id'])) {
            $transaction = BankTransaction::find($validated['transaction_id']);
        }

        if ($isPreview && ! $isConfirmed) {
            $msg = 'I will record a payment of £'.number_format((float) $validated['amount'], 2)
                ." for order {$order->reference_number} ({$order->customer->name}).\n\n"
                .'Current total paid: £'.number_format($currentTotalPaid, 2)."\n"
                .'New total paid: £'.number_format($newTotalPaid, 2)."\n"
                .'Balance due: £'.number_format($balanceDue, 2);

            if ($transaction) {
                $msg .= "\n\nThis payment will also be linked to bank transaction: {$transaction->description}.";
            }

            $msg .= "\n\nIs that correct?";

            return Response::structured([
                'status' => 'preview',
                'message' => $msg,
            ]);
        }

        $payment = DB::transaction(function () use ($order, $validated, $paidAt, $request, $transaction, $newTotalPaid) {
            $payment = $order->payments()->create([
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'paid_at' => $paidAt,
                'notes' => null,
                'recorded_by_user_id' => $request->user()?->id,
                'bank_transaction_id' => $transaction?->id,
            ]);

            $order->update(['deposit_paid' => $newTotalPaid]);

            if ($transaction) {
                $transaction->update([
                    'matched_payment_id' => $payment->id,
                    'reconciliation_status' => 'matched',
                ]);
            }

            return $payment;
        });

        return Response::structured([
            'status' => 'completed',
            'message' => sprintf(
                'Payment of £%s recorded for order %s.',
                number_format((float) $validated['amount'], 2),
                $order->reference_number
            ),
            'url' => route('orders.show', $order),
            'payment' => [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at->toIso8601String(),
                'order_reference' => $order->reference_number,
                'total_paid' => $newTotalPaid,
                'deposit_required' => $order->deposit_required,
            ],
        ]);
    }
}
