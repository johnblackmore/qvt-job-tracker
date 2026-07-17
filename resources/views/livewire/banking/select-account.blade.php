<div>
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Link Monzo Account</h1>
        <p class="mt-1 text-sm text-slate-500">Select which Monzo account to link to QVT Job Tracker.</p>
    </div>

    @if(! $hasSession)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-12 text-center">
                <x-lucide-clock class="w-12 h-12 mx-auto mb-4 text-slate-300" />
                <h2 class="text-lg font-display font-semibold text-slate-900 mb-2">Session Expired</h2>
                <p class="text-sm text-slate-500 mb-6">Your authorisation session has expired. Please connect your Monzo account again.</p>
                <a href="{{ route('admin.banking.connect') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    Connect Monzo Account
                </a>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            @foreach($accounts as $index => $account)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:border-copper/40 hover:shadow-md transition-all cursor-pointer" wire:click="linkAccount({{ $index }})">
                    <div class="p-5">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center shrink-0">
                                <x-lucide-landmark class="w-5 h-5 text-copper" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-display font-semibold text-slate-900">{{ $account['description'] }}</h3>
                                <p class="text-xs text-slate-400 mt-0.5 font-mono">{{ $account['id'] }}</p>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 mt-2">
                                    {{ str_replace('_', ' ', $account['account_type']) }}
                                </span>
                            </div>
                            <x-lucide-chevron-right class="w-5 h-5 text-slate-300 shrink-0 mt-1" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="cancel" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 transition-colors">
                Cancel
            </button>
            <p class="text-xs text-slate-400">Selecting an account will immediately link it and import recent transactions.</p>
        </div>
    @endif
</div>
