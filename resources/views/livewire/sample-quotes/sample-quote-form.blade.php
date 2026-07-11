<div class="max-w-6xl">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ $sampleQuote ? 'Edit Sample Quote' : 'Create Sample Quote' }}</h1>
        <p class="mt-1 text-sm text-slate-500">Build a reusable quote template</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Template details --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Template name <span class="text-red-500">*</span></label>
                <input wire:model="name" id="name" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="e.g. Solar + Battery Package" />
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                <textarea wire:model="description" id="description" rows="2" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="What this template is for..."></textarea>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Internal notes</label>
                <textarea wire:model="notes" id="notes" rows="2" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="Notes for staff when using this template..."></textarea>
            </div>

            <div class="flex items-center gap-3">
                <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 size-4" />
                <label for="is_active" class="text-sm text-slate-700">Active template</label>
            </div>
        </div>

        {{-- Line items --}}
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
                                            <button type="button" wire:click="addProductLine({{ $product->id }})" class="flex items-center gap-2 w-full px-2 py-1.5 text-xs text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 rounded transition-colors text-left">
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
            <div class="lg:col-span-2">
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
                                            <input wire:model="lineItems.{{ $index }}.description" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-1.5" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Qty</label>
                                            <input wire:model="lineItems.{{ $index }}.quantity" type="number" min="1" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-1.5" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Retail (£)</label>
                                            <input wire:model="lineItems.{{ $index }}.unit_retail_price" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-1.5" />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                                        <input wire:model="lineItems.{{ $index }}.notes" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-1.5" placeholder="Optional notes..." />
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
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $sampleQuote ? 'Save Changes' : 'Create Template' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('sample-quotes.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
