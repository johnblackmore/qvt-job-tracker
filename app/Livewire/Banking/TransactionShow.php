<?php

namespace App\Livewire\Banking;

use App\Banking\Jobs\SyncReceiptToMonzo;
use App\Models\BankTransaction;
use App\Models\Receipt;
use Livewire\Component;
use Livewire\WithFileUploads;

class TransactionShow extends Component
{
    use WithFileUploads;

    public BankTransaction $transaction;

    public string $notes = '';

    public string $expenseCategory = '';

    public string $reconciliationStatus = '';

    public bool $showRawData = false;

    public $upload = null;

    public function mount(BankTransaction $transaction): void
    {
        $this->transaction = $transaction->load('receipts');
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

    public function uploadReceipt(): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,gif', 'max:10240'],
        ]);

        $file = $this->upload;
        $filename = $file->getClientOriginalName();
        $path = $file->store('receipts/'.$this->transaction->bank_account_id.'/'.$this->transaction->id, 'local');

        $receipt = Receipt::create([
            'bank_transaction_id' => $this->transaction->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sync_status' => 'pending',
        ]);

        SyncReceiptToMonzo::dispatch($receipt);

        $this->upload = null;
        $this->transaction->load('receipts');

        $this->dispatch('notify', message: 'Receipt uploaded.', type: 'success');
    }

    public function removeReceipt(int $receiptId): void
    {
        $receipt = Receipt::findOrFail($receiptId);

        if ($receipt->file_path && file_exists(storage_path('app/'.$receipt->file_path))) {
            unlink(storage_path('app/'.$receipt->file_path));
        }

        $receipt->delete();

        $this->transaction->load('receipts');

        $this->dispatch('notify', message: 'Receipt removed.', type: 'success');
    }

    public function render()
    {
        $this->transaction->load('receipts');

        return view('livewire.banking.transaction-show');
    }
}
