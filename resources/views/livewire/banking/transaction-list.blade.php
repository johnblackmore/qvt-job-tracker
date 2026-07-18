<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Bank Transactions</h1>
            <p class="mt-1 text-sm text-slate-500">View and manage imported bank transactions</p>
        </div>
        @if($bankAccounts->isNotEmpty())
            <div class="flex items-center gap-3">
                <button wire:click="syncTransactions" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <x-lucide-refresh-cw wire:loading.remove class="w-4 h-4" />
                    <x-lucide-refresh-cw wire:loading class="w-4 h-4 animate-spin" />
                    <span wire:loading.remove>Sync Transactions</span>
                    <span wire:loading>Syncing...</span>
                </button>
                <a href="{{ route('admin.banking.accounts') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    <x-lucide-settings class="w-4 h-4" />
                    Manage Accounts
                </a>
                <a href="{{ route('admin.banking.connect') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    <x-lucide-plus class="w-4 h-4" />
                    Link Another Account
                </a>
            </div>
        @endif
    </div>

    @if($bankAccounts->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-12 text-center">
                <x-lucide-banknote class="w-12 h-12 mx-auto mb-4 text-slate-300" />
                <h2 class="text-lg font-display font-semibold text-slate-900 mb-2">No Bank Accounts Linked</h2>
                <p class="text-sm text-slate-500 mb-6 max-w-md mx-auto">
                    Link your Monzo business account to automatically import transactions, reconcile payments, and attach receipts for a complete digital audit trail.
                </p>
                <a href="{{ route('admin.banking.connect') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    <x-lucide-link-2 class="w-4 h-4" />
                    Connect Monzo Account
                </a>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Description, merchant..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
                    <select wire:model.live="expenseCategory" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2.5">
                        <option value="">All categories</option>
                        <optgroup label="Expenses">
                            @foreach(App\Models\BankTransaction::expenseCategories() as $cat)
                                <option value="{{ $cat }}">{{ str_replace('_', ' ', ucfirst($cat)) }}</option>
                            @endforeach
                            <option value="other">Other</option>
                        </optgroup>
                        <optgroup label="Income">
                            @foreach(App\Models\BankTransaction::incomeCategories() as $cat)
                                <option value="{{ $cat }}">{{ str_replace('_', ' ', ucfirst($cat)) }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                    <select wire:model.live="reconciliationStatus" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2.5">
                        <option value="">All statuses</option>
                        <option value="unmatched">Unmatched</option>
                        <option value="matched">Matched</option>
                        <option value="ignored">Ignored</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
                    <input type="date" wire:model.live="dateTo" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Account</label>
                    <select wire:model.live="bankAccountId" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2.5">
                        <option value="">All accounts</option>
                        @foreach($bankAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3 flex justify-between items-center">
                <button wire:click="clearFilters" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 transition-colors">
                    <x-lucide-x class="w-3.5 h-3.5" />
                    Clear filters
                </button>
                <span class="text-xs text-slate-400">{{ $transactions->total() }} transactions</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        {{-- Mobile card view --}}
        @if($transactions->count() > 0)
            <div class="block md:hidden divide-y divide-slate-100">
                @foreach($transactions as $txn)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('admin.banking.transactions.show', $txn) }}" wire:navigate class="font-medium text-slate-900 hover:text-copper transition-colors">
                                    {{ $txn->description }}
                                </a>
                                @if($txn->merchant_name)
                                    <div class="text-xs text-slate-500 mt-0.5">{{ $txn->merchant_name }}</div>
                                @endif
                            </div>
                            <a href="{{ route('admin.banking.transactions.show', $txn) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors shrink-0">
                                <x-lucide-eye class="w-4 h-4" />
                            </a>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">{{ $txn->transaction_date->format('j M Y') }}</span>
                            <span class="font-mono text-sm font-medium {{ $txn->amount < 0 ? 'text-slate-700' : 'text-teal' }}">
                                {{ $txn->amount < 0 ? '-' : '+' }}&pound;{{ number_format(abs($txn->amount), 2) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($txn->expense_category)
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">{{ str_replace('_', ' ', $txn->expense_category) }}</span>
                            @endif
                            @if($txn->reconciliation_status === 'matched')
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-teal/10 text-teal-dark border border-teal/20">Matched</span>
                            @elseif($txn->reconciliation_status === 'ignored')
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-400 border border-slate-200">Ignored</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">Unmatched</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 font-medium text-slate-700 cursor-pointer hover:text-copper transition-colors" wire:click="sortBy('transaction_date')">
                            Date
                            @if($sortField === 'transaction_date')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" />
                            @endif
                        </th>
                        <th class="px-6 py-3 font-medium text-slate-700 cursor-pointer hover:text-copper transition-colors" wire:click="sortBy('description')">
                            Description
                            @if($sortField === 'description')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" />
                            @endif
                        </th>
                        <th class="px-6 py-3 font-medium text-slate-700">Merchant</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right cursor-pointer hover:text-copper transition-colors" wire:click="sortBy('amount')">
                            Amount
                            @if($sortField === 'amount')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" />
                            @endif
                        </th>
                        <th class="px-6 py-3 font-medium text-slate-700">Category</th>
                        <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                        <th class="px-6 py-3 font-medium text-slate-700"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $txn)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-slate-600 whitespace-nowrap">{{ $txn->transaction_date->format('j M Y') }}</td>
                            <td class="px-6 py-4 max-w-xs truncate text-sm">
                                <a href="{{ route('admin.banking.transactions.show', $txn) }}" wire:navigate class="font-medium text-slate-900 hover:text-copper transition-colors">
                                    {{ $txn->description }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ $txn->merchant_name ?? '-' }}</td>
                            <td class="px-6 py-4 text-right font-mono text-sm {{ $txn->amount < 0 ? 'text-slate-700' : 'text-teal' }}">
                                {{ $txn->amount < 0 ? '-' : '+' }}&pound;{{ number_format(abs($txn->amount), 2) }}
                            </td>
                            <td class="px-6 py-4">
                                @if($txn->expense_category)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">{{ str_replace('_', ' ', $txn->expense_category) }}</span>
                                @else
                                    <span class="text-xs text-slate-300">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($txn->reconciliation_status === 'matched')
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-teal/10 text-teal-dark border border-teal/20">Matched</span>
                                @elseif($txn->reconciliation_status === 'ignored')
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-400 border border-slate-200">Ignored</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">Unmatched</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.banking.transactions.show', $txn) }}" wire:navigate class="inline-flex items-center justify-center p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                        <x-lucide-eye class="w-4 h-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <x-lucide-banknote class="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                <p class="text-sm text-slate-500">No transactions found.</p>
                                @if($search || $expenseCategory || $reconciliationStatus || $dateFrom || $dateTo || $bankAccountId)
                                    <button wire:click="clearFilters" class="mt-2 text-sm text-copper hover:text-copper-dark transition-colors">Clear filters</button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>
