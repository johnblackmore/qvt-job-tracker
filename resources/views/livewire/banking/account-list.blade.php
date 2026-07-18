<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Bank Accounts</h1>
            <p class="mt-1 text-sm text-slate-500">Manage connected bank accounts</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.banking.transactions') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                <x-lucide-arrow-left class="w-4 h-4" />
                Back to Transactions
            </a>
            <a href="{{ route('admin.banking.connect') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                <x-lucide-plus class="w-4 h-4" />
                Connect New Account
            </a>
        </div>
    </div>

    @if($accounts->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-12 text-center">
                <x-lucide-banknote class="w-12 h-12 mx-auto mb-4 text-slate-300" />
                <h2 class="text-lg font-display font-semibold text-slate-900 mb-2">No Bank Accounts</h2>
                <p class="text-sm text-slate-500 mb-6 max-w-md mx-auto">
                    Link your Monzo business account to automatically import transactions and reconcile payments.
                </p>
                <a href="{{ route('admin.banking.connect') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    <x-lucide-link-2 class="w-4 h-4" />
                    Connect Monzo Account
                </a>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($accounts as $account)
                @php $hasTokens = ! empty($account->metadata['tokens'] ?? []); @endphp
                @php $cached = $balances->get($account->id); @endphp
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                            <div class="w-12 h-12 rounded-xl bg-copper/10 flex items-center justify-center shrink-0">
                                <x-lucide-landmark class="w-6 h-6 text-copper" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <h3 class="font-display font-semibold text-slate-900 text-lg">{{ $account->name }}</h3>
                                    @if($hasTokens)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-teal/10 text-teal-dark border border-teal/20">Connected</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-50 text-red-700 border border-red-200">Reconnection Needed</span>
                                    @endif
                                    @if(! $account->is_active)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-400 border border-slate-200">Disabled</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-sm text-slate-500">
                                    <span class="inline-flex items-center gap-1">
                                        <x-lucide-building-2 class="w-3.5 h-3.5" />
                                        {{ ucfirst($account->provider) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-lucide-tag class="w-3.5 h-3.5" />
                                        {{ str_replace('_', ' ', $account->type ?? 'N/A') }}
                                    </span>
                                    @if($cached && $cached->balance_pence !== null)
                                        <span class="inline-flex items-center gap-1 font-mono text-slate-700 font-medium">
                                            <x-lucide-pound-sterling class="w-3.5 h-3.5" />
                                            &pound;{{ number_format($cached->balance_pence / 100, 2) }}
                                            @if($cached->balance_fetched_at)
                                                <span class="text-xs text-slate-400 font-sans">({{ $cached->balance_fetched_at->diffForHumans() }})</span>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-1.5 text-xs text-slate-400 font-mono break-all">{{ $account->provider_account_id }}</div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @if($hasTokens && $account->is_active)
                                    <button wire:click="refreshBalance({{ $account->id }})" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition-colors disabled:opacity-50">
                                        <x-lucide-refresh-cw class="w-4 h-4" />
                                        <span class="hidden sm:inline">Refresh Balance</span>
                                    </button>
                                @endif
                                @if($account->is_active)
                                    @if($hasTokens)
                                        <a href="{{ route('admin.banking.reconnect', $account) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                                            <x-lucide-refresh-cw class="w-4 h-4" />
                                            <span class="hidden sm:inline">Reconnect</span>
                                        </a>
                                    @else
                                        <a href="{{ route('admin.banking.reconnect', $account) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-copper px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                                            <x-lucide-link-2 class="w-4 h-4" />
                                            Reconnect Now
                                        </a>
                                    @endif
                                @endif
                                @if($account->is_active)
                                    <button wire:click="disconnect({{ $account->id }})" wire:confirm="Are you sure you want to disconnect this account? It will stop importing new transactions." wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 hover:border-red-200 transition-colors disabled:opacity-50">
                                        <x-lucide-plug class="w-4 h-4" />
                                        <span class="hidden sm:inline">Disconnect</span>
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400 italic">Account disabled</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
