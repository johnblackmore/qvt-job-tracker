<?php

namespace App\Mcp\Tools\Banking;

use App\Banking\Services\ReconciliationService;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\SupplierOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Match a bank transaction to an expense or supplier order. Preview shows both records for confirmation. Requires confirmation.')]
class ReconcileExpenseOrSupplierOrderTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The bank transaction ID to match')
                ->required(),
            'type' => $schema->string()
                ->description('Type of record to match: expense or supplier_order')
                ->enum(['expense', 'supplier_order'])
                ->required(),
            'record_id' => $schema->integer()
                ->description('The ID of the expense or supplier order to match')
                ->required(),
            'amount' => $schema->number()
                ->description('The amount being matched (defaults to transaction absolute amount)')
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
            'data' => $schema->object([])->nullable(),
            'url' => $schema->string()->description('Link to view the record in the staff admin area')->nullable(),
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
            'type' => ['required', 'string', Rule::in(['expense', 'supplier_order'])],
            'record_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
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

        $modelClass = $validated['type'] === 'expense' ? Expense::class : SupplierOrder::class;
        $record = $modelClass::findOrFail($validated['record_id']);

        $amount = $validated['amount'] ?? abs($transaction->amount);
        $recordLabel = $validated['type'] === 'expense'
            ? 'Expense '.$record->reference_number
            : 'Supplier order '.$record->reference_number;

        if ($isPreview && ! $isConfirmed) {
            $msg = "I will link the following bank transaction to a {$validated['type']}:\n\n"
                ."Transaction: {$transaction->description} ({$transaction->merchant_name})\n"
                .'Amount: -£'.number_format(abs($transaction->amount), 2)."\n"
                ."Date: {$transaction->transaction_date->format('j M Y')}\n\n"
                ."{$recordLabel}\n"
                .'Amount: £'.number_format($record->total_amount, 2)."\n"
                .'Match amount: £'.number_format($amount, 2)."\n\n"
                .'Is that correct?';

            return Response::structured([
                'status' => 'preview',
                'message' => $msg,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'transaction_description' => $transaction->description,
                    'transaction_amount' => (float) $transaction->amount,
                    'type' => $validated['type'],
                    'record_id' => $record->id,
                    'record_reference' => $record->reference_number,
                    'match_amount' => $amount,
                ],
            ]);
        }

        $service = app(ReconciliationService::class);
        $service->matchExpense($transaction, $record, $amount, $request->user()?->id);

        $url = $validated['type'] === 'expense'
            ? route('expenses.show', $record->id)
            : route('expenses.supplier-orders.show', $record->id);

        return Response::structured([
            'status' => 'completed',
            'message' => "Linked \"{$transaction->description}\" to {$recordLabel}.",
            'data' => [
                'transaction_id' => $transaction->id,
                'type' => $validated['type'],
                'record_id' => $record->id,
                'record_reference' => $record->reference_number,
                'match_amount' => $amount,
            ],
            'url' => $url,
        ]);
    }
}
