<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function delete(int $id): void
    {
        Customer::find($id)?->delete();
        $this->dispatch('customer-deleted');
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->withCount(['vehicles', 'enquiries', 'quotes'])
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.customers.customer-list', compact('customers'));
    }
}
