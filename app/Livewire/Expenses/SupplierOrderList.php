<?php

namespace App\Livewire\Expenses;

use App\Models\SupplierOrder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierOrderList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

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

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'dateFrom', 'dateTo']);
    }

    public function delete(int $id): void
    {
        SupplierOrder::find($id)?->delete();
    }

    public function render()
    {
        $orders = SupplierOrder::query()
            ->with('supplier')
            ->withCount('lineItems')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', "%{$this->search}%")
                        ->orWhere('invoice_number', 'like', "%{$this->search}%")
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->dateFrom, fn ($query) => $query->whereDate('order_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('order_date', '<=', $this->dateTo))
            ->latest('order_date')
            ->paginate(20);

        return view('livewire.expenses.supplier-order-list', compact('orders'));
    }
}
