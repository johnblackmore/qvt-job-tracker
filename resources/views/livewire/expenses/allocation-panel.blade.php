<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-ink">Allocate: {{ Str::limit($lineItem->description, 40) }}</h3>
            <p class="text-xs text-slate-500">Line total: £{{ number_format($lineTotal, 2) }}</p>
        </div>
        <p class="text-xs text-slate-500" x-text="`Allocated: £${calculateTotal()}`"></p>
    </div>

    <div class="space-y-3">
        @foreach($allocations as $index => $alloc)
            <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50">
                <div class="flex-1">
                    <select wire:model="allocations.{{ $index }}.order_id" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3 py-2">
                        <option value="">Select order...</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}">
                                {{ $order->reference_number }} — {{ $order->customer?->name ?? 'Unknown' }}
                            </option>
                        @endforeach
                    </select>
                    @error("allocations.{$index}.order_id") <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div class="w-32">
                    <div class="relative">
                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">£</span>
                        <input wire:model="allocations.{{ $index }}.amount" type="number" step="0.01" min="0" class="w-full text-right rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm pl-5 pr-2 py-2" placeholder="0.00" />
                    </div>
                </div>
                <button type="button" wire:click="removeRow({{ $index }})" class="p-1.5 rounded text-slate-400 hover:text-red-600 transition-colors">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>
        @endforeach
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="button" wire:click="addRow" class="text-sm font-medium text-copper hover:text-copper-dark">
            + Add another order
        </button>
        <span class="text-xs text-slate-400">|</span>
        <button type="button" wire:click="save" class="text-sm font-medium bg-copper text-white px-4 py-1.5 rounded-lg hover:bg-copper-dark transition-colors">
            Save Allocations
        </button>
    </div>

    {{-- Order search --}}
    <div class="relative">
        <x-lucide-search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search orders..." class="w-full rounded-lg border-slate-300 text-ink placeholder-slate-400 focus:border-copper focus:ring-copper text-xs pl-7 pr-3 py-1.5" />
    </div>

    <script>
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('[wire\\:model*="amount"]').forEach(el => {
                total += parseFloat(el.value) || 0;
            });
            return total.toFixed(2);
        }
    </script>
</div>
