<?php

namespace App\Banking\Services;

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReconciliationLink;
use App\Models\SupplierOrder;
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

    public function suggestMatches(BankTransaction $txn): array
    {
        $suggestions = [];

        $amount = abs($txn->amount);
        $date = $txn->transaction_date;
        $merchant = strtolower($txn->merchant_name ?? '');
        $description = strtolower($txn->description ?? '');

        $searchTerms = array_filter(explode(' ', $merchant.' '.$description));

        if ($txn->amount < 0) {
            $payments = Payment::whereNull('bank_transaction_id')
                ->whereHas('order', function ($q) {
                    $q->whereIn('status', ['deposit_paid', 'scheduled', 'in_progress', 'completed']);
                })
                ->with('order.customer')
                ->get();

            foreach ($payments as $payment) {
                $paymentAmount = (float) $payment->amount;
                $amountDiff = abs($amount - $paymentAmount);
                $matchScore = 0;

                if ($amountDiff <= 0.01) {
                    $matchScore += 50;
                } elseif ($amountDiff <= 1) {
                    $matchScore += 30;
                } elseif ($amountDiff <= 10) {
                    $matchScore += 15;
                }

                $paymentDate = $payment->paid_at;
                $dayDiff = abs($date->diffInDays($paymentDate));
                if ($dayDiff <= 1) {
                    $matchScore += 20;
                } elseif ($dayDiff <= 3) {
                    $matchScore += 10;
                } elseif ($dayDiff <= 7) {
                    $matchScore += 5;
                }

                if ($merchant || $description) {
                    $customerName = strtolower($payment->order?->customer?->name ?? '');
                    $orderRef = strtolower($payment->order?->reference_number ?? '');
                    foreach ($searchTerms as $term) {
                        if (strlen($term) < 2) {
                            continue;
                        }
                        if (str_contains($customerName, $term)) {
                            $matchScore += 15;
                        }
                        if (str_contains($orderRef, $term)) {
                            $matchScore += 10;
                        }
                        if (str_contains($description, $term)) {
                            $matchScore += 5;
                        }
                    }
                }

                if ($matchScore > 0) {
                    $suggestions[] = [
                        'type' => 'payment',
                        'id' => $payment->id,
                        'reference' => $payment->order?->reference_number ?? 'Order #'.$payment->order_id,
                        'label' => $payment->order?->customer?->name ?? 'Unknown',
                        'amount' => $paymentAmount,
                        'date' => $paymentDate->format('j M Y'),
                        'score' => $matchScore,
                        'confidence' => $matchScore >= 60 ? 'High' : ($matchScore >= 30 ? 'Medium' : 'Low'),
                    ];
                }
            }

            $expenses = Expense::whereNull('bank_transaction_id')
                ->where('status', 'approved')
                ->get();

            foreach ($expenses as $expense) {
                $expenseAmount = (float) $expense->total_amount;
                $amountDiff = abs($amount - $expenseAmount);
                $matchScore = 0;

                if ($amountDiff <= 0.01) {
                    $matchScore += 50;
                } elseif ($amountDiff <= 1) {
                    $matchScore += 30;
                } elseif ($amountDiff <= 10) {
                    $matchScore += 15;
                }

                $expenseDate = $expense->expense_date;
                $dayDiff = abs($date->diffInDays($expenseDate));
                if ($dayDiff <= 3) {
                    $matchScore += 15;
                } elseif ($dayDiff <= 7) {
                    $matchScore += 8;
                } elseif ($dayDiff <= 14) {
                    $matchScore += 3;
                }

                if ($merchant || $description) {
                    $expMerchant = strtolower($expense->merchant_name ?? '');
                    $expDesc = strtolower($expense->description ?? '');
                    foreach ($searchTerms as $term) {
                        if (strlen($term) < 2) {
                            continue;
                        }
                        if (str_contains($expMerchant, $term)) {
                            $matchScore += 20;
                        }
                        if (str_contains($expDesc, $term)) {
                            $matchScore += 10;
                        }
                    }
                }

                if ($matchScore > 0) {
                    $suggestions[] = [
                        'type' => 'expense',
                        'id' => $expense->id,
                        'reference' => $expense->reference_number,
                        'label' => $expense->merchant_name ?? $expense->description,
                        'amount' => $expenseAmount,
                        'date' => $expense->expense_date?->format('j M Y') ?? '',
                        'score' => $matchScore,
                        'confidence' => $matchScore >= 60 ? 'High' : ($matchScore >= 30 ? 'Medium' : 'Low'),
                    ];
                }
            }

            $supplierOrders = SupplierOrder::whereNull('bank_transaction_id')
                ->whereIn('status', ['ordered', 'received', 'partially_received'])
                ->with('supplier')
                ->get();

            foreach ($supplierOrders as $order) {
                $orderAmount = (float) $order->total_amount;
                $amountDiff = abs($amount - $orderAmount);
                $matchScore = 0;

                if ($amountDiff <= 0.01) {
                    $matchScore += 50;
                } elseif ($amountDiff <= 1) {
                    $matchScore += 30;
                } elseif ($amountDiff <= 10) {
                    $matchScore += 15;
                }

                $orderDate = $order->order_date;
                $dayDiff = abs($date->diffInDays($orderDate));
                if ($dayDiff <= 3) {
                    $matchScore += 15;
                } elseif ($dayDiff <= 7) {
                    $matchScore += 8;
                } elseif ($dayDiff <= 14) {
                    $matchScore += 3;
                }

                if ($merchant || $description) {
                    $supplierName = strtolower($order->supplier?->name ?? '');
                    $invNumber = strtolower($order->invoice_number ?? '');
                    foreach ($searchTerms as $term) {
                        if (strlen($term) < 2) {
                            continue;
                        }
                        if (str_contains($supplierName, $term)) {
                            $matchScore += 20;
                        }
                        if (str_contains($invNumber, $term)) {
                            $matchScore += 10;
                        }
                    }
                }

                if ($matchScore > 0) {
                    $suggestions[] = [
                        'type' => 'supplier_order',
                        'id' => $order->id,
                        'reference' => $order->reference_number,
                        'label' => $order->supplier?->name ?? $order->invoice_number ?? '',
                        'amount' => $orderAmount,
                        'date' => $order->order_date?->format('j M Y') ?? '',
                        'score' => $matchScore,
                        'confidence' => $matchScore >= 60 ? 'High' : ($matchScore >= 30 ? 'Medium' : 'Low'),
                    ];
                }
            }
        } else {
            $orders = Order::whereIn('status', ['pending', 'deposit_paid', 'scheduled', 'in_progress'])
                ->whereColumn('deposit_paid', '<', 'total_amount')
                ->with('customer')
                ->get();

            foreach ($orders as $order) {
                $balanceDue = (float) $order->total_amount - (float) $order->deposit_paid;
                $amountDiff = abs($amount - $balanceDue);
                $matchScore = 0;

                if ($amountDiff <= 0.01) {
                    $matchScore += 50;
                } elseif ($amountDiff <= 1) {
                    $matchScore += 30;
                } elseif ($amountDiff <= 10) {
                    $matchScore += 15;
                }

                if ($merchant || $description) {
                    $custName = strtolower($order->customer?->name ?? '');
                    $orderRef = strtolower($order->reference_number ?? '');
                    foreach ($searchTerms as $term) {
                        if (strlen($term) < 2) {
                            continue;
                        }
                        if (str_contains($custName, $term)) {
                            $matchScore += 15;
                        }
                        if (str_contains($orderRef, $term)) {
                            $matchScore += 10;
                        }
                    }
                }

                if ($matchScore > 0) {
                    $suggestions[] = [
                        'type' => 'record_payment',
                        'id' => $order->id,
                        'reference' => $order->reference_number,
                        'label' => $order->customer?->name ?? 'Unknown',
                        'amount' => $balanceDue,
                        'date' => '',
                        'score' => $matchScore,
                        'confidence' => $matchScore >= 60 ? 'High' : ($matchScore >= 30 ? 'Medium' : 'Low'),
                    ];
                }
            }
        }

        usort($suggestions, fn ($a, $b) => $b['score'] - $a['score']);

        return array_slice($suggestions, 0, 5);
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

            $txn->reconciliationLink?->delete();

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

    public function matchExpense(BankTransaction $txn, SupplierOrder|Expense $expensable, float $amount, ?int $userId = null): ReconciliationLink
    {
        return DB::transaction(function () use ($txn, $expensable, $amount, $userId) {
            // Deduct amount from existing link if re-matching
            $txn->reconciliationLink?->delete();

            $link = ReconciliationLink::create([
                'bank_transaction_id' => $txn->id,
                'reconcilable_type' => $expensable::class,
                'reconcilable_id' => $expensable->id,
                'amount' => $amount,
                'matched_by_user_id' => $userId,
                'matched_at' => now(),
            ]);

            $txn->update([
                'reconciliation_status' => 'matched',
            ]);

            // Update the expense's bank_transaction_id for convenience
            if ($expensable instanceof SupplierOrder) {
                $expensable->update(['bank_transaction_id' => $txn->id, 'paid_at' => now()]);
            } else {
                $expensable->update(['bank_transaction_id' => $txn->id, 'paid_at' => now()]);
            }

            return $link;
        });
    }

    public function unlinkExpense(BankTransaction $txn): void
    {
        DB::transaction(function () use ($txn) {
            $link = $txn->reconciliationLink;
            if ($link) {
                $expensable = $link->reconcilable;
                if ($expensable) {
                    $expensable->update(['bank_transaction_id' => null]);
                }
                $link->delete();
            }

            $txn->update([
                'reconciliation_status' => 'unmatched',
            ]);
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

    public function getUnmatchedExpenses()
    {
        $supplierOrders = SupplierOrder::whereNull('bank_transaction_id')
            ->whereIn('status', ['ordered', 'received', 'partially_received'])
            ->with('supplier')
            ->get();

        $expenses = Expense::whereNull('bank_transaction_id')
            ->where('status', 'approved')
            ->with('category')
            ->get();

        return $supplierOrders->concat($expenses);
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

        $unlinkedExpenses = SupplierOrder::whereNull('bank_transaction_id')
            ->whereIn('status', ['ordered', 'received', 'partially_received'])
            ->count()
            + Expense::whereNull('bank_transaction_id')
                ->where('status', 'approved')
                ->count();

        return [
            'total_transactions' => $totalTransactions,
            'matched_transactions' => $matchedTransactions,
            'unmatched_transactions' => $unmatchedTransactions,
            'ignored_transactions' => $ignoredTransactions,
            'unlinked_payments' => $unlinkedPayments,
            'unlinked_expenses' => $unlinkedExpenses,
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
