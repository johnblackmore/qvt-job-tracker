<?php

namespace App\Banking\Console;

use App\Banking\Services\BankingProviderManager;
use App\Banking\Services\TransactionImportService;
use App\Models\BankAccount;
use Illuminate\Console\Command;

class ImportTransactionsCommand extends Command
{
    protected $signature = 'banking:import
        {--account= : The ID of the bank account to import transactions for}
        {--since= : Import transactions since this date (Y-m-d)}
        {--before= : Import transactions before this date (Y-m-d)}
        {--limit=100 : Maximum number of transactions to import per request}
        {--refresh : Refresh raw transaction data for existing transactions instead of skipping them}';

    protected $description = 'Import recent transactions from linked bank accounts';

    public function handle(
        BankingProviderManager $providerManager,
        TransactionImportService $importService,
    ): int {
        $accounts = BankAccount::where('is_active', true)->get();

        if ($accountId = $this->option('account')) {
            $accounts = $accounts->where('id', (int) $accountId);
        }

        if ($accounts->isEmpty()) {
            $this->warn('No active bank accounts found.');

            return Command::SUCCESS;
        }

        $allResults = [];

        foreach ($accounts as $account) {
            $this->info("Importing transactions for {$account->name}...");

            try {
                $provider = $providerManager->provider($account);

                $params = [
                    'limit' => (int) ($this->option('limit') ?? 100),
                ];

                if ($since = $this->option('since')) {
                    $params['since'] = $since;
                }

                if ($before = $this->option('before')) {
                    $params['before'] = $before;
                }

                $refresh = $this->option('refresh') ?? false;
                $result = $importService->import($account, $provider, $params, $refresh);

                $allResults[$account->name] = $result;

                $parts = [];
                if ($result['imported'] > 0) {
                    $parts[] = "Imported: {$result['imported']}";
                }
                if (($result['refreshed'] ?? 0) > 0) {
                    $parts[] = "Refreshed: {$result['refreshed']}";
                }
                $parts[] = "Skipped: {$result['skipped']}";
                $parts[] = "Errors: {$result['errors']}";
                $this->info('  '.implode(', ', $parts));
            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
                $allResults[$account->name] = ['error' => $e->getMessage()];
            }
        }

        $totalImported = collect($allResults)->sum('imported');
        $this->info("Done. Total imported: {$totalImported}.");

        return Command::SUCCESS;
    }
}
