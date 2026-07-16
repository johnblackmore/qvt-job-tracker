<?php

namespace App\Mcp\Tools\Banking;

use App\Banking\Services\BankingProviderManager;
use App\Banking\Services\TransactionImportService;
use App\Models\BankAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Import recent transactions from a linked bank account. Preview shows how many new transactions will be imported. Requires confirmation.')]
class ImportTransactionsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'bank_account_id' => $schema->integer()
                ->description('The ID of the bank account to import transactions for')
                ->nullable(),
            'since' => $schema->string()
                ->description('Import transactions since this date (YYYY-MM-DD). Defaults to 7 days ago.')
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
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'since' => ['nullable', 'date', 'date_format:Y-m-d'],
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

        $since = $validated['since'] ?? now()->subDays(7)->format('Y-m-d');

        $accounts = BankAccount::where('is_active', true);

        if (! empty($validated['bank_account_id'])) {
            $accounts->where('id', $validated['bank_account_id']);
        }

        $accounts = $accounts->get();

        if ($accounts->isEmpty()) {
            return Response::error('No active bank accounts found. Link a bank account first.');
        }

        if ($isPreview && ! $isConfirmed) {
            $accountNames = $accounts->pluck('name')->implode(', ');

            return Response::structured([
                'status' => 'preview',
                'message' => "I will import transactions since {$since} for: {$accountNames}\n\nIs that correct?",
                'data' => [
                    'since' => $since,
                    'accounts' => $accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->toArray(),
                ],
            ]);
        }

        $providerManager = app(BankingProviderManager::class);
        $importService = app(TransactionImportService::class);

        $results = [];

        foreach ($accounts as $account) {
            $provider = $providerManager->provider($account);

            $params = ['since' => $since];

            $result = $importService->import($account, $provider, $params);

            $results[$account->name] = $result;
        }

        $totalImported = collect($results)->sum('imported');
        $totalSkipped = collect($results)->sum('skipped');
        $totalErrors = collect($results)->sum('errors');

        $message = "Imported {$totalImported} transaction".($totalImported !== 1 ? 's' : '').'.';

        if ($totalSkipped > 0) {
            $message .= " {$totalSkipped} skipped (already imported or pending).";
        }

        if ($totalErrors > 0) {
            $message .= " {$totalErrors} error".($totalErrors !== 1 ? 's' : '').'.';
        }

        return Response::structured([
            'status' => 'completed',
            'message' => $message,
            'data' => [
                'imported' => $totalImported,
                'skipped' => $totalSkipped,
                'errors' => $totalErrors,
                'accounts' => $results,
            ],
        ]);
    }
}
