<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Component;

class CustomerForm extends Component
{
    public ?Customer $customer = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    public function mount(?int $customerId = null): void
    {
        if ($customerId) {
            $this->customer = Customer::findOrFail($customerId);
            $this->name = $this->customer->name;
            $this->email = $this->customer->email ?? '';
            $this->phone = $this->customer->phone ?? '';
            $this->address = $this->customer->address ?? '';
            $this->notes = $this->customer->notes ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->customer) {
            $this->customer->update($validated);
            $this->dispatch('customer-saved', customerId: $this->customer->id);
        } else {
            $customer = Customer::create($validated);
            $this->dispatch('customer-saved', customerId: $customer->id);
        }

        $this->redirect(route('customers.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.customers.customer-form');
    }
}
