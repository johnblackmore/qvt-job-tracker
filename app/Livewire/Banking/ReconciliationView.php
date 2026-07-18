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

    public ?int $selectedExpenseId = null;

    public string $selectedExpenseType = '';

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

    public function selectExpense(int $id, string $type): void
    {
        $this->selectedExpenseId = $id;
        $this->selectedExpenseType = $type;
    }

    public function linkSelected(ReconciliationService $service): void
    {
        if ($this->selectedTransactionId && $this->selectedPaymentId) {
            $txn = BankTransaction::findOrFail($this->selectedTransactionId);
            $payment = Payment::findOrFail($this->selectedPaymentId);

            $service->manualMatch($txn, $payment);

            $this->selectedTransactionId = null;
            $this->selectedPaymentId = null;

            $this->dispatch('notify', message: 'Transaction linked to payment.', type: 'success');
        } elseif ($this->selectedTransactionId && $this->selectedExpenseId) {
            $service->matchExpense($this->selectedTransactionId, $this->selectedExpenseType, $this->selectedExpenseId);

            $this->selectedTransactionId = null;
            $this->selectedExpenseId = null;
            $this->selectedExpenseType = '';

            $this->dispatch('notify', message: 'Transaction linked to expense.', type: 'success');
        } else {
            $this->dispatch('notify', message: 'Select both a transaction and a payment or expense to link.', type: 'warning');
        }
    }

    public function unlink(int $transactionId, ReconciliationService $service): void
    {
        $txn = BankTransaction::findOrFail($transactionId);

        if ($txn->matchedPayment) {
            $service->unlinkTransaction($txn);
        } else {
            $service->unlinkExpense($transactionId);
        }

        $this->dispatch('notify', message: 'Transaction unlinked.', type: 'success');
    }

    public function render(ReconciliationService $service)
    {
        $unmatchedTransactions = $service->getUnmatchedTransactions();
        $unmatchedPayments = $service->getUnmatchedPayments();

        $unmatchedExpenses = $service->getUnmatchedExpenses();

        $matchedTransactions = BankTransaction::where('reconciliation_status', 'matched')
            ->where(function ($q) {
                $q->whereNotNull('matched_payment_id')
                    ->orWhereHas('reconciliationLink');
            })
            ->with(['bankAccount', 'matchedPayment.order.customer', 'reconciliationLink'])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $summary = $service->getSummary();

        return view('livewire.banking.reconciliation-view', compact(
            'unmatchedTransactions',
            'unmatchedPayments',
            'unmatchedExpenses',
            'matchedTransactions',
            'summary',
        ));
    }
}
