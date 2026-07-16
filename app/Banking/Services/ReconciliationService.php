<?php

namespace App\Banking\Services;

use App\Models\BankTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function autoMatch(): array
    {
        $matched = 0;
        $ambiguous = 0;
        $unmatched = 0;

        $transactions = BankTransaction::unmatched()
            ->debits()
            ->whereNull('matched_payment_id')
            ->orderBy('transaction_date')
            ->get();

        foreach ($transactions as $txn) {
            $candidates = Payment::whereNull('bank_transaction_id')
                ->whereHas('order', function ($q) {
                    $q->whereIn('status', ['deposit_paid', 'scheduled', 'in_progress', 'completed']);
                })
                ->get()
                ->filter(function ($payment) use ($txn) {
                    return $this->isPotentialMatch($txn, $payment);
                });

            if ($candidates->count() === 1) {
                $this->linkTransaction($txn, $candidates->first());
                $matched++;
            } elseif ($candidates->count() > 1) {
                $this->storeCandidates($txn, $candidates);
                $ambiguous++;
            } else {
                $unmatched++;
            }
        }

        return compact('matched', 'ambiguous', 'unmatched');
    }

    public function isPotentialMatch(BankTransaction $txn, Payment $payment): bool
    {
        $amountTolerance = abs(abs($txn->amount) - (float) $payment->amount);

        if ($amountTolerance > 0.01) {
            return false;
        }

        $txnDate = $txn->transaction_date->startOfDay();
        $paymentDate = $payment->paid_at->startOfDay();
        $diffDays = abs($txnDate->diffInDays($paymentDate));

        if ($diffDays > 3) {
            return false;
        }

        return true;
    }

    public function linkTransaction(BankTransaction $txn, Payment $payment): void
    {
        $payment->update(['bank_transaction_id' => $txn->id]);

        $txn->update([
            'matched_payment_id' => $payment->id,
            'reconciliation_status' => 'matched',
        ]);
    }

    public function unlinkTransaction(BankTransaction $txn): void
    {
        DB::transaction(function () use ($txn) {
            if ($txn->matchedPayment) {
                $txn->matchedPayment->update(['bank_transaction_id' => null]);
            }

            $txn->update([
                'matched_payment_id' => null,
                'reconciliation_status' => 'unmatched',
                'metadata' => array_merge($txn->metadata ?? [], ['manual_reconciliation_candidates' => null]),
            ]);
        });
    }

    public function manualMatch(BankTransaction $txn, Payment $payment): void
    {
        DB::transaction(function () use ($txn, $payment) {
            if ($payment->bank_transaction_id && $payment->bank_transaction_id !== $txn->id) {
                $oldTxn = BankTransaction::find($payment->bank_transaction_id);
                if ($oldTxn) {
                    $oldTxn->update([
                        'matched_payment_id' => null,
                        'reconciliation_status' => 'unmatched',
                    ]);
                }
            }

            $this->linkTransaction($txn, $payment);
        });
    }

    public function getUnmatchedPayments()
    {
        return Payment::whereNull('bank_transaction_id')
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['deposit_paid', 'scheduled', 'in_progress', 'completed']);
            })
            ->with('order.customer')
            ->orderByDesc('paid_at')
            ->get();
    }

    public function getUnmatchedTransactions()
    {
        return BankTransaction::unmatched()
            ->whereNull('matched_payment_id')
            ->with('bankAccount')
            ->orderByDesc('transaction_date')
            ->get();
    }

    public function getSummary(): array
    {
        $totalTransactions = BankTransaction::count();
        $matchedTransactions = BankTransaction::where('reconciliation_status', 'matched')->count();
        $unmatchedTransactions = BankTransaction::where('reconciliation_status', 'unmatched')->count();
        $ignoredTransactions = BankTransaction::where('reconciliation_status', 'ignored')->count();

        $unlinkedPayments = Payment::whereNull('bank_transaction_id')
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['deposit_paid', 'scheduled', 'in_progress', 'completed']);
            })
            ->count();

        return [
            'total_transactions' => $totalTransactions,
            'matched_transactions' => $matchedTransactions,
            'unmatched_transactions' => $unmatchedTransactions,
            'ignored_transactions' => $ignoredTransactions,
            'unlinked_payments' => $unlinkedPayments,
            'match_rate' => $totalTransactions > 0
                ? round(($matchedTransactions / $totalTransactions) * 100, 1)
                : 0,
        ];
    }

    private function storeCandidates(BankTransaction $txn, $candidates): void
    {
        $candidateIds = $candidates->pluck('id')->toArray();

        $txn->update([
            'metadata' => array_merge($txn->metadata ?? [], [
                'manual_reconciliation_candidates' => $candidateIds,
            ]),
        ]);
    }
}
