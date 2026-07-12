# Sample Quote — Preview Modal & List Totals

## Overview

Add two features to the Sample Quote list view:
1. **Column showing the total retail price** of each template
2. **Preview action** that opens a modal overlay listing items, quantities, and prices with CTA buttons

---

## Feature 1: Total Price Column in List

### What

Add a new column between "Line Items" and "Status" showing the grand total (retail subtotal + labour) formatted as `£X,XXX.XX`.

### Implementation

#### Model — `SampleQuote.php`

Add a computed `total` accessor so the value can be reused anywhere:

```php
public function getTotalAttribute(): float
{
    $items = $this->line_items ?? [];
    $retail = 0;
    $labour = 0;

    foreach ($items as $item) {
        $qty = (int) ($item['quantity'] ?? 1);
        $price = (float) ($item['unit_retail_price'] ?? 0);

        if (($item['line_type'] ?? '') === 'labour') {
            $labour += $price; // labour is a flat fee, not per-unit
        } else {
            $retail += $qty * $price;
        }
    }

    return $retail + $labour;
}
```

Also add `getRetailSubtotalAttribute()` and `getLabourTotalAttribute()` accessors for the modal breakdown — see Feature 2.

#### View — `sample-quote-list.blade.php`

Add `<th>Total</th>` after the "Line Items" column header and a corresponding `<td>` in each row:

```blade
<td class="px-6 py-4 text-right">
    <span class="font-medium text-slate-900">£{{ number_format($sample->total, 2) }}</span>
</td>
```

---

## Feature 2: Preview Modal

### What

A "Preview" icon button in each row that opens a modal overlay showing the template contents in full. The modal has two primary CTA buttons.

### Implementation

#### Livewire Component — `SampleQuoteList.php`

Add three new properties and two methods:

```php
public bool $showPreviewModal = false;
public ?SampleQuote $previewing = null;

public function preview(int $id): void
{
    $this->previewing = SampleQuote::findOrFail($id);
    $this->showPreviewModal = true;
}

public function closePreview(): void
{
    $this->showPreviewModal = false;
    $this->previewing = null;
}
```

#### View — `sample-quote-list.blade.php`

**a) Preview button** — Add to the actions column, before the "Clone to quote" link:

```blade
<button wire:click="preview({{ $sample->id }})" class="p-1.5 rounded-lg text-slate-400 hover:text-teal hover:bg-teal/10 transition-colors" title="Preview template">
    <x-lucide-eye class="w-4 h-4" />
</button>
```

**b) Modal** — Append after the table (outside the `@if` / table wrapper but inside the outer `<div>`). Reuse the existing `<x-modal>` Breeze component:

