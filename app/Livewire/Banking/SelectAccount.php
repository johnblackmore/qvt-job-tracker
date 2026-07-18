<?php

namespace App\Livewire\Banking;

use App\Banking\Services\BankingProviderManager;
use App\Banking\Services\TransactionImportService;
use App\Models\BankAccount;
use Livewire\Component;

class SelectAccount extends Component
{
    public array $accounts = [];

    public bool $hasSession = false;

    public function mount(): void
    {
        $this->accounts = session('pending_monzo_accounts', []);
        $this->hasSession = ! empty($this->accounts);
    }

    public function linkAccount(int $index): void
    {
        $accounts = session('pending_monzo_accounts', []);
        $accountId = session('pending_monzo_account_id');
        $reconnectAccountId = session('reconnect_account_id');

        if (! isset($accounts[$index]) || ! $accountId) {
            $this->dispatch('notify', message: 'Session expired. Please connect your account again.', type: 'error');

            return;
        }

        $selected = $accounts[$index];
        $pending = BankAccount::find($accountId);

        if (! $pending) {
            $this->dispatch('notify', message: 'Account not found. Please reconnect.', type: 'error');

            return;
        }

        session()->forget([
            'pending_monzo_accounts',
            'pending_monzo_account_id',
            'reconnect_account_id',
            'reconnect_provider_account_id',
        ]);

        if ($reconnectAccountId) {
            $target = BankAccount::find($reconnectAccountId);

            if ($target) {
                $target->update([
                    'metadata' => $pending->metadata,
                    'provider_account_id' => $selected['id'],
                    'name' => $selected['description'],
                    'type' => $selected['account_type'],
                    'is_active' => true,
                ]);

                $pending->delete();

                $this->importAndRedirect($target, 'Monzo account reconnected successfully.');

                return;
            }
        }

        $this->linkAndFinish($pending, $selected['id'], $selected['description'], $selected['account_type']);
    }

    public function cancel(): void
    {
        $accountId = session('pending_monzo_account_id');

        if ($accountId) {
            BankAccount::where('id', $accountId)->delete();
        }

        session()->forget([
            'pending_monzo_accounts',
            'pending_monzo_account_id',
            'reconnect_account_id',
            'reconnect_provider_account_id',
        ]);

        $this->redirect(
            route('admin.banking.transactions'),
            navigate: false,
        );
    }

    private function linkAndFinish(BankAccount $account, string $providerAccountId, string $name, string $type): void
    {
        $account->update([
            'provider_account_id' => $providerAccountId,
            'name' => $name,
            'type' => $type,
        ]);

        $this->importAndRedirect($account, 'Monzo account linked.');
    }

    private function importAndRedirect(BankAccount $account, string $successMessage): void
    {
        try {
            $adapter = app(BankingProviderManager::class)->provider($account);
            $importService = app(TransactionImportService::class);
            $result = $importService->import($account, $adapter, [
                'limit' => 100,
                'since' => now()->subDays(90)->format('Y-m-d\TH:i:s\Z'),
            ]);
            $importMsg = ' '.$result['imported'].' transactions imported.';
        } catch (\Exception $e) {
            report($e);
            $importMsg = ' Transaction import failed — you can run it manually from the transactions page.';
        }

        $this->redirect(
            route('admin.banking.transactions'),
            navigate: false,
        );

        session()->flash('success', $successMessage.$importMsg);
    }

    public function render()
    {
        return view('livewire.banking.select-account');
    }
}
