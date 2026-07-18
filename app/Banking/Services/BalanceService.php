<?php

namespace App\Banking\Services;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BalanceService
{
    private const CACHE_TTL = 14400;

    private const CACHE_PREFIX = 'banking_balance_';

    public function __construct(
        private BankingProviderManager $providerManager,
    ) {}

    public function getBalances(): Collection
    {
        $accounts = BankAccount::where('is_active', true)->get();

        foreach ($accounts as $account) {
            $this->loadBalance($account);
        }

        return $accounts;
    }

    public function getBalanceForAccount(BankAccount $account): BankAccount
    {
        $this->loadBalance($account);

        return $account->fresh();
    }

    public function refreshAllBalances(): Collection
    {
        $accounts = BankAccount::where('is_active', true)->get();

        foreach ($accounts as $account) {
            $this->refreshBalance($account);
        }

        return $accounts->fresh();
    }

    public function refreshBalance(BankAccount $account): BankAccount
    {
        Cache::forget(self::CACHE_PREFIX.$account->id);

        $this->fetchAndStoreBalance($account);

        return $account->fresh();
    }

    private function loadBalance(BankAccount $account): void
    {
        $balance = Cache::remember(
            self::CACHE_PREFIX.$account->id,
            self::CACHE_TTL,
            fn () => $this->fetchAndStoreBalance($account),
        );

        if ($balance !== null && is_array($balance)) {
            if (isset($balance['balance_pence'], $balance['balance_fetched_at'])) {
                $account->setAttribute('balance_pence', $balance['balance_pence']);
                $account->setAttribute('balance_fetched_at', $balance['balance_fetched_at']);
            }
        }
    }

    private function fetchAndStoreBalance(BankAccount $account): ?array
    {
        try {
            $provider = $this->providerManager->provider($account);
            $response = $provider->getBalance($account->provider_account_id);

            $balancePence = $response['balance'] ?? 0;
            $now = now();

            $account->updateQuietly([
                'balance_pence' => $balancePence,
                'balance_fetched_at' => $now,
            ]);

            return [
                'balance_pence' => $balancePence,
                'balance_fetched_at' => $now->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch balance for bank account {account_id}: {message}', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            return $account->balance_pence !== null
                ? [
                    'balance_pence' => $account->balance_pence,
                    'balance_fetched_at' => $account->balance_fetched_at?->toISOString(),
                ]
                : null;
        }
    }
}
