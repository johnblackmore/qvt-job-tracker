<?php

namespace App\Livewire\Banking;

use App\Banking\Services\BalanceService;
use App\Models\BankAccount;
use Livewire\Component;

class AccountList extends Component
{
    public function disconnect(BankAccount $account): void
    {
        $account->update(['is_active' => false]);

        $this->dispatch('notify', message: 'Bank account disconnected.', type: 'success');
    }

    public function refreshBalance(BankAccount $account): void
    {
        try {
            $refreshed = app(BalanceService::class)->refreshBalance($account);

            $balance = $refreshed->balance_pence !== null
                ? '£'.number_format($refreshed->balance_pence / 100, 2)
                : 'N/A';

            $this->dispatch('notify', message: "Balance refreshed: {$balance}", type: 'success');
        } catch (\Exception $e) {
            report($e);
            $this->dispatch('notify', message: 'Could not refresh balance. The account may need reconnecting.', type: 'error');
        }
    }

    public function render()
    {
        $accounts = BankAccount::orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        $balances = app(BalanceService::class)->getBalances()->keyBy('id');

        return view('livewire.banking.account-list', compact('accounts', 'balances'));
    }
}
