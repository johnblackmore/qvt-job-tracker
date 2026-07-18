<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ExpenseForm extends Component
{
    use WithFileUploads;

    public ?Expense $expense = null;

    public ?int $expense_category_id = null;

    public string $description = '';

    public string $merchant_name = '';

    public string $total_amount = '0';

    public string $vat_total = '0';

    public string $expense_date = '';

    public string $payment_method = '';

    public string $payment_reference = '';

    public string $status = 'draft';

    public string $notes = '';

    public bool $showLineItems = false;

    public array $lineItems = [];

    public $upload;

    public function mount(?int $expenseId = null): void
    {
        if ($expenseId) {
            $this->expense = Expense::with('lineItems')->findOrFail($expenseId);
            $this->expense_category_id = $this->expense->expense_category_id;
            $this->description = $this->expense->description;
            $this->merchant_name = $this->expense->merchant_name ?? '';
            $this->total_amount = (string) $this->expense->total_amount;
            $this->vat_total = (string) $this->expense->vat_total;
            $this->expense_date = $this->expense->expense_date->format('Y-m-d');
            $this->payment_method = $this->expense->payment_method ?? '';
            $this->payment_reference = $this->expense->payment_reference ?? '';
            $this->status = $this->expense->status;
            $this->notes = $this->expense->notes ?? '';

            if ($this->expense->lineItems->isNotEmpty()) {
                $this->showLineItems = true;
                foreach ($this->expense->lineItems as $item) {
                    $this->lineItems[] = [
                        'id' => $item->id,
                        'description' => $item->description,
                        'line_type' => $item->line_type,
                        'amount' => (string) $item->amount,
                        'unit_price' => (string) ($item->unit_price ?? 0),
                        'quantity' => (string) ($item->quantity ?? 1),
                        'vat_rate' => (string) ($item->vat_rate * 100),
                        'vat_amount' => (string) $item->vat_amount,
                        'line_type_category' => $item->line_type_category ?? '',
                    ];
                }
            }
        } else {
            $this->expense_date = now()->format('Y-m-d');
        }
    }

    public function toggleLineItems(): void
    {
        $this->showLineItems = ! $this->showLineItems;
        if ($this->showLineItems && empty($this->lineItems)) {
            $this->addLineItem();
        }
    }

    public function addLineItem(): void
    {
        $this->lineItems[] = [
            'id' => null,
            'description' => '',
            'line_type' => 'business',
            'amount' => '0',
            'unit_price' => '0',
            'quantity' => '1',
            'vat_rate' => '20',
            'vat_amount' => '0',
            'line_type_category' => '',
        ];
    }

    public function removeLineItem(int $index): void
    {
        unset($this->lineItems[$index]);
        $this->lineItems = array_values($this->lineItems);
        $this->recalculateTotals();
    }

    public function recalculateLineItem(int $index): void
    {
        $item = &$this->lineItems[$index];
        $amount = (float) ($item['amount'] ?? 0);
        $vatRate = (float) ($item['vat_rate'] ?? 20) / 100;
        $item['vat_amount'] = (string) round($amount * $vatRate, 2);
        $this->recalculateTotals();
    }

    public function recalculateTotals(): void
    {
        $total = 0;
        $vatTotal = 0;

        foreach ($this->lineItems as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 20) / 100;
            $total += $amount;
            $vatTotal += $amount * $vatRate;
        }

        $this->total_amount = (string) round($total, 2);
        $this->vat_total = (string) round($vatTotal, 2);
    }

    public function save(): void
    {
        $rules = [
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:5000'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,approved,paid,cancelled'],
            'notes' => ['nullable', 'string'],
        ];

        if ($this->showLineItems) {
            $rules['lineItems'] = ['required', 'array', 'min:1'];
            $rules['lineItems.*.description'] = ['required', 'string', 'max:5000'];
            $rules['lineItems.*.line_type'] = ['required', 'in:business,personal'];
            $rules['lineItems.*.amount'] = ['required', 'numeric', 'min:0'];
            $rules['lineItems.*.vat_rate'] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        $validated = $this->validate($rules);

        $data = [
            'expense_category_id' => $validated['expense_category_id'],
            'description' => $validated['description'],
            'merchant_name' => $validated['merchant_name'] ?: null,
            'total_amount' => $this->total_amount,
            'vat_total' => $this->vat_total,
            'expense_date' => $validated['expense_date'],
            'payment_method' => $validated['payment_method'] ?: null,
            'payment_reference' => $validated['payment_reference'] ?: null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->expense) {
            $this->expense->update($data);
            $this->expense->lineItems()->delete();
        } else {
            $data['reference_number'] = 'EXP-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
            $this->expense = Expense::create($data);
        }

        if ($this->showLineItems && ! empty($validated['lineItems'] ?? [])) {
            foreach ($validated['lineItems'] as $item) {
                $this->expense->lineItems()->create([
                    'description' => $item['description'],
                    'line_type' => $item['line_type'],
                    'amount' => $item['amount'],
                    'unit_price' => ! empty($item['unit_price']) ? (float) $item['unit_price'] : null,
                    'quantity' => ! empty($item['quantity']) ? (float) $item['quantity'] : null,
                    'vat_rate' => (float) ($item['vat_rate'] ?? 20) / 100,
                    'vat_amount' => (float) ($item['amount'] ?? 0) * ((float) ($item['vat_rate'] ?? 20) / 100),
                    'line_type_category' => $item['line_type_category'] ?? null,
                ]);
            }
        }

        if ($this->upload) {
            $filePath = $this->upload->store('expenses/documents', 'local');
            $this->expense->documents()->create([
                'documentable_type' => Expense::class,
                'file_path' => $filePath,
                'original_filename' => $this->upload->getClientOriginalName(),
                'mime_type' => $this->upload->getMimeType(),
                'file_size' => $this->upload->getSize(),
                'document_type' => 'invoice',
            ]);
        }

        $this->redirect(route('expenses.show', $this->expense->id), navigate: true);
    }

    protected function getListeners(): array
    {
        return [
            'apply-extracted-data' => 'applyExtractedData',
        ];
    }

    public function applyExtractedData(array $data): void
    {
        if (! empty($data['supplier_name'])) {
            $this->merchant_name = $data['supplier_name'];
        }

        if (! empty($data['invoice_date'])) {
            $this->expense_date = $data['invoice_date'];
        }

        if (! empty($data['total_amount'])) {
            $this->total_amount = (string) $data['total_amount'];
        }

        if (! empty($data['vat_total'])) {
            $this->vat_total = (string) $data['vat_total'];
        }

        if (! empty($data['invoice_number'])) {
            $this->payment_reference = $data['invoice_number'];
        }

        if (! empty($data['line_items']) && is_array($data['line_items'])) {
            $this->showLineItems = true;
            $this->lineItems = [];
            foreach ($data['line_items'] as $item) {
                $lineTotal = (float) ($item['line_total'] ?? 0);
                $unitAmount = (float) ($item['unit_amount'] ?? 0);
                $qty = (float) ($item['quantity'] ?? 1);
                $amount = $lineTotal > 0 ? $lineTotal : $unitAmount * $qty;
                $vatRate = (float) ($item['vat_rate'] ?? 0.20) * 100;
                $this->lineItems[] = [
                    'id' => null,
                    'description' => $item['description'] ?? '',
                    'line_type' => $item['line_type'] ?? 'business',
                    'amount' => (string) $amount,
                    'unit_price' => $unitAmount > 0 ? (string) $unitAmount : '0',
                    'quantity' => $qty > 0 ? (string) $qty : '1',
                    'vat_rate' => (string) $vatRate,
                    'vat_amount' => (string) round($amount * ($vatRate / 100), 2),
                    'line_type_category' => '',
                ];
            }

            $this->recalculateTotals();

            if (! empty($data['total_amount'])) {
                $this->total_amount = (string) $data['total_amount'];
            }

            if (! empty($data['vat_total'])) {
                $this->vat_total = (string) $data['vat_total'];
            }
        }

        $this->dispatch('$refresh');
    }

    public function render()
    {
        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();

        return view('livewire.expenses.expense-form', compact('categories'));
    }
}
