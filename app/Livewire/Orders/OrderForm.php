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

    public array $payments = [];

    public bool $showPaymentForm = false;

    public string $newPaymentAmount = '';

    public string $newPaymentMethod = 'bank_transfer';

    public string $newPaymentReference = '';

    public string $newPaymentDate = '';

    public function mount(?int $orderId = null, ?int $quoteId = null): void
    {
        if ($orderId) {
            $this->order = Order::with(['customer', 'quote', 'payments'])->findOrFail($orderId);
            $this->payments = $this->order->payments->toArray();
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
            $this->total_amount = (string) $quote->grand_total;
            $productCost = $quote->grand_total - $quote->labour_total;
            $this->deposit_required = (string) round(max($quote->grand_total * 0.5, $productCost), 2);
        }

        if (! $orderId) {
            $this->reference_number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));
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
            'quote_id' => ['nullable', 'exists:quotes,id'],
        ]);

        if ($validated['scheduled_date'] === '' || $validated['scheduled_date'] === null) {
            $validated['scheduled_date'] = null;
        }

        $validated['balance_due'] = $validated['total_amount'] - $validated['deposit_paid'];
        $validated['staff_user_id'] = auth()->id();

        if ($validated['status'] === 'completed' && ! $this->order?->completed_at) {
            $validated['completed_at'] = now();
        }

        if ($this->order) {
            $this->order->update($validated);
        } else {
            $order = Order::create($validated);

            if ($this->quote_id) {
                Quote::where('id', $this->quote_id)->update(['converted_order_id' => $order->id]);
            }
        }

        $this->dispatch('notify', message: $this->order ? 'Order updated.' : 'Order created.', type: 'success');
        $this->redirect(route('orders.index'), navigate: true);
    }

    public function openPaymentForm(): void
    {
        $this->showPaymentForm = true;
        $this->newPaymentAmount = '';
        $this->newPaymentMethod = 'bank_transfer';
        $this->newPaymentReference = '';
        $this->newPaymentDate = now()->format('Y-m-d');
    }

    public function closePaymentForm(): void
    {
        $this->showPaymentForm = false;
        $this->reset('newPaymentAmount', 'newPaymentMethod', 'newPaymentReference', 'newPaymentDate');
    }

    public function recordPayment(): void
    {
        $this->validate([
            'newPaymentAmount' => ['required', 'numeric', 'min:0.01'],
            'newPaymentMethod' => ['required', 'in:bank_transfer,card,cash,other'],
            'newPaymentReference' => ['nullable', 'string', 'max:255'],
            'newPaymentDate' => ['required', 'date'],
        ]);

        $order = $this->order;
        if (! $order) {
            return;
        }

        $payment = $order->payments()->create([
            'amount' => $this->newPaymentAmount,
            'method' => $this->newPaymentMethod,
            'reference' => $this->newPaymentReference ?: null,
            'paid_at' => $this->newPaymentDate,
            'notes' => null,
            'recorded_by_user_id' => auth()->id(),
        ]);

        $totalPaid = $order->payments()->sum('amount');
        $order->update(['deposit_paid' => $totalPaid]);

        $this->payments = $order->payments()->orderByDesc('paid_at')->get()->toArray();
        $this->deposit_paid = (string) $totalPaid;

        $this->closePaymentForm();

        $this->dispatch('notify', message: 'Payment recorded.', type: 'success');
    }

    public function removePayment(int $paymentId): void
    {
        $order = $this->order;
        if (! $order) {
            return;
        }

        $order->payments()->where('id', $paymentId)->delete();

        $totalPaid = $order->payments()->sum('amount');
        $order->update(['deposit_paid' => $totalPaid]);

        $this->payments = $order->payments()->orderByDesc('paid_at')->get()->toArray();
        $this->deposit_paid = (string) $totalPaid;

        $this->dispatch('notify', message: 'Payment removed.', type: 'success');
    }

    public function render()
    {
        $customers = Customer::orderBy('name')->get();
        $quotes = Quote::with('customer')->where('status', 'accepted')->orderByDesc('created_at')->get();

        return view('livewire.orders.order-form', compact('customers', 'quotes'));
    }
}
