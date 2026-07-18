<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Suppliers</h1>
            <p class="mt-1 text-sm text-slate-500">Manage your component suppliers and trade pricing</p>
        </div>
        <a href="{{ route('suppliers.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
            <x-lucide-building-2 class="w-4 h-4" />
            Add Supplier
        </a>
    </div>

    <div class="mb-6">
        <div class="relative max-w-md">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search suppliers..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($suppliers->count() > 0)
            {{-- Mobile card view --}}
            <div class="block md:hidden divide-y divide-slate-100">
                @foreach($suppliers as $supplier)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-slate-900">{{ $supplier->name }}</div>
                                @if($supplier->website)
                                    <a href="{{ $supplier->website }}" target="_blank" class="text-xs text-copper hover:underline">{{ $supplier->website }}</a>
                                @endif
                            </div>
                            <button wire:click="toggleActive({{ $supplier->id }})" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium shrink-0 {{ $supplier->is_active ? 'bg-teal/10 text-teal-dark border border-teal/20' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </div>
                        @if($supplier->contact_name || $supplier->email || $supplier->phone)
                            <div class="text-xs text-slate-500 space-y-0.5">
                                @if($supplier->contact_name)<div>{{ $supplier->contact_name }}</div>@endif
                                @if($supplier->email)<div>{{ $supplier->email }}</div>@endif
                                @if($supplier->phone)<div>{{ $supplier->phone }}</div>@endif
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                {{ $supplier->products_count }} products
                            </span>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('suppliers.edit', $supplier) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                    <x-lucide-pencil class="w-4 h-4" />
                                </a>
                                <button wire:click="delete({{ $supplier->id }})" wire:confirm="Delete this supplier? This will remove all product links." class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop table view --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Name</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Contact</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Products</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($suppliers as $supplier)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900">{{ $supplier->name }}</div>
                                    @if($supplier->website)
                                        <a href="{{ $supplier->website }}" target="_blank" class="text-xs text-copper hover:underline">{{ $supplier->website }}</a>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-0.5">
                                        @if($supplier->contact_name)
                                            <div class="text-slate-900 text-xs">{{ $supplier->contact_name }}</div>
                                        @endif
                                        @if($supplier->email)
                                            <div class="text-slate-500 text-xs">{{ $supplier->email }}</div>
                                        @endif
                                        @if($supplier->phone)
                                            <div class="text-slate-500 text-xs">{{ $supplier->phone }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $supplier->products_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button wire:click="toggleActive({{ $supplier->id }})" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $supplier->is_active ? 'bg-teal/10 text-teal-dark border border-teal/20' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                        {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('suppliers.edit', $supplier) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $supplier->id }})" wire:confirm="Delete this supplier? This will remove all product links." class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
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
                {{ $suppliers->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-building-2 class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No suppliers yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Add your first supplier to start tracking trade prices and product links.</p>
            </div>
        @endif
    </div>
</div>
