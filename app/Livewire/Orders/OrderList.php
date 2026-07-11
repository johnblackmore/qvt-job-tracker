<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class OrderList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function delete(int $id): void
    {
        Order::find($id)?->delete();
        $this->dispatch('notify', message: 'Order deleted.', type: 'success');
    }

    public function render()
    {
        $orders = Order::query()
            ->with('customer')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', function ($cq) {
                            $cq->where('name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.orders.order-list', compact('orders'));
    }
}
