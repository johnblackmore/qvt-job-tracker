<div class="max-w-4xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">{{ $product ? 'Edit Product' : 'Add Product' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $product ? 'Update product and supplier pricing' : 'Create a new product with supplier links' }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Product details --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-display font-semibold text-slate-900">Product Details</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="sku" class="block text-sm font-medium text-slate-700 mb-1.5">SKU <span class="text-red-500">*</span></label>
                    <input wire:model="sku" id="sku" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5 font-mono" placeholder="VIC-MPPT-100-30" />
                    @error('sku') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="category_id" class="block text-sm font-medium text-slate-700 mb-1.5">Category</label>
                    <select wire:model="category_id" id="category_id" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="">— No category —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Product name <span class="text-red-500">*</span></label>
                <input wire:model="name" id="name" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Victron SmartSolar MPPT 100/30" />
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                <textarea wire:model="description" id="description" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Product description and specifications..."></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="retail_price" class="block text-sm font-medium text-slate-700 mb-1.5">Retail price (£) <span class="text-red-500">*</span></label>
                    <input wire:model="retail_price" id="retail_price" type="number" step="0.01" min="0" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="199.99" />
                    @error('retail_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="stock_qty" class="block text-sm font-medium text-slate-700 mb-1.5">Stock quantity</label>
                    <input wire:model="stock_qty" id="stock_qty" type="number" min="0" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="5" />
                    @error('stock_qty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea wire:model="notes" id="notes" rows="2" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Internal notes about this product..."></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-slate-300 text-copper shadow-sm focus:ring-copper size-4" />
                <label for="is_active" class="text-sm text-slate-700">Active product</label>
            </div>
        </div>

        {{-- Supplier links --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-display font-semibold text-slate-900">Supplier Links</h2>
                <button type="button" wire:click="addSupplierLink" class="inline-flex items-center gap-1.5 text-sm font-medium text-copper hover:text-copper transition-colors">
                    <x-lucide-plus class="w-4 h-4" />
                    Add supplier
                </button>
            </div>

            @if(count($supplierLinks) > 0)
                <div class="space-y-4">
                    @foreach($supplierLinks as $index => $link)
                        <div class="p-4 rounded-lg border border-slate-200 bg-slate-50/50 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-slate-700">Supplier #{{ $index + 1 }}</h3>
                                <button type="button" wire:click="removeSupplierLink({{ $index }})" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <x-lucide-x class="w-4 h-4" />
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Supplier <span class="text-red-500">*</span></label>
                                    <select wire:model="supplierLinks.{{ $index }}.supplier_id" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2">
                                        <option value="">Select supplier...</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("supplierLinks.{$index}.supplier_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Trade price (£) <span class="text-red-500">*</span></label>
                                    <input wire:model="supplierLinks.{{ $index }}.trade_price" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2" placeholder="149.99" />
                                    @error("supplierLinks.{$index}.trade_price") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Supplier SKU</label>
                                    <input wire:model="supplierLinks.{{ $index }}.supplier_sku" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2" placeholder="Supplier's SKU code" />
                                    @error("supplierLinks.{$index}.supplier_sku") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Lead time (days)</label>
                                    <input wire:model="supplierLinks.{{ $index }}.lead_time_days" type="number" min="0" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2" placeholder="3" />
                                    @error("supplierLinks.{$index}.lead_time_days") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Product URL</label>
                                <input wire:model="supplierLinks.{{ $index }}.supplier_product_url" type="url" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2" placeholder="https://supplier.com/product" />
                                @error("supplierLinks.{$index}.supplier_product_url") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                                <input wire:model="supplierLinks.{{ $index }}.notes" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2" placeholder="Minimum order, bulk pricing, etc..." />
                            </div>

                            <div class="flex items-center gap-3">
                                <input wire:model="supplierLinks.{{ $index }}.is_preferred" id="pref_{{ $index }}" type="checkbox" class="rounded border-slate-300 text-copper shadow-sm focus:ring-copper size-4" />
                                <label for="pref_{{ $index }}" class="text-sm text-slate-700">Preferred supplier (default for quotes)</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-6 text-center border border-dashed border-slate-300 rounded-lg">
                    <p class="text-sm text-slate-500">No suppliers linked yet. Click "Add supplier" to link trade pricing.</p>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $product ? 'Save Changes' : 'Create Product' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('products.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
