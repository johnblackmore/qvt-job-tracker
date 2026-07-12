<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use Livewire\Component;

class OrderShow extends Component
{
    public Order $order;

    public function mount(int $id): void
    {
        $this->order = Order::with(['customer', 'quote', 'staff', 'payments'])->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.orders.order-show');
    }
}
