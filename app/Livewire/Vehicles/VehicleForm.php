<?php

namespace App\Livewire\Vehicles;

use App\Models\Customer;
use App\Models\Vehicle;
use Livewire\Component;

class VehicleForm extends Component
{
    public Customer $customer;
    public ?Vehicle $vehicle = null;

    public string $make = '';
    public string $model = '';
    public string $registration = '';
    public string $year = '';
    public string $type = '';
    public string $notes = '';

    public function mount(int $customerId, ?int $vehicleId = null): void
    {
        $this->customer = Customer::findOrFail($customerId);

        if ($vehicleId) {
            $this->vehicle = Vehicle::findOrFail($vehicleId);
            $this->make = $this->vehicle->make ?? '';
            $this->model = $this->vehicle->model ?? '';
            $this->registration = $this->vehicle->registration ?? '';
            $this->year = $this->vehicle->year ?? '';
            $this->type = $this->vehicle->type ?? '';
            $this->notes = $this->vehicle->notes ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'registration' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'string', 'max:10'],
            'type' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $data = array_merge($validated, ['customer_id' => $this->customer->id]);

        if ($this->vehicle) {
            $this->vehicle->update($data);
        } else {
            Vehicle::create($data);
        }

        $this->redirect(route('customers.show', $this->customer->id), navigate: true);
    }

    public function render()
    {
        return view('livewire.vehicles.vehicle-form');
    }
}