```blade
<x-modal name="preview-template" :show="$showPreviewModal" maxWidth="2xl" focusable>
    @if($previewing)
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 class="text-lg font-display font-semibold text-slate-900">{{ $previewing->name }}</h2>
                    @if($previewing->description)
                        <p class="mt-1 text-sm text-slate-500">{{ $previewing->description }}</p>
                    @endif
                </div>
                <button wire:click="closePreview" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <!-- Line Items Table -->
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-slate-700">Item</th>
                            <th class="px-4 py-2.5 text-center font-medium text-slate-700">Qty</th>
                            <th class="px-4 py-2.5 text-right font-medium text-slate-700">Unit Price</th>
                            <th class="px-4 py-2.5 text-right font-medium text-slate-700">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($previewing->line_items ?? [] as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="text-slate-900">{{ $item['description'] ?? '—' }}</div>
                                    @if(!empty($item['notes']))
                                        <div class="text-xs text-slate-400 mt-0.5">{{ $item['notes'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-slate-600">
                                    {{ $item['line_type'] === 'labour' ? '—' : ($item['quantity'] ?? 1) }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-600">
                                    £{{ number_format((float)($item['unit_retail_price'] ?? 0), 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900">
                                    @php
                                        $lineTotal = $item['line_type'] === 'labour'
                                            ? (float)($item['unit_retail_price'] ?? 0)
                                            : ((int)($item['quantity'] ?? 1)) * (float)($item['unit_retail_price'] ?? 0);
                                    @endphp
                                    £{{ number_format($lineTotal, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400 text-sm">
                                    This template has no line items.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Totals Breakdown -->
            <div class="mt-4 space-y-1 text-sm text-right">
                <div class="flex justify-end gap-8">
                    <span class="text-slate-500">Retail subtotal:</span>
                    <span class="font-medium text-slate-900 w-28 text-right">£{{ number_format($previewing->retail_subtotal, 2) }}</span>
                </div>
                <div class="flex justify-end gap-8">
                    <span class="text-slate-500">Labour:</span>
                    <span class="font-medium text-slate-900 w-28 text-right">£{{ number_format($previewing->labour_total, 2) }}</span>
                </div>
                <div class="flex justify-end gap-8 border-t border-slate-200 pt-1">
                    <span class="font-medium text-slate-700">Grand total:</span>
                    <span class="font-bold text-copper w-28 text-right">£{{ number_format($previewing->total, 2) }}</span>
                </div>
            </div>

            <!-- CTA Buttons -->
            <div class="mt-6 flex items-center justify-between gap-3 pt-4 border-t border-slate-200">
                <button wire:click="closePreview" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
                <div class="flex items-center gap-3">
                    <a href="{{ route('sample-quotes.edit', $previewing) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                        <x-lucide-pencil class="w-4 h-4" />
                        Edit template
                    </a>
                    <a href="{{ route('quotes.create-from-sample', $previewing) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                        <x-lucide-copy-plus class="w-4 h-4" />
                        Create quote from template
                    </a>
                </div>
            </div>
        </div>
    @endif
</x-modal>
```

#### Model Accessors — `SampleQuote.php`

Add these accessors for the modal breakdown (used by both the livewire view and potentially MCP tools):

```php
public function getRetailSubtotalAttribute(): float
{
    $total = 0;
    foreach ($this->line_items ?? [] as $item) {
        if (($item['line_type'] ?? '') !== 'labour') {
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_retail_price'] ?? 0);
            $total += $qty * $price;
        }
    }
    return $total;
}

public function getLabourTotalAttribute(): float
{
    $total = 0;
    foreach ($this->line_items ?? [] as $item) {
        if (($item['line_type'] ?? '') === 'labour') {
            $total += (float) ($item['unit_retail_price'] ?? 0);
        }
    }
    return $total;
}
```

---

## Files to Modify

| # | File | Change |
|---|------|--------|
| 1 | `app/Models/SampleQuote.php` | Add `total`, `retail_subtotal`, `labour_total` accessors |
| 2 | `app/Livewire/SampleQuotes/SampleQuoteList.php` | Add `showPreviewModal`, `previewing` properties + `preview()`, `closePreview()` methods |
| 3 | `resources/views/livewire/sample-quotes/sample-quote-list.blade.php` | Add Total column, preview button, modal markup |

---

## Trade Price Confidentiality Check

The modal only displays `unit_retail_price` — no trade prices are exposed. This is safe for all staff roles.

---

## Design Consistency

- **Modal size**: `maxWidth="2xl"` — wide enough for a comfortable item table
- **Colours**: Copper for primary CTAs, slate for secondary, teal for the preview button icon (matches existing icon colour scheme)
- **Typography**: `font-display` (Space Grotesk) for headings, consistent with rest of app
- **Price format**: `£{{ number_format($value, 2) }}` — matches existing app convention
- **Icons**: Lucide `eye` for preview, consistent with existing `copy`, `pencil`, `trash-2` icon set

---

## Testing

No tests exist for SampleQuote currently. Manual verification checklist:

1. List view shows correct total for templates with mixed product/labour/ad-hoc items
2. Preview button opens modal with correct line item data
3. Modal totals match the list view total
4. "Create quote from template" link navigates correctly
5. "Edit template" link navigates correctly
6. Close button and backdrop click dismiss the modal
7. Empty templates show "no line items" message in modal
8. Keyboard escape closes the modal
