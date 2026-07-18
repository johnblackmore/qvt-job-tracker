<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">Supplier Orders</h1>
            <p class="mt-1 text-sm text-slate-500">Track orders and invoices from your suppliers</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('expenses.export', ['type' => 'supplier_orders']) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                <x-lucide-file-down class="w-4 h-4" />
                Export CSV
            </a>
            <a href="{{ route('expenses.supplier-orders.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                <x-lucide-plus class="w-4 h-4" />
                New Supplier Order
            </a>
        </div>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1 max-w-md">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by reference, invoice number or supplier..." class="w-full rounded-lg border-slate-300 text-ink placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
        </div>
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="ordered">Ordered</option>
            <option value="received">Received</option>
            <option value="partially_received">Partially Received</option>
            <option value="paid">Paid</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <input wire:model.live="dateFrom" type="date" placeholder="From" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
        <input wire:model.live="dateTo" type="date" placeholder="To" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
        @if($search || $status || $dateFrom || $dateTo)
            <button wire:click="clearFilters" class="text-sm text-copper hover:underline">Clear filters</button>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($orders->count() > 0)
            {{-- Mobile card view --}}
            <div class="block md:hidden divide-y divide-slate-100">
                @foreach($orders as $order)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('expenses.supplier-orders.show', $order->id) }}" wire:navigate class="font-medium text-copper hover:underline">
                                    {{ $order->reference_number }}
                                </a>
                                <div class="text-sm text-slate-900 mt-0.5">{{ $order->supplier?->name ?? '—' }}</div>
                            </div>
                            @php
                                $soStatusStyles = [
                                    'draft' => 'bg-slate-100 text-slate-600',
                                    'ordered' => 'bg-blue-50 text-blue-600',
                                    'received' => 'bg-teal/10 text-teal-dark',
                                    'partially_received' => 'bg-amber-50 text-amber-600',
                                    'paid' => 'bg-teal/10 text-teal-dark',
                                    'cancelled' => 'bg-red-50 text-red-600',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium shrink-0 {{ $soStatusStyles[$order->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ str_replace('_', ' ', ucfirst($order->status)) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="text-xs text-slate-500">
                                @if($order->invoice_number)
                                    <div>Invoice: {{ $order->invoice_number }}</div>
                                @endif
                                <div>{{ $order->order_date->format('j M Y') }}</div>
                            </div>
                            <span class="font-medium text-slate-900">£{{ number_format($order->total_amount, 2) }}</span>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <a href="{{ route('expenses.supplier-orders.show', $order->id) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                <x-lucide-eye class="w-4 h-4" />
                            </a>
                            <a href="{{ route('expenses.supplier-orders.edit', $order->id) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                <x-lucide-pencil class="w-4 h-4" />
                            </a>
                            <button wire:click="delete({{ $order->id }})" wire:confirm="Delete this supplier order?" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop table view --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Reference</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Supplier</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Invoice</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Order Date</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Total</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($orders as $order)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <a href="{{ route('expenses.supplier-orders.show', $order->id) }}" wire:navigate class="font-medium text-copper hover:underline">
                                        {{ $order->reference_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-slate-900">
                                    {{ $order->supplier?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-slate-500 text-xs">
                                    {{ $order->invoice_number ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    {{ $order->order_date->format('j M Y') }}
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-900">
                                    £{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusStyles = [
                                            'draft' => 'bg-slate-100 text-slate-600',
                                            'ordered' => 'bg-blue-50 text-blue-600',
                                            'received' => 'bg-teal/10 text-teal-dark',
                                            'partially_received' => 'bg-amber-50 text-amber-600',
                                            'paid' => 'bg-teal/10 text-teal-dark',
                                            'cancelled' => 'bg-red-50 text-red-600',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusStyles[$order->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ str_replace('_', ' ', ucfirst($order->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('expenses.supplier-orders.show', $order->id) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-eye class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('expenses.supplier-orders.edit', $order->id) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $order->id }})" wire:confirm="Delete this supplier order?" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
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
                {{ $orders->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-truck class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-ink">No supplier orders yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Record your first supplier order to start tracking stock purchases and invoices.</p>
                <a href="{{ route('expenses.supplier-orders.create') }}" wire:navigate class="mt-4 inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    <x-lucide-plus class="w-4 h-4" />
                    New Supplier Order
                </a>
            </div>
        @endif
    </div>
</div>
