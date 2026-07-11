<div>
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('products.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-emerald-600 transition-colors">Products</a>
            <x-lucide-chevron-right class="w-4 h-4 text-slate-400" />
            <span class="text-sm text-slate-900 font-medium">{{ $product->name }}</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ $product->name }}</h1>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-500">
                    <span class="font-mono">{{ $product->sku }}</span>
                    @if($product->category)
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $product->category->name }}</span>
                    @endif
                    <span class="inline-flex items-center rounded-full {{ $product->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }} px-2.5 py-0.5 text-xs font-medium border {{ $product->is_active ? 'border-emerald-200' : 'border-slate-200' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('products.edit', $product) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <x-lucide-pencil class="w-4 h-4" />
                    Edit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            @if($product->description)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">Description</h2>
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $product->description }}</p>
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-base font-semibold text-slate-900">Supplier Pricing</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Trade prices are internal-only and never shown to customers</p>
                </div>
                @if($product->suppliers->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach($product->suppliers as $supplier)
                            <div class="px-6 py-4 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                                            <x-lucide-building-2 class="w-4 h-4 text-emerald-600" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-slate-900">{{ $supplier->name }}</span>
                                                @if($supplier->pivot->is_preferred)
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-[10px] font-semibold uppercase">Preferred</span>
                                                @endif
                                            </div>
                                            @if($supplier->pivot->supplier_sku)
                                                <p class="text-xs text-slate-500 mt-0.5">Supplier SKU: {{ $supplier->pivot->supplier_sku }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-slate-900">£{{ number_format($supplier->pivot->trade_price, 2) }}</p>
                                        <p class="text-xs text-slate-500">trade price</p>
                                    </div>
                                </div>
                                @if($supplier->pivot->lead_time_days)
                                    <p class="text-xs text-slate-500 mt-2 ml-11">Lead time: {{ $supplier->pivot->lead_time_days }} days</p>
                                @endif
                                @if($supplier->pivot->supplier_product_url)
                                    <a href="{{ $supplier->pivot->supplier_product_url }}" target="_blank" class="text-xs text-emerald-600 hover:underline mt-1 ml-11 inline-flex items-center gap-1">
                                        <x-lucide-external-link class="w-3 h-3" />
                                        View on supplier website
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <p class="text-sm text-slate-500">No supplier links configured yet.</p>
                        <a href="{{ route('products.edit', $product) }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-emerald-600 hover:text-emerald-700">
                            <x-lucide-plus class="w-4 h-4" />
                            Add supplier pricing
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Pricing Summary</h2>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-500">Retail price</span>
                    <span class="text-sm font-medium text-slate-900">£{{ number_format($product->retail_price, 2) }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-500">Stock</span>
                    <span class="text-sm font-medium {{ $product->stock_qty > 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $product->stock_qty }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-500">Suppliers</span>
                    <span class="text-sm font-medium text-slate-900">{{ $product->suppliers->count() }}</span>
                </div>
            </div>

            @if($product->notes)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">Notes</h2>
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $product->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
