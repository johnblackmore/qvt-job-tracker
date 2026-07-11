<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Component;

class CustomerShow extends Component
{
    public Customer $customer;

    public function mount(int $id): void
    {
        $this->customer = Customer::with(['vehicles', 'enquiries'])->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.customers.customer-show');
    }
}
