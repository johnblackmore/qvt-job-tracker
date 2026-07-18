<?php

namespace App\Banking\Console;

use App\Banking\Services\BalanceService;
use App\Models\BankAccount;
use Illuminate\Console\Command;

class RefreshBalancesCommand extends Command
{
    protected $signature = 'banking:refresh-balances
        {--account= : The ID of the bank account to refresh balance for}';

    protected $description = 'Refresh cached bank account balances from the banking provider';

    public function handle(BalanceService $balanceService): int
    {
        $accounts = BankAccount::where('is_active', true)->get();

        if ($accountId = $this->option('account')) {
            $accounts = $accounts->where('id', (int) $accountId);
        }

        if ($accounts->isEmpty()) {
            $this->warn('No active bank accounts found.');

            return Command::SUCCESS;
        }

        $results = [];

        foreach ($accounts as $account) {
            $this->info("Refreshing balance for {$account->name}...");

            try {
                $balanceService->refreshBalance($account);
                $results[$account->name] = ['status' => 'ok'];

                $this->info('  Done.');
            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
                $results[$account->name] = ['error' => $e->getMessage()];
            }
        }

        $okCount = collect($results)->where('status', 'ok')->count();
        $errorCount = collect($results)->where('error', fn ($v) => $v !== null)->count();
        $this->info("Done. Refreshed: {$okCount}, Failed: {$errorCount}.");

        return Command::SUCCESS;
    }
}
