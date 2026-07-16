<div>
    <div class="mb-6">
        <h1 class="text-2xl font-display font-bold text-slate-800">Bank Transactions</h1>
    </div>

    <div class="card bg-white border border-slate-200 shadow-sm mb-6">
        <div class="card-body p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div>
                    <label class="label-text text-xs text-slate-500 mb-1 block">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Description, merchant..." class="input input-bordered input-sm w-full text-sm" />
                </div>

                <div>
                    <label class="label-text text-xs text-slate-500 mb-1 block">Category</label>
                    <select wire:model.live="expenseCategory" class="select select-bordered select-sm w-full text-sm">
                        <option value="">All categories</option>
                        <option value="stock">Stock</option>
                        <option value="equipment">Equipment</option>
                        <option value="travel">Travel</option>
                        <option value="fuel">Fuel</option>
                        <option value="subsistence">Subsistence</option>
                        <option value="utilities">Utilities</option>
                        <option value="professional_fees">Professional Fees</option>
                        <option value="insurance">Insurance</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="label-text text-xs text-slate-500 mb-1 block">Status</label>
                    <select wire:model.live="reconciliationStatus" class="select select-bordered select-sm w-full text-sm">
                        <option value="">All statuses</option>
                        <option value="unmatched">Unmatched</option>
                        <option value="matched">Matched</option>
                        <option value="ignored">Ignored</option>
                    </select>
                </div>

                <div>
                    <label class="label-text text-xs text-slate-500 mb-1 block">From</label>
                    <input type="date" wire:model.live="dateFrom" class="input input-bordered input-sm w-full text-sm" />
                </div>

                <div>
                    <label class="label-text text-xs text-slate-500 mb-1 block">To</label>
                    <input type="date" wire:model.live="dateTo" class="input input-bordered input-sm w-full text-sm" />
                </div>

                <div>
                    <label class="label-text text-xs text-slate-500 mb-1 block">Account</label>
                    <select wire:model.live="bankAccountId" class="select select-bordered select-sm w-full text-sm">
                        <option value="">All accounts</option>
                        @foreach($bankAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3 flex justify-between items-center">
                <button wire:click="clearFilters" class="btn btn-ghost btn-xs text-slate-500">
                    <x-lucide-x class="w-3.5 h-3.5" />
                    Clear filters
                </button>
                <span class="text-xs text-slate-400">{{ $transactions->total() }} transactions</span>
            </div>
        </div>
    </div>

    <div class="card bg-white border border-slate-200 shadow-sm">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr class="text-slate-500 text-xs uppercase">
                        <th class="cursor-pointer hover:text-copper" wire:click="sortBy('transaction_date')">
                            Date
                            @if($sortField === 'transaction_date')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" />
                            @endif
                        </th>
                        <th class="cursor-pointer hover:text-copper" wire:click="sortBy('description')">
                            Description
                            @if($sortField === 'description')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" />
                            @endif
                        </th>
                        <th>Merchant</th>
                        <th class="cursor-pointer hover:text-copper text-right" wire:click="sortBy('amount')">
                            Amount
                            @if($sortField === 'amount')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" />
                            @endif
                        </th>
                        <th>Category</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $txn)
                        <tr class="hover:bg-slate-50 text-sm">
                            <td class="text-slate-600 whitespace-nowrap">{{ $txn->transaction_date->format('j M Y') }}</td>
                            <td class="max-w-xs truncate text-slate-800">
                                <a href="{{ route('admin.banking.transactions.show', $txn) }}" wire:navigate class="hover:text-copper transition-colors">
                                    {{ $txn->description }}
                                </a>
                            </td>
                            <td class="text-slate-500">{{ $txn->merchant_name ?? '-' }}</td>
                            <td class="text-right font-mono {{ $txn->amount < 0 ? 'text-slate-700' : 'text-teal' }}">
                                {{ $txn->amount < 0 ? '-' : '+' }}£{{ number_format(abs($txn->amount), 2) }}
                            </td>
                            <td>
                                @if($txn->expense_category)
                                    <span class="badge badge-sm bg-slate-100 text-slate-600 border-slate-200">{{ str_replace('_', ' ', $txn->expense_category) }}</span>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                            <td>
                                @if($txn->reconciliation_status === 'matched')
                                    <span class="badge badge-sm bg-teal/10 text-teal border-teal/20">Matched</span>
                                @elseif($txn->reconciliation_status === 'ignored')
                                    <span class="badge badge-sm bg-slate-100 text-slate-400 border-slate-200">Ignored</span>
                                @else
                                    <span class="badge badge-sm bg-amber/10 text-amber border-amber/20">Unmatched</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.banking.transactions.show', $txn) }}" wire:navigate class="btn btn-ghost btn-xs text-slate-400 hover:text-copper">
                                    <x-lucide-eye class="w-4 h-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-12 text-slate-400">
                                <x-lucide-banknote class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p class="text-sm">No transactions found.</p>
                                @if($search || $expenseCategory || $reconciliationStatus || $dateFrom || $dateTo || $bankAccountId)
                                    <button wire:click="clearFilters" class="btn btn-ghost btn-xs mt-2 text-copper">Clear filters</button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>
