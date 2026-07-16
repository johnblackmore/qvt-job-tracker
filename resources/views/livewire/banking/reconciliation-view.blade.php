<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-display font-bold text-slate-800">Reconciliation</h1>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400">
                {{ $summary['match_rate'] }}% matched
                ({{ $summary['matched_transactions'] }}/{{ $summary['total_transactions'] }})
            </span>
            <button wire:click="runAutoMatch" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                <x-lucide-wand-2 class="w-4 h-4" />
                Run Auto-Match
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="stat bg-white border border-slate-200 shadow-sm rounded-lg p-4">
            <div class="stat-title text-xs text-slate-500">Unmatched Transactions</div>
            <div class="stat-value text-2xl font-display font-bold text-amber">{{ $summary['unmatched_transactions'] }}</div>
        </div>
        <div class="stat bg-white border border-slate-200 shadow-sm rounded-lg p-4">
            <div class="stat-title text-xs text-slate-500">Unlinked Payments</div>
            <div class="stat-value text-2xl font-display font-bold text-slate-700">{{ $summary['unlinked_payments'] }}</div>
        </div>
        <div class="stat bg-white border border-slate-200 shadow-sm rounded-lg p-4">
            <div class="stat-title text-xs text-slate-500">Matched</div>
            <div class="stat-value text-2xl font-display font-bold text-teal">{{ $summary['matched_transactions'] }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="card bg-white border border-slate-200 shadow-sm">
            <div class="card-body p-4">
                <h3 class="font-display font-semibold text-slate-700 mb-3 flex items-center gap-2">
                    <x-lucide-arrow-left-from-line class="w-4 h-4 text-amber" />
                    Unmatched Bank Transactions
                    <span class="badge badge-sm bg-slate-100 text-slate-500">{{ $unmatchedTransactions->count() }}</span>
                </h3>

                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($unmatchedTransactions as $txn)
                        <div
                            wire:click="selectTransaction({{ $txn->id }})"
                            class="p-3 rounded-lg border text-sm cursor-pointer transition-colors {{ $selectedTransactionId === $txn->id ? 'border-copper bg-copper/5 ring-1 ring-copper' : 'border-slate-200 hover:border-slate-300' }}"
                        >
                            <div class="flex justify-between items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-800 truncate">{{ $txn->description }}</p>
                                    <p class="text-xs text-slate-400">{{ $txn->merchant_name ?? $txn->bankAccount?->name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $txn->transaction_date->format('j M Y') }}</p>
                                </div>
                                <div class="text-right ml-3 shrink-0">
                                    <p class="font-mono font-medium text-slate-700">-£{{ number_format(abs($txn->amount), 2) }}</p>
                                    @if($txn->expense_category)
                                        <span class="badge badge-xs bg-slate-100 text-slate-500 mt-1">{{ str_replace('_', ' ', $txn->expense_category) }}</span>
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

        <div class="card bg-white border border-slate-200 shadow-sm">
            <div class="card-body p-4">
                <h3 class="font-display font-semibold text-slate-700 mb-3 flex items-center gap-2">
                    <x-lucide-arrow-right-from-line class="w-4 h-4 text-teal" />
                    Unlinked Order Payments
                    <span class="badge badge-sm bg-slate-100 text-slate-500">{{ $unmatchedPayments->count() }}</span>
                </h3>

                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($unmatchedPayments as $payment)
                        <div
                            wire:click="selectPayment({{ $payment->id }})"
                            class="p-3 rounded-lg border text-sm cursor-pointer transition-colors {{ $selectedPaymentId === $payment->id ? 'border-copper bg-copper/5 ring-1 ring-copper' : 'border-slate-200 hover:border-slate-300' }}"
                        >
                            <div class="flex justify-between items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-800">{{ $payment->order?->reference_number ?? 'Order #'.$payment->order_id }}</p>
                                    <p class="text-xs text-slate-400">{{ $payment->order?->customer?->name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $payment->paid_at->format('j M Y') }}</p>
                                </div>
                                <div class="text-right ml-3 shrink-0">
                                    <p class="font-mono font-medium text-teal">£{{ number_format($payment->amount, 2) }}</p>
                                    <span class="badge badge-xs bg-slate-100 text-slate-500 mt-1">{{ str_replace('_', ' ', $payment->method) }}</span>
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
        <div class="card bg-copper/5 border border-copper/20 shadow-sm mb-6">
            <div class="card-body p-4 flex items-center justify-between">
                <div class="flex items-center gap-3 text-sm">
                    <x-lucide-link-2 class="w-5 h-5 text-copper" />
                    <span class="text-slate-700">
                        Link selected transaction to selected payment?
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="$set('selectedTransactionId', null)" class="btn btn-ghost btn-xs">Cancel</button>
                    <button wire:click="linkSelected" class="btn btn-primary btn-sm">
                        <x-lucide-link-2 class="w-4 h-4" />
                        Link
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($matchedTransactions->isNotEmpty())
        <div class="card bg-white border border-slate-200 shadow-sm">
            <div class="card-body p-4">
                <h3 class="font-display font-semibold text-slate-700 mb-3 flex items-center gap-2">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-teal" />
                    Recently Matched
                </h3>

                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-slate-500 text-xs uppercase">
                                <th>Transaction</th>
                                <th>Payment</th>
                                <th>Order</th>
                                <th>Amount</th>
                                <th>Matched</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matchedTransactions as $txn)
                                <tr class="text-sm text-slate-600">
                                    <td class="max-w-xs truncate">{{ $txn->description }}</td>
                                    <td>£{{ number_format($txn->matchedPayment?->amount ?? 0, 2) }}</td>
                                    <td>
                                        <a href="{{ route('orders.show', $txn->matchedPayment?->order_id) }}" wire:navigate class="text-copper hover:underline">
                                            {{ $txn->matchedPayment?->order?->reference_number ?? '#' . $txn->matchedPayment?->order_id }}
                                        </a>
                                    </td>
                                    <td class="font-mono">£{{ number_format(abs($txn->amount), 2) }}</td>
                                    <td class="text-xs text-slate-400">{{ $txn->updated_at->diffForHumans() }}</td>
                                    <td>
                                        <button wire:click="unlink({{ $txn->id }})" class="btn btn-ghost btn-xs text-slate-400 hover:text-red-500" title="Unlink">
                                            <x-lucide-unlink-2 class="w-3.5 h-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
