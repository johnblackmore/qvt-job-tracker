<div>
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('orders.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-emerald-600 transition-colors">Orders</a>
            <x-lucide-chevron-right class="w-4 h-4 text-slate-400" />
            <span class="text-sm text-slate-900 font-medium font-mono">{{ $order->reference_number }}</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Order {{ $order->reference_number }}</h1>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-500">
                    <span>{{ $order->customer->name }}</span>
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
                    @if($order->scheduled_date)
                        <span class="text-xs">Scheduled {{ $order->scheduled_date->format('d M Y') }}</span>
                    @endif
                    @if($order->completed_at)
                        <span class="text-xs text-emerald-600">Completed {{ $order->completed_at->format('d M Y') }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('orders.edit', $order) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <x-lucide-pencil class="w-4 h-4" />
                    Edit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            {{-- Financial summary --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-4">Financial Summary</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Total amount</p>
                        <p class="text-xl font-bold text-slate-900 mt-1">£{{ number_format($order->total_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Deposit required</p>
                        <p class="text-xl font-bold text-slate-900 mt-1">£{{ number_format($order->deposit_required, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Balance due</p>
                        <p class="text-xl font-bold {{ $order->balance_due <= 0 ? 'text-emerald-600' : 'text-slate-900' }} mt-1">£{{ number_format($order->balance_due, 2) }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-600">Deposit progress</span>
                        <span class="text-xs font-semibold text-slate-900">{{ $order->deposit_percent }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-emerald-500 h-2.5 rounded-full transition-all" style="width: {{ min($order->deposit_percent, 100) }}%"></div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1.5">
                        £{{ number_format($order->deposit_paid, 2) }} paid of £{{ number_format($order->deposit_required, 2) }} required
                    </p>
                </div>
            </div>

            @if($order->quote)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">Original Quote</h2>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $order->quote->reference_number }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">£{{ number_format($order->quote->grand_total, 2) }} — {{ $order->quote->status }}</p>
                        </div>
                        <a href="{{ route('quotes.show', $order->quote) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
                            <x-lucide-arrow-right class="w-4 h-4" />
                            View quote
                        </a>
                    </div>
                </div>
            @endif

            @if($order->notes)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">Internal Notes</h2>
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $order->notes }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Customer card --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Customer</h2>
                <p class="text-sm font-medium text-slate-900">{{ $order->customer->name }}</p>
                @if($order->customer->email)
                    <p class="text-xs text-slate-500 mt-1">{{ $order->customer->email }}</p>
                @endif
                @if($order->customer->phone)
                    <p class="text-xs text-slate-500">{{ $order->customer->phone }}</p>
                @endif
            </div>

            {{-- Schedule card --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Schedule</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Installation date</span>
                        <span class="text-sm font-medium text-slate-900">{{ $order->scheduled_date?->format('d M Y') ?? 'Not scheduled' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Created</span>
                        <span class="text-sm font-medium text-slate-900">{{ $order->created_at->format('d M Y') }}</span>
                    </div>
                    @if($order->completed_at)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500">Completed</span>
                            <span class="text-sm font-medium text-emerald-700">{{ $order->completed_at->format('d M Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Staff card --}}
            @if($order->staff)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">Assigned to</h2>
                    <p class="text-sm font-medium text-slate-900">{{ $order->staff->name }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
