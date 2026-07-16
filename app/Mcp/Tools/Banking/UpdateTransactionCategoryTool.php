<?php

namespace App\Mcp\Tools\Banking;

use App\Models\BankTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update the expense category on a bank transaction. Preview shows the current and new category. Requires confirmation.')]
class UpdateTransactionCategoryTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The transaction ID')
                ->required(),
            'expense_category' => $schema->string()
                ->description('Expense category (stock, equipment, travel, fuel, subsistence, utilities, professional_fees, insurance, other, or empty to clear)')
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
            'transaction' => $schema->object([])->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:bank_transactions,id'],
            'expense_category' => ['nullable', 'string', 'max:50'],
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

        $transaction = BankTransaction::findOrFail($validated['id']);
        $newCategory = $validated['expense_category'] ?? null;
        $oldCategory = $transaction->expense_category;

        if ($newCategory === $oldCategory) {
            return Response::structured([
                'status' => 'completed',
                'message' => 'The expense category is already set to '.($newCategory ?: 'none').'. No change needed.',
                'transaction' => [
                    'id' => $transaction->id,
                    'description' => $transaction->description,
                    'expense_category' => $newCategory,
                ],
            ]);
        }

        if ($isPreview && ! $isConfirmed) {
            $oldLabel = $oldCategory ?: 'uncategorised';
            $newLabel = $newCategory ?: 'uncategorised';

            return Response::structured([
                'status' => 'preview',
                'message' => "I will update the expense category for \"{$transaction->description}\" from \"{$oldLabel}\" to \"{$newLabel}\".\n\nIs that correct?",
                'data' => [
                    'transaction_id' => $transaction->id,
                    'description' => $transaction->description,
                    'old_category' => $oldCategory,
                    'new_category' => $newCategory,
                ],
            ]);
        }

        $transaction->update([
            'expense_category' => $newCategory,
        ]);

        return Response::structured([
            'status' => 'completed',
            'message' => "Updated expense category for \"{$transaction->description}\" to ".($newCategory ?: 'none').'.',
            'transaction' => [
                'id' => $transaction->id,
                'description' => $transaction->description,
                'expense_category' => $newCategory,
            ],
        ]);
    }
}
