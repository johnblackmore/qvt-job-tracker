<?php

namespace App\Livewire\Quotes;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\SampleQuote;
use Livewire\Component;

class QuoteBuilder extends Component
{
    public ?Quote $quote = null;

    public ?SampleQuote $sampleQuote = null;

    public ?int $customer_id = null;

    public string $reference_number = '';

    public string $status = 'draft';

    public string $valid_until = '';

    public string $notes = '';

    public array $lineItems = [];

    public string $selectedCategory = '';

    public ?int $enquiry_id = null;

    public ?int $enquiryId = null;

    public function mount(?int $quoteId = null, ?int $sampleQuoteId = null, ?int $customerId = null, ?int $enquiryId = null): void
    {
        $this->valid_until = now()->addDays(30)->format('Y-m-d');

        if ($quoteId) {
            $this->quote = Quote::with('lineItems')->findOrFail($quoteId);
            $this->customer_id = $this->quote->customer_id;
            $this->reference_number = $this->quote->reference_number;
            $this->status = $this->quote->status;
            $this->valid_until = $this->quote->valid_until?->format('Y-m-d') ?? '';
            $this->notes = $this->quote->notes ?? '';

            foreach ($this->quote->lineItems as $item) {
                $this->lineItems[] = [
                    'line_type' => $item->line_type,
                    'product_id' => $item->product_id,
                    'product_supplier_id' => $item->product_supplier_id,
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_retail_price' => (string) $item->unit_retail_price,
                    'unit_trade_price' => (string) $item->unit_trade_price,
                    'notes' => $item->notes ?? '',
                ];
            }
        } elseif ($sampleQuoteId) {
            $this->sampleQuote = SampleQuote::findOrFail($sampleQuoteId);
            $this->notes = $this->sampleQuote->notes ?? '';

            foreach ($this->sampleQuote->line_items ?? [] as $item) {
                $this->lineItems[] = [
                    'line_type' => $item['line_type'] ?? 'product',
                    'product_id' => $item['product_id'] ?? null,
                    'product_supplier_id' => $item['product_supplier_id'] ?? null,
                    'description' => $item['description'] ?? '',
                    'quantity' => (string) ($item['quantity'] ?? 1),
                    'unit_retail_price' => (string) ($item['unit_retail_price'] ?? 0),
                    'unit_trade_price' => (string) ($item['unit_trade_price'] ?? 0),
                    'notes' => $item['notes'] ?? '',
                ];
            }
        }

        $this->enquiryId = $enquiryId ?? (request()->query('enquiryId') ? (int) request()->query('enquiryId') : null);

        if (! $this->customer_id) {
            $this->customer_id = $customerId ?? (request()->query('customerId') ? (int) request()->query('customerId') : null);
        }

        if (! $this->customer_id && $this->enquiryId) {
            $enquiry = Enquiry::find($this->enquiryId);
            if ($enquiry && $enquiry->customer_id) {
                $this->customer_id = $enquiry->customer_id;
            }
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
            'quantity' => '1',
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
            'quantity' => '1',
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
            'quantity' => '1',
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

    public function updatedLineItems(): void
    {
        // Recalculate totals when line items change
    }

    public function getTotalsProperty(): array
    {
        $totalRetail = 0;
        $totalTrade = 0;
        $labourTotal = 0;

        foreach ($this->lineItems as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $retail = (float) ($item['unit_retail_price'] ?? 0);
            $trade = (float) ($item['unit_trade_price'] ?? 0);

            $lineRetail = $qty * $retail;
            $lineTrade = $qty * $trade;

            $totalTrade += $lineTrade;

            if (($item['line_type'] ?? 'product') === 'labour') {
                $labourTotal += $lineRetail;
            } else {
                $totalRetail += $lineRetail;
            }
        }

        $grandTotal = $totalRetail + $labourTotal;

        return [
            'retail' => $totalRetail,
            'trade' => $totalTrade,
            'labour' => $labourTotal,
            'grand' => $grandTotal,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,sent,accepted,declined,expired'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $totals = $this->getTotalsProperty();

        $quoteData = [
            'customer_id' => $validated['customer_id'],
            'reference_number' => $validated['reference_number'] ?: 'Q-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4)),
            'status' => $validated['status'],
            'total_retail' => $totals['retail'],
            'total_trade' => $totals['trade'],
            'labour_total' => $totals['labour'],
            'grand_total' => $totals['grand'],
            'notes' => $validated['notes'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'enquiry_id' => $this->enquiryId,
            'staff_user_id' => auth()->id(),
        ];

        if ($this->quote) {
            $this->quote->update($quoteData);
            $quote = $this->quote;
            $quote->lineItems()->delete();
        } else {
            $quote = Quote::create($quoteData);
        }

        if ($this->enquiryId) {
            EnquiryActivityLog::create([
                'enquiry_id' => $this->enquiryId,
                'staff_user_id' => auth()->id(),
                'action' => 'quote_created',
                'description' => 'Quote created: '.($quote->reference_number ?? '#'.$quote->id),
                'metadata' => ['quote_id' => $quote->id, 'quote_reference' => $quote->reference_number],
            ]);
        }

        foreach ($this->lineItems as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $retail = (float) ($item['unit_retail_price'] ?? 0);
            $trade = (float) ($item['unit_trade_price'] ?? 0);

            $quote->lineItems()->create([
                'line_type' => $item['line_type'] ?? 'product',
                'product_id' => $item['product_id'] ?? null,
                'product_supplier_id' => $item['product_supplier_id'] ?? null,
                'description' => $item['description'] ?? '',
                'quantity' => (int) $qty,
                'unit_retail_price' => $retail,
                'unit_trade_price' => $trade,
                'line_total_retail' => $qty * $retail,
                'line_total_trade' => $qty * $trade,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $this->dispatch('notify', message: $this->quote ? 'Quote updated.' : 'Quote created.', type: 'success');
        $this->redirect(route('quotes.index'), navigate: true);
    }

    public function render()
    {
        $customers = Customer::orderBy('name')->get();
        $categories = ProductCategory::with(['products' => function ($query) {
            $query->where('is_active', true)->orderBy('name');
        }])->orderBy('name')->get();

        return view('livewire.quotes.quote-builder', compact('customers', 'categories'));
    }
}
