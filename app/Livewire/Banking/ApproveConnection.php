<?php

namespace App\Livewire\Banking;

use App\Models\BankAccount;
use Livewire\Component;

class ApproveConnection extends Component
{
    public bool $hasSession = false;

    public string $error = '';

    public function mount(): void
    {
        $this->hasSession = session()->has('pending_monzo_account_id');
        $this->error = session('error', '');
    }

    public function retry()
    {
        $accountId = session('pending_monzo_account_id');
        $account = BankAccount::find($accountId);

        if (! $account) {
            $this->dispatch('notify', message: 'Session expired. Please connect your Monzo account again.', type: 'error');

            return;
        }

        $this->redirect(route('monzo.retry'), navigate: false);
    }

    public function cancel(): void
    {
        $accountId = session('pending_monzo_account_id');

        if ($accountId) {
            BankAccount::where('id', $accountId)->delete();
        }

        session()->forget(['pending_monzo_account_id', 'pending_monzo_accounts', 'pending_monzo_retry']);

        $this->redirect(
            route('admin.banking.transactions'),
            navigate: false,
        );
    }

    public function render()
    {
        return view('livewire.banking.approve-connection');
    }
}
