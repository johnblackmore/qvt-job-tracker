<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function toggleActive(int $id): void
    {
        $supplier = Supplier::find($id);
        if ($supplier) {
            $supplier->update(['is_active' => ! $supplier->is_active]);
        }
    }

    public function delete(int $id): void
    {
        Supplier::find($id)?->delete();
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('contact_name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.suppliers.supplier-list', compact('suppliers'));
    }
}
