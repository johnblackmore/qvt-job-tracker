<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Orders</h1>
            <p class="mt-1 text-sm text-slate-500">Manage customer installations and jobs</p>
        </div>
        <a href="{{ route('orders.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
            <x-lucide-clipboard-plus class="w-4 h-4" />
            New Order
        </a>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-3">
        <div class="relative max-w-md flex-1">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by reference or customer..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm pl-9 pr-4 py-2.5" />
        </div>
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="deposit_paid">Deposit Paid</option>
            <option value="scheduled">Scheduled</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($orders->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Reference</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Customer</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Total</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Deposit</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Scheduled</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($orders as $order)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <a href="{{ route('orders.show', $order) }}" wire:navigate class="font-medium text-slate-900 hover:text-emerald-600 transition-colors font-mono text-xs">
                                        {{ $order->reference_number }}
                                    </a>
                                    @if($order->quote)
                                        <div class="text-[10px] text-slate-400 mt-0.5">from {{ $order->quote->reference_number }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $order->customer->name }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $order->status === 'pending' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                                        {{ $order->status === 'deposit_paid' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                        {{ $order->status === 'scheduled' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : '' }}
                                        {{ $order->status === 'in_progress' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                        {{ $order->status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : '' }}
                                        {{ $order->status === 'cancelled' ? 'bg-red-50 text-red-700 border border-red-200' : '' }}
                                    ">
                                        {{ ucwords(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-slate-900">
                                    £{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="text-xs text-slate-600">
                                        £{{ number_format($order->deposit_paid, 2) }} / £{{ number_format($order->deposit_required, 2) }}
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1.5 overflow-hidden">
                                        <div class="bg-emerald-500 h-1.5 rounded-full transition-all" style="width: {{ min($order->deposit_percent, 100) }}%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    {{ $order->scheduled_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('orders.edit', $order) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $order->id }})" wire:confirm="Delete this order?" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
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
                    <x-lucide-clipboard-list class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No orders yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Convert an accepted quote or create a new order to start tracking installations.</p>
            </div>
        @endif
    </div>
</div>
