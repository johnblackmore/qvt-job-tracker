<?php

namespace App\Livewire\Expenses;

use App\Models\Allocation;
use App\Models\Order;
use App\Models\SupplierOrderLineItem;
use Livewire\Component;

class AllocationPanel extends Component
{
    public SupplierOrderLineItem $lineItem;

    public string $search = '';

    public array $allocations = [];

    protected $listeners = ['allocate' => 'loadLineItem'];

    public function loadLineItem(int $lineItemId): void
    {
        $this->lineItem = SupplierOrderLineItem::with('allocations')->findOrFail($lineItemId);
        $this->loadExistingAllocations();
    }

    public function loadExistingAllocations(): void
    {
        $this->allocations = [];
        foreach ($this->lineItem->allocations as $allocation) {
            $order = $allocation->allocatableTo;
            if ($order) {
                $this->allocations[] = [
                    'id' => $allocation->id,
                    'order_id' => $order->id,
                    'order_reference' => $order->reference_number,
                    'customer_name' => $order->customer?->name ?? 'Unknown',
                    'amount' => (string) $allocation->amount,
                ];
            }
        }
        // Add one empty row if none exist
        if (empty($this->allocations)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->allocations[] = [
            'id' => null,
            'order_id' => null,
            'order_reference' => '',
            'customer_name' => '',
            'amount' => '0',
        ];
    }

    public function removeRow(int $index): void
    {
        if (isset($this->allocations[$index]['id'])) {
            Allocation::find($this->allocations[$index]['id'])?->delete();
        }
        unset($this->allocations[$index]);
        $this->allocations = array_values($this->allocations);
    }

    public function updatedSearch(): void
    {
        $this->dispatch('search-updated');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.order_id' => ['required', 'integer', 'exists:orders,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Delete removed allocations (those no longer in the list)
        $this->lineItem->allocations()->delete();

        $totalAllocated = 0;

        foreach ($validated['allocations'] as $alloc) {
            $totalAllocated += (float) $alloc['amount'];
            Allocation::create([
                'allocatable_from_type' => SupplierOrderLineItem::class,
                'allocatable_from_id' => $this->lineItem->id,
                'allocatable_to_type' => Order::class,
                'allocatable_to_id' => $alloc['order_id'],
                'amount' => $alloc['amount'],
            ]);
        }

        $this->dispatch('allocations-saved', lineItemId: $this->lineItem->id, totalAllocated: $totalAllocated);
    }

    public function render()
    {
        $orders = Order::with('customer')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', fn ($sq) => $sq->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('livewire.expenses.allocation-panel', [
            'orders' => $orders,
            'lineTotal' => (float) $this->lineItem->line_total,
        ]);
    }
}
