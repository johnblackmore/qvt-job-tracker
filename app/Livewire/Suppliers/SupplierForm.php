<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Livewire\Component;

class SupplierForm extends Component
{
    public ?Supplier $supplier = null;

    public string $name = '';

    public string $contact_name = '';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $address = '';

    public string $notes = '';

    public bool $is_active = true;

    public bool $default_trade_price_includes_vat = false;

    public function mount(?int $supplierId = null): void
    {
        if ($supplierId) {
            $this->supplier = Supplier::findOrFail($supplierId);
            $this->name = $this->supplier->name;
            $this->contact_name = $this->supplier->contact_name ?? '';
            $this->email = $this->supplier->email ?? '';
            $this->phone = $this->supplier->phone ?? '';
            $this->website = $this->supplier->website ?? '';
            $this->address = $this->supplier->address ?? '';
            $this->notes = $this->supplier->notes ?? '';
            $this->is_active = $this->supplier->is_active;
            $this->default_trade_price_includes_vat = $this->supplier->default_trade_price_includes_vat;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'default_trade_price_includes_vat' => ['boolean'],
        ]);

        if ($this->supplier) {
            $this->supplier->update($validated);
        } else {
            Supplier::create($validated);
        }

        $this->redirect(route('suppliers.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.suppliers.supplier-form');
    }
}
