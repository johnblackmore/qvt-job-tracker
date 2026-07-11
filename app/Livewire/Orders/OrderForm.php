<?php

namespace App\Livewire\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use Livewire\Component;

class OrderForm extends Component
{
    public ?Order $order = null;

    public ?int $quote_id = null;

    public ?int $customer_id = null;

    public string $reference_number = '';

    public string $status = 'pending';

    public string $total_amount = '0';

    public string $deposit_required = '0';

    public string $deposit_paid = '0';

    public string $scheduled_date = '';

    public string $notes = '';

    public function mount(?int $orderId = null, ?int $quoteId = null): void
    {
        if ($orderId) {
            $this->order = Order::with(['customer', 'quote'])->findOrFail($orderId);
            $this->quote_id = $this->order->quote_id;
            $this->customer_id = $this->order->customer_id;
            $this->reference_number = $this->order->reference_number;
            $this->status = $this->order->status;
            $this->total_amount = (string) $this->order->total_amount;
            $this->deposit_required = (string) $this->order->deposit_required;
            $this->deposit_paid = (string) $this->order->deposit_paid;
            $this->scheduled_date = $this->order->scheduled_date?->format('Y-m-d') ?? '';
            $this->notes = $this->order->notes ?? '';
        } elseif ($quoteId) {
            $quote = Quote::with('customer')->findOrFail($quoteId);
            $this->quote_id = $quote->id;
            $this->customer_id = $quote->customer_id;
            $this->reference_number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));
            $this->total_amount = (string) $quote->grand_total;
            $this->deposit_required = (string) round($quote->grand_total * 0.3, 2);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'reference_number' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:pending,deposit_paid,scheduled,in_progress,completed,cancelled'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'deposit_required' => ['required', 'numeric', 'min:0'],
            'deposit_paid' => ['required', 'numeric', 'min:0'],
            'scheduled_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['balance_due'] = $validated['total_amount'] - $validated['deposit_paid'];
        $validated['staff_user_id'] = auth()->id();

        if ($validated['status'] === 'completed' && ! $this->order?->completed_at) {
            $validated['completed_at'] = now();
        }

        if ($this->order) {
            $this->order->update($validated);
        } else {
            Order::create($validated);
        }

        $this->redirect(route('orders.index'), navigate: true);
    }

    public function render()
    {
        $customers = Customer::orderBy('name')->get();
        $quotes = Quote::with('customer')->where('status', 'accepted')->orderByDesc('created_at')->get();

        return view('livewire.orders.order-form', compact('customers', 'quotes'));
    }
}
