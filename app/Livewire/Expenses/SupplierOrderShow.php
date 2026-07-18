<?php

namespace App\Livewire\Expenses;

use App\Models\SupplierOrder;
use Livewire\Component;

class SupplierOrderShow extends Component
{
    public SupplierOrder $supplierOrder;

    public function mount(int $supplierOrderId): void
    {
        $this->supplierOrder = SupplierOrder::with([
            'supplier',
            'lineItems',
            'documents',
            'createdBy',
        ])->findOrFail($supplierOrderId);
    }

    public function delete(): void
    {
        $this->supplierOrder->delete();
        $this->redirect(route('expenses.supplier-orders.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.expenses.supplier-order-show');
    }
}
