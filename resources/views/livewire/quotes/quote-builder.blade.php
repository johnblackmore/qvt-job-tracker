<div class="max-w-6xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">
            {{ $quote ? 'Edit Quote' : ($sampleQuote ? 'Clone from Template' : ($sourceQuoteId ? 'Clone from Quote' : 'Create Quote')) }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $quote ? 'Update quote details' : ($sampleQuote ? 'Customise this template for a customer' : ($sourceQuoteId ? 'Customise this clone before saving' : 'Build a new customer quote')) }}
        </p>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Quote details --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-display font-semibold text-slate-900">Quote Details</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-slate-700 mb-1.5">Customer <span class="text-red-500">*</span></label>
                    <select wire:model="customer_id" id="customer_id" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="">Select customer...</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="reference_number" class="block text-sm font-medium text-slate-700 mb-1.5">Reference</label>
                    <input wire:model="reference_number" id="reference_number" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5 font-mono" placeholder="Auto-generated if left blank" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                    <select wire:model="status" id="status" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="accepted">Accepted</option>
                        <option value="declined">Declined</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div>
                    <label for="valid_until" class="block text-sm font-medium text-slate-700 mb-1.5">Valid until</label>
                    <input wire:model="valid_until" id="valid_until" type="date" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes (internal)</label>
                <textarea wire:model="notes" id="notes" rows="2" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Internal notes about this quote..."></textarea>
            </div>
        </div>

        {{-- Line items builder --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Product picker --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 space-y-4">
                    <h2 class="text-sm font-semibold text-slate-900">Pick from Catalogue</h2>

                    <div class="space-y-1 max-h-[400px] overflow-y-auto">
                        @foreach($categories as $category)
                            <div>
                                <button type="button" wire:click="$set('selectedCategory', '{{ $selectedCategory === $category->id ? '' : $category->id }}')" class="flex items-center justify-between w-full px-2 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 rounded transition-colors">
                                    <span>{{ $category->name }}</span>
                                    <x-lucide-chevron-down class="w-3 h-3 {{ $selectedCategory == $category->id ? 'rotate-180' : '' }} transition-transform" />
                                </button>
                                @if($selectedCategory == $category->id)
                                    <div class="ml-3 space-y-0.5">
                                        @foreach($category->products as $product)
                                            <button type="button" wire:click="addProductLine({{ $product->id }})" class="flex items-center gap-2 w-full px-2 py-1.5 text-xs text-slate-600 hover:bg-copper/10 hover:text-copper rounded transition-colors text-left">
                                                <x-lucide-plus class="w-3 h-3 shrink-0" />
                                                <span class="truncate">{{ $product->name }}</span>
                                                <span class="text-slate-400 shrink-0">£{{ number_format($product->retail_price, 2) }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-200 pt-3 space-y-2">
                        <button type="button" wire:click="addLabourLine" class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                            <x-lucide-wrench class="w-3.5 h-3.5" />
                            Add Labour Line
                        </button>
                        <button type="button" wire:click="addAdHocLine" class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                            <x-lucide-plus class="w-3.5 h-3.5" />
                            Add Custom Item
                        </button>
                    </div>
                </div>
            </div>

            {{-- Line items list --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">Line Items ({{ count($lineItems) }})</h2>
                    </div>

                    @if(count($lineItems) > 0)
                        <div class="space-y-3">
                            @foreach($lineItems as $index => $item)
                                <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                            {{ $item['line_type'] === 'product' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                            {{ $item['line_type'] === 'labour' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                            {{ $item['line_type'] === 'ad_hoc' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                                        ">
                                            {{ str_replace('_', ' ', $item['line_type']) }}
                                        </span>
                                        <button type="button" wire:click="removeLineItem({{ $index }})" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                            <x-lucide-x class="w-4 h-4" />
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                                            <input wire:model="lineItems.{{ $index }}.description" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Qty</label>
                                            <input wire:model="lineItems.{{ $index }}.quantity" type="number" min="1" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Retail (£)</label>
                                            <input wire:model="lineItems.{{ $index }}.unit_retail_price" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5" />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                                        <input wire:model="lineItems.{{ $index }}.notes" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-1.5" placeholder="Optional notes..." />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center border border-dashed border-slate-300 rounded-lg">
                            <p class="text-sm text-slate-500">No line items yet. Use the catalogue picker to add products.</p>
                        </div>
                    @endif
                </div>

                {{-- Totals --}}
                @php
                    $totals = $this->getTotalsProperty();
                @endphp
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Products subtotal</span>
                        <span class="text-sm font-medium text-slate-900">£{{ number_format($totals['retail'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Labour</span>
                        <span class="text-sm font-medium text-slate-900">£{{ number_format($totals['labour'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-base font-semibold text-slate-900">Grand total</span>
                        <span class="text-base font-bold text-copper-light">£{{ number_format($totals['grand'], 2) }}</span>
                    </div>
                    <div class="mt-2 pt-2 border-t border-slate-100 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400">Trade cost (excl. VAT)</span>
                            <span class="text-xs text-slate-400">£{{ number_format($totals['trade'], 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400">True cost (incl. VAT paid)</span>
                            <span class="text-xs text-slate-500 font-medium">£{{ number_format($totals['cost'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $quote ? 'Save Changes' : 'Create Quote' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('quotes.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
