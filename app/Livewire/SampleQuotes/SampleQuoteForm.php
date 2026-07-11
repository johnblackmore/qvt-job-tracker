<?php

namespace App\Livewire\SampleQuotes;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SampleQuote;
use App\Models\Supplier;
use Livewire\Component;

class SampleQuoteForm extends Component
{
    public ?SampleQuote $sampleQuote = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public string $notes = '';

    public array $lineItems = [];

    public string $selectedCategory = '';

    public function mount(?int $sampleQuoteId = null): void
    {
        if ($sampleQuoteId) {
            $this->sampleQuote = SampleQuote::findOrFail($sampleQuoteId);
            $this->name = $this->sampleQuote->name;
            $this->description = $this->sampleQuote->description ?? '';
            $this->is_active = $this->sampleQuote->is_active;
            $this->notes = $this->sampleQuote->notes ?? '';
            $this->lineItems = $this->sampleQuote->line_items ?? [];
        }
    }

    public function addProductLine(int $productId): void
    {
        $product = Product::with('suppliers')->find($productId);
        if (! $product) {
            return;
        }

        $preferredSupplier = $product->preferredSupplier();
        $tradePrice = $preferredSupplier ? $preferredSupplier->pivot->trade_price : 0;

        $this->lineItems[] = [
            'line_type' => 'product',
            'product_id' => $product->id,
            'product_supplier_id' => $preferredSupplier?->pivot?->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_retail_price' => (string) $product->retail_price,
            'unit_trade_price' => (string) $tradePrice,
            'notes' => '',
        ];
    }

    public function addLabourLine(): void
    {
        $this->lineItems[] = [
            'line_type' => 'labour',
            'product_id' => null,
            'product_supplier_id' => null,
            'description' => 'Labour',
            'quantity' => 1,
            'unit_retail_price' => '0',
            'unit_trade_price' => '0',
            'notes' => '',
        ];
    }

    public function addAdHocLine(): void
    {
        $this->lineItems[] = [
            'line_type' => 'ad_hoc',
            'product_id' => null,
            'product_supplier_id' => null,
            'description' => '',
            'quantity' => 1,
            'unit_retail_price' => '0',
            'unit_trade_price' => '0',
            'notes' => '',
        ];
    }

    public function removeLineItem(int $index): void
    {
        unset($this->lineItems[$index]);
        $this->lineItems = array_values($this->lineItems);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['line_items'] = $this->lineItems;

        if ($this->sampleQuote) {
            $this->sampleQuote->update($validated);
        } else {
            SampleQuote::create($validated);
        }

        $this->redirect(route('sample-quotes.index'), navigate: true);
    }

    public function render()
    {
        $categories = ProductCategory::with(['products' => function ($query) {
            $query->where('is_active', true)->orderBy('name');
        }])->orderBy('name')->get();

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('livewire.sample-quotes.sample-quote-form', compact('categories', 'suppliers'));
    }
}
