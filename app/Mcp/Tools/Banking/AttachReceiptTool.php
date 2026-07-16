<?php

namespace App\Mcp\Tools\Banking;

use App\Models\BankTransaction;
use App\Models\Receipt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Upload a receipt and attach it to a bank transaction. Preview shows the transaction and filename. Requires confirmation.')]
class AttachReceiptTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The bank transaction ID to attach the receipt to')
                ->required(),
            'notes' => $schema->string()
                ->description('Optional notes about the receipt')
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
            'notes' => ['nullable', 'string', 'max:1000'],
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

        if ($isPreview && ! $isConfirmed) {
            $msg = 'I will create a receipt record attached to transaction "'.$transaction->description
                .'". You will need to upload the receipt file via the web interface at '
                .route('admin.banking.transactions.show', $transaction).'.';

            if ($validated['notes']) {
                $msg .= "\n\nNotes: ".$validated['notes'];
            }

            return Response::structured([
                'status' => 'preview',
                'message' => $msg,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'transaction_description' => $transaction->description,
                    'notes' => $validated['notes'] ?? null,
                    'upload_url' => route('admin.banking.transactions.show', $transaction),
                ],
            ]);
        }

        $receipt = Receipt::create([
            'bank_transaction_id' => $transaction->id,
            'file_path' => '',
            'original_filename' => 'pending-upload-'.now()->format('YmdHis'),
            'mime_type' => null,
            'file_size' => null,
            'notes' => $validated['notes'] ?? null,
            'sync_status' => 'pending',
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => 'Receipt placeholder created for "'.$transaction->description.'". Please upload the file via the transaction detail page.',
            'data' => [
                'receipt_id' => $receipt->id,
                'transaction_id' => $transaction->id,
                'upload_url' => route('admin.banking.transactions.show', $transaction),
            ],
        ]);
    }
}
