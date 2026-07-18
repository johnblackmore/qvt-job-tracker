<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryId = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryId', 'status', 'dateFrom', 'dateTo']);
    }

    public function delete(int $id): void
    {
        Expense::find($id)?->delete();
    }

    public function render()
    {
        $expenses = Expense::query()
            ->with('category')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', "%{$this->search}%")
                        ->orWhere('merchant_name', 'like', "%{$this->search}%")
                        ->orWhere('reference_number', 'like', "%{$this->search}%");
                });
            })
            ->when($this->categoryId, fn ($query) => $query->where('expense_category_id', $this->categoryId))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->dateFrom, fn ($query) => $query->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('expense_date', '<=', $this->dateTo))
            ->latest('expense_date')
            ->paginate(20);

        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();

        return view('livewire.expenses.expense-list', compact('expenses', 'categories'));
    }
}
