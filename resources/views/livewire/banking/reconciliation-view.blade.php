<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Reconciliation</h1>
            <p class="mt-1 text-sm text-slate-500">Match bank transactions to order payments</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400">
                {{ $summary['match_rate'] }}% matched
                ({{ $summary['matched_transactions'] }}/{{ $summary['total_transactions'] }})
            </span>
            <button wire:click="runAutoMatch" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors" wire:loading.attr="disabled">
                <x-lucide-wand-2 class="w-4 h-4" />
                Run Auto-Match
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Unmatched Transactions</p>
            <p class="mt-1 text-2xl font-display font-bold text-amber-700">{{ $summary['unmatched_transactions'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Unlinked Payments</p>
            <p class="mt-1 text-2xl font-display font-bold text-slate-900">{{ $summary['unlinked_payments'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Matched</p>
            <p class="mt-1 text-2xl font-display font-bold text-teal">{{ $summary['matched_transactions'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="px-4 py-4 border-b border-slate-200 flex items-center gap-2">
                <x-lucide-arrow-left-from-line class="w-4 h-4 text-amber-700" />
                <h2 class="text-sm font-semibold text-slate-900">Unmatched Bank Transactions</h2>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600">{{ $unmatchedTransactions->count() }}</span>
            </div>
            <div class="p-4">
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($unmatchedTransactions as $txn)
                        <div
                            wire:click="selectTransaction({{ $txn->id }})"
                            class="p-3 rounded-lg border text-sm cursor-pointer transition-colors {{ $selectedTransactionId === $txn->id ? 'border-copper bg-copper/5 ring-1 ring-copper' : 'border-slate-200 hover:border-slate-300' }}"
                        >
                            <div class="flex justify-between items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900 truncate">{{ $txn->description }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $txn->merchant_name ?? $txn->bankAccount?->name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $txn->transaction_date->format('j M Y') }}</p>
                                </div>
                                <div class="text-right ml-3 shrink-0">
                                    <p class="font-mono font-medium text-slate-700">-&pound;{{ number_format(abs($txn->amount), 2) }}</p>
                                    @if($txn->expense_category)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 mt-1">{{ str_replace('_', ' ', $txn->expense_category) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 text-center py-8">No unmatched transactions.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="px-4 py-4 border-b border-slate-200 flex items-center gap-2">
                <x-lucide-arrow-right-from-line class="w-4 h-4 text-teal" />
                <h2 class="text-sm font-semibold text-slate-900">Unlinked Order Payments</h2>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600">{{ $unmatchedPayments->count() }}</span>
            </div>
            <div class="p-4">
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($unmatchedPayments as $payment)
                        <div
                            wire:click="selectPayment({{ $payment->id }})"
                            class="p-3 rounded-lg border text-sm cursor-pointer transition-colors {{ $selectedPaymentId === $payment->id ? 'border-copper bg-copper/5 ring-1 ring-copper' : 'border-slate-200 hover:border-slate-300' }}"
                        >
                            <div class="flex justify-between items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900">{{ $payment->order?->reference_number ?? 'Order #'.$payment->order_id }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $payment->order?->customer?->name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $payment->paid_at->format('j M Y') }}</p>
                                </div>
                                <div class="text-right ml-3 shrink-0">
                                    <p class="font-mono font-medium text-teal">&pound;{{ number_format($payment->amount, 2) }}</p>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 mt-1">{{ str_replace('_', ' ', $payment->method) }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 text-center py-8">No unlinked payments.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if($selectedTransactionId && $selectedPaymentId)
        <div class="bg-copper/5 border border-copper/20 rounded-xl shadow-sm mb-6">
            <div class="p-4 flex items-center justify-between">
                <div class="flex items-center gap-3 text-sm">
                    <x-lucide-link-2 class="w-5 h-5 text-copper" />
                    <span class="text-slate-700">
                        Link selected transaction to selected payment?
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="$set('selectedTransactionId', null)" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 transition-colors">Cancel</button>
                    <button wire:click="linkSelected" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                        <x-lucide-link-2 class="w-4 h-4" />
                        Link
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($matchedTransactions->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-4 border-b border-slate-200 flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-4 h-4 text-teal" />
                <h2 class="text-sm font-semibold text-slate-900">Recently Matched</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Transaction</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Payment</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Order</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Amount</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Matched</th>
                            <th class="px-6 py-3 font-medium text-slate-700"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($matchedTransactions as $txn)
                            <tr class="hover:bg-slate-50 transition-colors text-sm">
                                <td class="px-6 py-4 max-w-xs truncate text-slate-600">{{ $txn->description }}</td>
                                <td class="px-6 py-4 text-slate-600">&pound;{{ number_format($txn->matchedPayment?->amount ?? 0, 2) }}</td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('orders.show', $txn->matchedPayment?->order_id) }}" wire:navigate class="text-copper hover:underline">
                                        {{ $txn->matchedPayment?->order?->reference_number ?? '#' . $txn->matchedPayment?->order_id }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-600">&pound;{{ number_format(abs($txn->amount), 2) }}</td>
                                <td class="px-6 py-4 text-xs text-slate-400">{{ $txn->updated_at->diffForHumans() }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button wire:click="unlink({{ $txn->id }})" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Unlink">
                                        <x-lucide-unlink-2 class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
