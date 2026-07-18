<?php

namespace App\Livewire\Expenses;

use App\Models\Supplier;
use App\Models\SupplierOrder;
use Illuminate\Support\Str;
use Livewire\Component;

class SupplierOrderForm extends Component
{
    public ?SupplierOrder $supplierOrder = null;

    public ?int $supplier_id = null;

    public string $order_date = '';

    public string $invoice_date = '';

    public string $invoice_number = '';

    public string $due_date = '';

    public string $subtotal = '0';

    public string $vat_total = '0';

    public string $total_amount = '0';

    public string $status = 'draft';

    public string $notes = '';

    public array $lineItems = [];

    public function mount(?int $supplierOrderId = null): void
    {
        if ($supplierOrderId) {
            $this->supplierOrder = SupplierOrder::with('lineItems')->findOrFail($supplierOrderId);
            $this->supplier_id = $this->supplierOrder->supplier_id;
            $this->order_date = $this->supplierOrder->order_date->format('Y-m-d');
            $this->invoice_date = $this->supplierOrder->invoice_date?->format('Y-m-d') ?? '';
            $this->invoice_number = $this->supplierOrder->invoice_number ?? '';
            $this->due_date = $this->supplierOrder->due_date?->format('Y-m-d') ?? '';
            $this->subtotal = (string) $this->supplierOrder->subtotal;
            $this->vat_total = (string) $this->supplierOrder->vat_total;
            $this->total_amount = (string) $this->supplierOrder->total_amount;
            $this->status = $this->supplierOrder->status;
            $this->notes = $this->supplierOrder->notes ?? '';

            foreach ($this->supplierOrder->lineItems as $item) {
                $this->lineItems[] = [
                    'id' => $item->id,
                    'line_type' => $item->line_type,
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_amount' => (string) $item->unit_amount,
                    'vat_rate' => (string) ($item->vat_rate * 100),
                    'vat_amount' => (string) $item->vat_amount,
                    'line_total' => (string) $item->line_total,
                    'line_type_category' => $item->line_type_category ?? 'stock',
                ];
            }
        } else {
            $this->order_date = now()->format('Y-m-d');
            $this->addLineItem();
        }
    }

    public function addLineItem(): void
    {
        $this->lineItems[] = [
            'id' => null,
            'line_type' => 'product',
            'description' => '',
            'quantity' => '1',
            'unit_amount' => '0',
            'vat_rate' => '20',
            'vat_amount' => '0',
            'line_total' => '0',
            'line_type_category' => 'stock',
        ];
    }

    public function removeLineItem(int $index): void
    {
        unset($this->lineItems[$index]);
        $this->lineItems = array_values($this->lineItems);
        $this->recalculateTotals();
    }

    public function updatedLineItems(): void
    {
        $this->recalculateTotals();
    }

    public function recalculateLineItem(int $index): void
    {
        $item = &$this->lineItems[$index];
        $qty = (float) ($item['quantity'] ?? 1);
        $unit = (float) ($item['unit_amount'] ?? 0);
        $vatRate = (float) ($item['vat_rate'] ?? 20) / 100;
        $lineNet = $qty * $unit;
        $vatAmount = $lineNet * $vatRate;
        $item['vat_amount'] = (string) round($vatAmount, 2);
        $item['line_total'] = (string) round($lineNet + $vatAmount, 2);
        $this->recalculateTotals();
    }

    public function recalculateTotals(): void
    {
        $subtotal = 0;
        $vatTotal = 0;

        foreach ($this->lineItems as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unit = (float) ($item['unit_amount'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 20) / 100;
            $lineNet = $qty * $unit;
            $subtotal += $lineNet;
            $vatTotal += $lineNet * $vatRate;
        }

        $this->subtotal = (string) round($subtotal, 2);
        $this->vat_total = (string) round($vatTotal, 2);
        $this->total_amount = (string) round($subtotal + $vatTotal, 2);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date', 'date_format:Y-m-d'],
            'invoice_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'status' => ['required', 'in:draft,ordered,received,partially_received,paid,cancelled'],
            'notes' => ['nullable', 'string'],
            'lineItems' => ['required', 'array', 'min:1'],
            'lineItems.*.line_type' => ['required', 'in:product,service,expense,personal'],
            'lineItems.*.description' => ['required', 'string', 'max:5000'],
            'lineItems.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lineItems.*.unit_amount' => ['required', 'numeric', 'min:0'],
            'lineItems.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'lineItems.*.line_type_category' => ['nullable', 'string', 'max:30'],
        ]);

        $data = [
            'supplier_id' => $validated['supplier_id'],
            'order_date' => $validated['order_date'],
            'invoice_date' => $validated['invoice_date'] ?: null,
            'invoice_number' => $validated['invoice_number'] ?: null,
            'due_date' => $validated['due_date'] ?: null,
            'subtotal' => $this->subtotal,
            'vat_total' => $this->vat_total,
            'total_amount' => $this->total_amount,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->supplierOrder) {
            $this->supplierOrder->update($data);
            $this->supplierOrder->lineItems()->delete();
        } else {
            $data['reference_number'] = 'PO-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
            $this->supplierOrder = SupplierOrder::create($data);
        }

        foreach ($validated['lineItems'] as $item) {
            $this->supplierOrder->lineItems()->create([
                'line_type' => $item['line_type'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_amount' => $item['unit_amount'],
                'vat_rate' => (float) ($item['vat_rate'] ?? 20) / 100,
                'vat_amount' => $item['vat_amount'] ?? 0,
                'line_total' => $item['line_total'] ?? 0,
                'line_type_category' => $item['line_type_category'] ?? null,
            ]);
        }

        $this->redirect(route('expenses.supplier-orders.show', $this->supplierOrder->id), navigate: true);
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('livewire.expenses.supplier-order-form', compact('suppliers'));
    }
}
