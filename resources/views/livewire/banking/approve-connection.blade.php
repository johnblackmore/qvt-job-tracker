<div>
    <div class="mb-6">
        <h1 class="text-2xl font-display font-bold text-slate-800">Approve Monzo Connection</h1>
    </div>

    @if(! $hasSession)
        <div class="card bg-white border border-slate-200 shadow-sm">
            <div class="card-body p-8 text-center">
                <x-lucide-clock class="w-12 h-12 mx-auto mb-3 text-slate-300" />
                <h2 class="text-lg font-display font-semibold text-slate-600 mb-2">Session Expired</h2>
                <p class="text-sm text-slate-400 mb-4">Your authorisation session has expired. Please connect your Monzo account again.</p>
                <a href="{{ route('admin.banking.connect') }}" wire:navigate class="btn btn-primary">
                    Connect Monzo Account
                </a>
            </div>
        </div>
    @else
        <div class="card bg-white border border-slate-200 shadow-sm">
            <div class="card-body p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-copper/10 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-smartphone class="w-8 h-8 text-copper" />
                </div>

                <h2 class="text-xl font-display font-semibold text-slate-800 mb-2">
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
                    <p class="font-medium text-slate-700 mb-2">Having trouble?</p>
                    <ul class="space-y-1 list-disc list-inside">
                        <li>Make sure the Monzo app is installed on your phone and you're logged in</li>
                        <li>Check your notification centre for the Monzo approval prompt</li>
                        <li>If you don't see a notification, try disconnecting and reconnecting</li>
                    </ul>
                </div>

                <div class="flex flex-col items-center gap-3">
                    <button wire:click="retry" class="btn btn-primary btn-lg" wire:loading.attr="disabled">
                        <x-lucide-check-circle-2 class="w-5 h-5" />
                        I've approved — continue
                    </button>
                    <button wire:click="cancel" class="btn btn-ghost text-slate-400 btn-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
