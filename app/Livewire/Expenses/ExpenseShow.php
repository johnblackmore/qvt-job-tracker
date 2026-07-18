<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use Livewire\Component;

class ExpenseShow extends Component
{
    public Expense $expense;

    public function mount(int $expenseId): void
    {
        $this->expense = Expense::with([
            'category',
            'lineItems',
            'documents',
            'createdBy',
        ])->findOrFail($expenseId);
    }

    public function delete(): void
    {
        $this->expense->delete();
        $this->redirect(route('expenses.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.expenses.expense-show');
    }
}
