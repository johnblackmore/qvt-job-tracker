<?php

namespace App\Livewire\Banking;

use App\Models\BankTransaction;
use Livewire\Component;

class TransactionShow extends Component
{
    public BankTransaction $transaction;

    public string $notes = '';

    public string $expenseCategory = '';

    public string $reconciliationStatus = '';

    public function mount(BankTransaction $transaction): void
    {
        $this->transaction = $transaction;
        $this->notes = $transaction->notes ?? '';
        $this->expenseCategory = $transaction->expense_category ?? '';
        $this->reconciliationStatus = $transaction->reconciliation_status;
    }

    public function saveNotes(): void
    {
        $this->transaction->update([
            'notes' => $this->notes ?: null,
        ]);

        $this->dispatch('notify', message: 'Notes updated.', type: 'success');
    }

    public function saveCategory(): void
    {
        $this->transaction->update([
            'expense_category' => $this->expenseCategory ?: null,
        ]);

        $this->dispatch('notify', message: 'Category updated.', type: 'success');
    }

    public function toggleIgnored(): void
    {
        $newStatus = $this->reconciliationStatus === 'ignored' ? 'unmatched' : 'ignored';

        $this->transaction->update([
            'reconciliation_status' => $newStatus,
            'matched_payment_id' => $newStatus === 'ignored' ? null : $this->transaction->matched_payment_id,
        ]);

        $this->reconciliationStatus = $newStatus;

        $message = $newStatus === 'ignored' ? 'Transaction marked as ignored.' : 'Transaction re-opened.';

        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function render()
    {
        return view('livewire.banking.transaction-show');
    }
}
