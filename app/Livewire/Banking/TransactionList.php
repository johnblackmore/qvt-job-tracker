<?php

namespace App\Livewire\Banking;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $expenseCategory = '';

    public string $reconciliationStatus = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $bankAccountId = '';

    public string $sortField = 'transaction_date';

    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'expenseCategory' => ['except' => ''],
        'reconciliationStatus' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'bankAccountId' => ['except' => ''],
        'sortField' => ['except' => 'transaction_date'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingExpenseCategory(): void
    {
        $this->resetPage();
    }

    public function updatingReconciliationStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'expenseCategory', 'reconciliationStatus', 'dateFrom', 'dateTo', 'bankAccountId']);
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function render()
    {
        $query = BankTransaction::query()->with('bankAccount');

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('merchant_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($this->expenseCategory) {
            $query->where('expense_category', $this->expenseCategory);
        }

        if ($this->reconciliationStatus) {
            $query->where('reconciliation_status', $this->reconciliationStatus);
        }

        if ($this->dateFrom) {
            $query->whereDate('transaction_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('transaction_date', '<=', $this->dateTo);
        }

        if ($this->bankAccountId) {
            $query->where('bank_account_id', $this->bankAccountId);
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        $transactions = $query->paginate(25);

        $bankAccounts = BankAccount::where('is_active', true)->orderBy('name')->get();

        return view('livewire.banking.transaction-list', compact('transactions', 'bankAccounts'));
    }
}
