<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Products</h1>
            <p class="mt-1 text-sm text-slate-500">Component library with retail and trade pricing</p>
        </div>
        <a href="{{ route('products.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
            <x-lucide-package-plus class="w-4 h-4" />
            Add Product
        </a>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-3">
        <div class="relative max-w-md flex-1">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name or SKU..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
        </div>
        <select wire:model.live="category" class="rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2.5">
            <option value="">All categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($products->count() > 0)
            {{-- Mobile card view --}}
            <div class="block md:hidden divide-y divide-slate-100">
                @foreach($products as $product)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('products.show', $product) }}" wire:navigate class="font-medium text-slate-900 hover:text-copper transition-colors">
                                    {{ $product->name }}
                                </a>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $product->sku }}</div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <a href="{{ route('products.edit', $product) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                    <x-lucide-pencil class="w-4 h-4" />
                                </a>
                                <button wire:click="delete({{ $product->id }})" wire:confirm="Delete this product? This will remove all supplier links." class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            @if($product->category)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $product->category->name }}
                                </span>
                            @endif
                            <span class="font-medium text-slate-900">£{{ number_format($product->retail_price, 2) }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center rounded-full {{ $product->stock_qty > 0 ? 'bg-teal/10 text-teal-dark' : 'bg-red-50 text-red-700' }} px-2.5 py-0.5 font-medium">
                                Stock: {{ $product->stock_qty }}
                            </span>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 font-medium text-slate-600">
                                {{ $product->suppliers->count() }} supplier(s)
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop table view --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Product</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Category</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Retail</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Stock</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-center">Suppliers</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($products as $product)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <a href="{{ route('products.show', $product) }}" wire:navigate class="font-medium text-slate-900 hover:text-copper transition-colors">
                                        {{ $product->name }}
                                    </a>
                                    <div class="text-xs text-slate-400 mt-0.5">{{ $product->sku }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($product->category)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $product->category->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-slate-900">
                                    £{{ number_format($product->retail_price, 2) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="inline-flex items-center rounded-full {{ $product->stock_qty > 0 ? 'bg-teal/10 text-teal-dark' : 'bg-red-50 text-red-700' }} px-2.5 py-0.5 text-xs font-medium">
                                        {{ $product->stock_qty }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $product->suppliers->count() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('products.edit', $product) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $product->id }})" wire:confirm="Delete this product? This will remove all supplier links." class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $products->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-package class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No products yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Add your first product to start building your component library with trade pricing.</p>
            </div>
        @endif
    </div>
</div>
