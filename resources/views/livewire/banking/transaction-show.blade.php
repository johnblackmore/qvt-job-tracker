<div>
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('admin.banking.transactions') }}" wire:navigate class="text-sm text-slate-500 hover:text-copper transition-colors">Bank Transactions</a>
            <x-lucide-chevron-right class="w-4 h-4 text-slate-400" />
            <span class="text-sm text-slate-900 font-medium truncate">{{ $transaction->description }}</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Transaction Details</h1>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-500">
                    <span>{{ $transaction->transaction_date->format('j F Y H:i') }}</span>
                    @if($transaction->merchant_name)
                        <span class="text-slate-400">&middot;</span>
                        <span>{{ $transaction->merchant_name }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ $transaction->description }}</h2>
                            @if($transaction->merchant_name)
                                <p class="text-sm text-slate-500 mt-0.5">{{ $transaction->merchant_name }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-mono font-bold {{ $transaction->amount < 0 ? 'text-slate-700' : 'text-teal' }}">
                                {{ $transaction->amount < 0 ? '-' : '+' }}&pound;{{ number_format(abs($transaction->amount), 2) }}
                            </p>
                            <p class="text-xs text-slate-400">{{ strtoupper($transaction->currency) }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-xs text-slate-400">Date</span>
                            <p class="text-slate-900 font-medium mt-0.5">{{ $transaction->transaction_date->format('j F Y H:i') }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Settled</span>
                            <p class="text-slate-900 font-medium mt-0.5">{{ $transaction->settled_date?->format('j F Y H:i') ?? 'Pending' }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Account</span>
                            <p class="text-slate-900 font-medium mt-0.5">{{ $transaction->bankAccount?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Merchant Category</span>
                            <p class="text-slate-900 font-medium mt-0.5">{{ $transaction->merchant_category ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-900">Category</h2>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-3">
                        <select wire:model="expenseCategory" class="w-full max-w-xs rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2.5">
                            <option value="">Uncategorised</option>
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
                        <button wire:click="saveCategory" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                            Save
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-900">Notes</h2>
                </div>
                <div class="p-6">
                    <textarea wire:model="notes" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Add internal notes..."></textarea>
                    <div class="mt-3">
                        <button wire:click="saveNotes" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                            Save Notes
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-900">Receipts</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-3 mb-4">
                        @forelse($transaction->receipts as $receipt)
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-lucide-file-image class="w-5 h-5 shrink-0 text-slate-400" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate">{{ $receipt->original_filename }}</p>
                                        <div class="flex items-center gap-2 text-xs text-slate-400">
                                            <span>{{ number_format($receipt->file_size / 1024, 1) }} KB</span>
                                            @if($receipt->sync_status === 'synced')
                                                <span class="text-teal">Synced to Monzo</span>
                                            @elseif($receipt->sync_status === 'failed')
                                                <span class="text-red-500">Sync failed</span>
                                            @elseif($receipt->sync_status === 'pending')
                                                <span class="text-amber-700">Pending sync</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <button wire:click="removeReceipt({{ $receipt->id }})" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors shrink-0" title="Remove">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400 text-center py-4">No receipts attached.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <p class="text-xs text-slate-400 mb-2">Upload a receipt or invoice image (JPG, PNG, PDF &mdash; max 10MB)</p>
                        <div class="flex items-center gap-3">
                            <input type="file" wire:model="upload" accept="image/jpeg,image/png,image/gif,application/pdf" class="block w-full max-w-xs text-sm text-slate-900 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-copper/10 file:text-copper hover:file:bg-copper/20 file:cursor-pointer file:transition-colors" />
                            <button wire:click="uploadReceipt" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors" wire:loading.attr="disabled">
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
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Status</h2>
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
                        <div class="flex items-center gap-2 text-amber-700">
                            <x-lucide-alert-circle class="w-5 h-5" />
                            <span class="text-sm font-medium">Unmatched</span>
                        </div>
                    @endif

                    <button wire:click="toggleIgnored" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 transition-colors mt-2">
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

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Raw Data</h2>
                <p class="text-xs text-slate-400 mb-3">View the original data returned by the bank for this transaction.</p>
                <button wire:click="$set('showRawData', true)" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-copper transition-colors">
                    <x-lucide-code class="w-4 h-4" />
                    View Raw Data
                </button>
            </div>
        </div>
    </div>

    @if($showRawData)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40" wire:click="$set('showRawData', false)"></div>
            <div class="relative bg-white rounded-xl border border-slate-200 shadow-lg max-w-3xl w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Raw Transaction Data</h3>
                    <button wire:click="$set('showRawData', false)" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
                <div class="bg-slate-50 rounded-lg border border-slate-200 overflow-auto max-h-96">
                    <pre class="text-xs text-slate-600 p-4">{{ json_encode($transaction->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
