<div>
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('admin.banking.transactions') }}" wire:navigate class="btn btn-ghost btn-sm text-slate-400 hover:text-copper">
            <x-lucide-arrow-left class="w-4 h-4" />
        </a>
        <h1 class="text-2xl font-display font-bold text-slate-800">Transaction Details</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="card bg-white border border-slate-200 shadow-sm">
                <div class="card-body p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-lg font-display font-semibold text-slate-800">{{ $transaction->description }}</h2>
                            @if($transaction->merchant_name)
                                <p class="text-sm text-slate-500">{{ $transaction->merchant_name }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-mono font-bold {{ $transaction->amount < 0 ? 'text-slate-700' : 'text-teal' }}">
                                {{ $transaction->amount < 0 ? '-' : '+' }}£{{ number_format(abs($transaction->amount), 2) }}
                            </p>
                            <p class="text-xs text-slate-400">{{ strtoupper($transaction->currency) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-slate-400 text-xs">Date</span>
                            <p class="text-slate-700">{{ $transaction->transaction_date->format('j F Y H:i') }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-xs">Settled</span>
                            <p class="text-slate-700">{{ $transaction->settled_date?->format('j F Y H:i') ?? 'Pending' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-xs">Account</span>
                            <p class="text-slate-700">{{ $transaction->bankAccount?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-xs">Merchant Category</span>
                            <p class="text-slate-700">{{ $transaction->merchant_category ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white border border-slate-200 shadow-sm">
                <div class="card-body p-6">
                    <h3 class="font-display font-semibold text-slate-700 mb-3">Category</h3>
                    <div class="flex items-center gap-3">
                        <select wire:model="expenseCategory" class="select select-bordered select-sm text-sm">
                            <option value="">Uncategorised</option>
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
                        <button wire:click="saveCategory" class="btn btn-primary btn-sm">
                            Save
                        </button>
                    </div>
                </div>
            </div>

            <div class="card bg-white border border-slate-200 shadow-sm">
                <div class="card-body p-6">
                    <h3 class="font-display font-semibold text-slate-700 mb-3">Notes</h3>
                    <textarea wire:model="notes" rows="3" class="textarea textarea-bordered w-full text-sm" placeholder="Add internal notes..."></textarea>
                    <div class="mt-2">
                        <button wire:click="saveNotes" class="btn btn-primary btn-sm">
                            Save Notes
                        </button>
                    </div>
                </div>
            </div>

            <div class="card bg-white border border-slate-200 shadow-sm">
                <div class="card-body p-6">
                    <h3 class="font-display font-semibold text-slate-700 mb-3">Receipts</h3>

                    <div class="space-y-3 mb-4">
                        @forelse($transaction->receipts as $receipt)
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-lucide-file-image class="w-5 h-5 shrink-0 text-slate-400" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-700 truncate">{{ $receipt->original_filename }}</p>
                                        <div class="flex items-center gap-2 text-xs text-slate-400">
                                            <span>{{ number_format($receipt->file_size / 1024, 1) }} KB</span>
                                            @if($receipt->sync_status === 'synced')
                                                <span class="text-teal">Synced to Monzo</span>
                                            @elseif($receipt->sync_status === 'failed')
                                                <span class="text-red-500">Sync failed</span>
                                            @elseif($receipt->sync_status === 'pending')
                                                <span class="text-amber">Pending sync</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <button wire:click="removeReceipt({{ $receipt->id }})" class="btn btn-ghost btn-xs text-slate-400 hover:text-red-500 shrink-0" title="Remove">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400 text-center py-4">No receipts attached.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <p class="text-xs text-slate-400 mb-2">Upload a receipt or invoice image (JPG, PNG, PDF — max 10MB)</p>
                        <div class="flex items-center gap-3">
                            <input type="file" wire:model="upload" accept="image/jpeg,image/png,image/gif,application/pdf" class="file-input file-input-bordered file-input-sm w-full max-w-xs text-sm" />
                            <button wire:click="uploadReceipt" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                <x-lucide-upload class="w-4 h-4" />
                                Upload
                            </button>
                        </div>
                        @error('upload') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card bg-white border border-slate-200 shadow-sm">
                <div class="card-body p-6">
                    <h3 class="font-display font-semibold text-slate-700 mb-3">Status</h3>
                    <div class="space-y-2">
                        @if($transaction->reconciliation_status === 'matched')
                            <div class="flex items-center gap-2 text-teal">
                                <x-lucide-check-circle-2 class="w-5 h-5" />
                                <span class="text-sm font-medium">Matched</span>
                            </div>
                            @if($transaction->matchedPayment)
                                <p class="text-xs text-slate-500">
                                    Linked to payment on order
                                    <a href="{{ route('orders.show', $transaction->matchedPayment->order_id) }}" wire:navigate class="text-copper hover:underline">
                                        #{{ $transaction->matchedPayment->order_id }}
                                    </a>
                                </p>
                            @endif
                        @elseif($transaction->reconciliation_status === 'ignored')
                            <div class="flex items-center gap-2 text-slate-400">
                                <x-lucide-eye-off class="w-5 h-5" />
                                <span class="text-sm font-medium">Ignored</span>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-amber">
                                <x-lucide-alert-circle class="w-5 h-5" />
                                <span class="text-sm font-medium">Unmatched</span>
                            </div>
                        @endif

                        <button wire:click="toggleIgnored" class="btn btn-ghost btn-xs text-slate-400 mt-2">
                            @if($transaction->reconciliation_status === 'ignored')
                                <x-lucide-rotate-ccw class="w-3.5 h-3.5" />
                                Re-open
                            @else
                                <x-lucide-eye-off class="w-3.5 h-3.5" />
                                Ignore transaction
                            @endif
                        </button>
                    </div>
                </div>
            </div>

            <div class="card bg-white border border-slate-200 shadow-sm">
                <div class="card-body p-6">
                    <h3 class="font-display font-semibold text-slate-700 mb-3">Raw Data</h3>
                    <p class="text-xs text-slate-400 mb-3">View the original data returned by the bank for this transaction.</p>
                    <button onclick="document.getElementById('raw-data-modal').showModal()" class="btn btn-ghost btn-sm text-slate-400 hover:text-copper">
                        <x-lucide-code class="w-4 h-4" />
                        View Raw Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <dialog id="raw-data-modal" class="modal">
        <div class="modal-box max-w-3xl bg-white">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2 text-slate-400">✕</button>
            </form>
            <h3 class="font-display font-semibold text-slate-700 mb-4">Raw Transaction Data</h3>
            <div class="bg-slate-50 rounded-lg border border-slate-200" style="max-height: 400px; overflow-y: auto;">
                <pre class="text-xs text-slate-500 p-4">{{ json_encode($transaction->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
        </div>
    </div>
</div>
