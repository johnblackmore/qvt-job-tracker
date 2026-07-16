<?php

namespace App\Banking\Services;

use App\Banking\Adapters\MonzoAdapter;
use App\Banking\Contracts\BankingProvider;
use App\Models\BankAccount;
use InvalidArgumentException;

class BankingProviderManager
{
    private array $adapters = [];

    public function provider(BankAccount $account): BankingProvider
    {
        $key = $account->id;

        if (! isset($this->adapters[$key])) {
            $this->adapters[$key] = $this->resolve($account);
        }

        return $this->adapters[$key];
    }

    public function resolve(BankAccount $account): BankingProvider
    {
        return match ($account->provider) {
            'monzo' => app(MonzoAdapter::class, ['account' => $account]),
            default => throw new InvalidArgumentException("Unsupported banking provider: {$account->provider}"),
        };
    }
}
