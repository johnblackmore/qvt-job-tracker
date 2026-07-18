<?php

namespace App\Livewire\Expenses;

use App\Banking\Services\ReconciliationService;
use App\Models\BankTransaction;
use App\Models\Expense;
use Livewire\Component;

class ExpenseShow extends Component
{
    public Expense $expense;

    public ?int $selectedTransactionId = null;

    public string $matchAmount = '0';

    public bool $showLinkModal = false;

    public string $transactionSearch = '';

    public function mount(int $expenseId): void
    {
        $this->expense = Expense::with([
            'category',
            'lineItems',
            'documents',
            'bankTransaction',
            'createdBy',
        ])->findOrFail($expenseId);
    }

    public function delete(): void
    {
        $this->expense->delete();
        $this->redirect(route('expenses.index'), navigate: true);
    }

    public function openLinkModal(): void
    {
        $this->matchAmount = (string) $this->expense->total_amount;
        $this->selectedTransactionId = null;
        $this->transactionSearch = '';
        $this->showLinkModal = true;
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
    }

    public function linkTransaction(): void
    {
        $this->validate([
            'selectedTransactionId' => ['required', 'integer', 'exists:bank_transactions,id'],
            'matchAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $txn = BankTransaction::findOrFail($this->selectedTransactionId);
        $service = app(ReconciliationService::class);
        $service->matchExpense($txn, $this->expense, (float) $this->matchAmount, auth()->id());

        $this->showLinkModal = false;
        $this->expense->load('bankTransaction');
    }

    public function unlinkTransaction(): void
    {
        if ($this->expense->bankTransaction) {
            $service = app(ReconciliationService::class);
            $service->unlinkExpense($this->expense->bankTransaction);
            $this->expense->load('bankTransaction');
        }
    }

    public function render()
    {
        $transactions = collect();
        if ($this->showLinkModal) {
            $transactions = BankTransaction::unmatched()
                ->debits()
                ->when($this->transactionSearch, function ($q) {
                    $q->where(function ($query) {
                        $query->where('description', 'like', "%{$this->transactionSearch}%")
                            ->orWhere('merchant_name', 'like', "%{$this->transactionSearch}%");
                    });
                })
                ->with('bankAccount')
                ->latest('transaction_date')
                ->limit(20)
                ->get();
        }

        return view('livewire.expenses.expense-show', compact('transactions'));
    }
}
