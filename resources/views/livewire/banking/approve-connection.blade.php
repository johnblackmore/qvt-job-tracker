<div>
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Approve Monzo Connection</h1>
        <p class="mt-1 text-sm text-slate-500">Complete the authorisation in the Monzo app</p>
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
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-8 text-center">
                <div class="w-16 h-16 rounded-xl bg-copper/10 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-smartphone class="w-8 h-8 text-copper" />
                </div>

                <h2 class="text-xl font-display font-semibold text-slate-900 mb-2">
                    Approve in the Monzo App
                </h2>

                <p class="text-sm text-slate-500 max-w-md mx-auto mb-6">
                    Monzo has sent a notification to your phone. Open the Monzo app and approve the connection to <strong>QVT Job Tracker</strong> using your PIN or fingerprint, then come back here.
                </p>

                @if($error)
                    <div class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-6 text-sm text-red-700 text-left">
                        <x-lucide-alert-circle class="w-5 h-5 shrink-0" />
                        <span>{{ $error }}</span>
                    </div>
                @endif

                <div class="bg-slate-50 rounded-lg p-4 mb-6 text-sm text-slate-600 text-left">
                    <p class="font-medium text-slate-900 mb-2">Having trouble?</p>
                    <ul class="space-y-1 list-disc list-inside">
                        <li>Make sure the Monzo app is installed on your phone and you're logged in</li>
                        <li>Check your notification centre for the Monzo approval prompt</li>
                        <li>If you don't see a notification, try disconnecting and reconnecting</li>
                    </ul>
                </div>

                <div class="flex flex-col items-center gap-3">
                    <button wire:click="retry" class="inline-flex items-center gap-2 rounded-lg bg-copper px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors" wire:loading.attr="disabled">
                        <x-lucide-check-circle-2 class="w-5 h-5" />
                        I've approved &mdash; continue
                    </button>
                    <button wire:click="cancel" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
