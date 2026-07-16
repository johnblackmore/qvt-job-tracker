<div class="max-w-2xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">VAT Settings</h1>
        <p class="mt-1 text-sm text-slate-500">Configure VAT rates used for cost price calculations.</p>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div>
            <h2 class="text-base font-display font-semibold text-slate-900 mb-1">VAT Rates</h2>
            <p class="text-sm text-slate-500 mb-5">These rates are applied when calculating the true cost of items with ex-VAT trade prices.</p>

            <div class="space-y-5">
                <div>
                    <label for="standard_rate" class="block text-sm font-medium text-slate-700 mb-1.5">Standard Rate (%)</label>
                    <div class="relative">
                        <input wire:model="standard_rate" id="standard_rate" type="number" step="0.01" min="0" max="100" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5 pr-8" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Most goods and services. Current UK rate: 20%.</p>
                    @error('standard_rate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="reduced_rate" class="block text-sm font-medium text-slate-700 mb-1.5">Reduced Rate (%)</label>
                    <div class="relative">
                        <input wire:model="reduced_rate" id="reduced_rate" type="number" step="0.01" min="0" max="100" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5 pr-8" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Energy-saving materials, children's car seats, etc. Current UK rate: 5%.</p>
                    @error('reduced_rate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="zero_rate" class="block text-sm font-medium text-slate-700 mb-1.5">Zero Rate (%)</label>
                    <div class="relative">
                        <input wire:model="zero_rate" id="zero_rate" type="number" step="0.01" min="0" max="100" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5 pr-8" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Zero-rated items. Usually 0%.</p>
                    @error('zero_rate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading>Saving...</span>
            </button>
            <span wire:loading class="text-sm text-slate-500">Saving...</span>
        </div>
    </form>
</div>
