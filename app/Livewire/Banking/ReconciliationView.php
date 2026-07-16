<?php

namespace App\Livewire\Banking;

use App\Banking\Services\ReconciliationService;
use App\Models\BankTransaction;
use App\Models\Payment;
use Livewire\Component;

class ReconciliationView extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $selectedTransactionId = null;

    public ?int $selectedPaymentId = null;

    public bool $showMatched = false;

    public function runAutoMatch(ReconciliationService $service): void
    {
        $result = $service->autoMatch();

        $message = "Auto-matched {$result['matched']} transaction".($result['matched'] !== 1 ? 's' : '').'.';

        if ($result['ambiguous'] > 0) {
            $message .= " {$result['ambiguous']} had multiple candidates and need manual review.";
        }

        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function selectTransaction(int $id): void
    {
        $this->selectedTransactionId = $id;
    }

    public function selectPayment(int $id): void
    {
        $this->selectedPaymentId = $id;
    }

    public function linkSelected(ReconciliationService $service): void
    {
        if (! $this->selectedTransactionId || ! $this->selectedPaymentId) {
            $this->dispatch('notify', message: 'Select both a transaction and a payment to link.', type: 'warning');

            return;
        }

        $txn = BankTransaction::findOrFail($this->selectedTransactionId);
        $payment = Payment::findOrFail($this->selectedPaymentId);

        $service->manualMatch($txn, $payment);

        $this->selectedTransactionId = null;
        $this->selectedPaymentId = null;

        $this->dispatch('notify', message: 'Transaction linked to payment.', type: 'success');
    }

    public function unlink(int $transactionId, ReconciliationService $service): void
    {
        $txn = BankTransaction::findOrFail($transactionId);
        $service->unlinkTransaction($txn);

        $this->dispatch('notify', message: 'Transaction unlinked.', type: 'success');
    }

    public function render(ReconciliationService $service)
    {
        $unmatchedTransactions = $service->getUnmatchedTransactions();
        $unmatchedPayments = $service->getUnmatchedPayments();

        $matchedTransactions = BankTransaction::where('reconciliation_status', 'matched')
            ->whereNotNull('matched_payment_id')
            ->with(['bankAccount', 'matchedPayment.order.customer'])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $summary = $service->getSummary();

        return view('livewire.banking.reconciliation-view', compact(
            'unmatchedTransactions',
            'unmatchedPayments',
            'matchedTransactions',
            'summary',
        ));
    }
}
