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
            {{-- Status Card --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Status</h2>
                <div class="space-y-2">
                    @if($transaction->reconciliation_status === 'matched')
                        <div class="flex items-center gap-2 text-teal">
                            <x-lucide-check-circle-2 class="w-5 h-5" />
                            <span class="text-sm font-medium">Matched</span>
                        </div>

                        @php $matchedEntity = $transaction->matchedEntity(); @endphp
                        @if($matchedEntity)
                            <p class="text-xs text-slate-500">
                                @php $url = $transaction->matchedEntityUrl(); @endphp
                                @if($url)
                                    Linked to
                                    <a href="{{ $url }}" wire:navigate class="text-copper hover:underline">
                                        {{ $transaction->matchedEntityLabel() }}
                                    </a>
                                @else
                                    {{ $transaction->matchedEntityLabel() }}
                                @endif
                            </p>
                        @endif

                        <button wire:click="unlinkMatch" wire:confirm="Unlink this transaction?" class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 transition-colors mt-2">
                            <x-lucide-unlink-2 class="w-3.5 h-3.5" />
                            Unlink
                        </button>
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

                    @if($transaction->reconciliation_status !== 'matched')
                        <button wire:click="toggleIgnored" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 transition-colors mt-2">
                            @if($transaction->reconciliation_status === 'ignored')
                                <x-lucide-rotate-ccw class="w-3.5 h-3.5" />
                                Re-open
                            @else
                                <x-lucide-eye-off class="w-3.5 h-3.5" />
                                Ignore transaction
                            @endif
                        </button>
                    @endif
                </div>
            </div>

            {{-- Match / Record Payment Card (only for unmatched) --}}
            @if($transaction->reconciliation_status === 'unmatched')
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">Matching</h2>

                    @if($transaction->amount < 0)
                        <div class="space-y-2">
                            <p class="text-xs text-slate-400 mb-2">Link this debit to:</p>
                            <button wire:click="openMatchPanel('payment')" class="w-full inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                <x-lucide-arrow-right-from-line class="w-4 h-4 text-teal" />
                                Order Payment
                            </button>
                            <button wire:click="openMatchPanel('expense')" class="w-full inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                <x-lucide-receipt class="w-4 h-4 text-amber-700" />
                                Expense
                            </button>
                            <button wire:click="openMatchPanel('supplier_order')" class="w-full inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                <x-lucide-building-2 class="w-4 h-4 text-blue-600" />
                                Supplier Order
                            </button>
                        </div>
                    @else
                        <div>
                            <p class="text-xs text-slate-400 mb-2">This is a credit (money in). Record as a customer payment:</p>
                            <button wire:click="openMatchPanel('record_payment')" class="w-full inline-flex items-center gap-2 rounded-lg bg-copper px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                                <x-lucide-circle-dollar-sign class="w-4 h-4" />
                                Record as Payment
                            </button>
                        </div>
                    @endif
                </div>

                {{-- AI Suggestions Card --}}
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-slate-900">AI Suggestions</h2>
                        @if(!$aiLoaded)
                            <button wire:click="loadAiSuggestions" wire:loading.attr="disabled" class="inline-flex items-center gap-1 text-xs font-medium text-copper hover:text-copper-dark transition-colors">
                                <x-lucide-wand-2 wire:loading.remove class="w-3.5 h-3.5" />
                                <x-lucide-wand-2 wire:loading class="w-3.5 h-3.5 animate-pulse" />
                                <span wire:loading.remove>Find matches</span>
                                <span wire:loading>Analysing...</span>
                            </button>
                        @endif
                    </div>

                    @if($aiLoading)
                        <div class="flex items-center justify-center py-6">
                            <x-lucide-loader class="w-5 h-5 animate-spin text-copper" />
                            <span class="ml-2 text-sm text-slate-500">Analysing transaction...</span>
                        </div>
                    @elseif($aiLoaded && empty($aiSuggestions))
                        <p class="text-xs text-slate-400 text-center py-4">No likely matches found.</p>
                    @elseif($aiLoaded)
                        <div class="space-y-2">
                            @foreach($aiSuggestions as $suggestion)
                                <div class="p-3 rounded-lg border border-slate-200 text-sm">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-medium text-slate-900 truncate">{{ $suggestion['reference'] }}</span>
                                                <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium
                                                    {{ $suggestion['confidence'] === 'High' ? 'bg-teal/10 text-teal-dark' : ($suggestion['confidence'] === 'Medium' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500') }}">
                                                    {{ $suggestion['confidence'] }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-0.5">{{ $suggestion['label'] }}</p>
                                            <p class="text-xs text-slate-400 mt-0.5">&pound;{{ number_format($suggestion['amount'], 2) }} &middot; {{ $suggestion['date'] }}</p>
                                        </div>
                                        <button wire:click="acceptAiSuggestion('{{ $suggestion['type'] }}', {{ $suggestion['id'] }})" wire:confirm="Accept this suggestion?" class="inline-flex items-center gap-1 rounded-lg bg-copper/10 px-2.5 py-1 text-xs font-medium text-copper hover:bg-copper/20 transition-colors shrink-0">
                                            <x-lucide-check class="w-3 h-3" />
                                            Accept
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400 text-center py-4">Click "Find matches" to get AI-powered suggestions for this transaction.</p>
                    @endif
                </div>
            @endif

            {{-- Raw Data Card --}}
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

    {{-- Match Panel Modal --}}
    @if($showMatchPanel)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 w-full max-w-lg max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-display font-semibold text-slate-900">
                        @switch($matchType)
                            @case('payment') Link to Order Payment @break
                            @case('expense') Link to Expense @break
                            @case('supplier_order') Link to Supplier Order @break
                            @case('record_payment') Record as Payment @break
                            @default Link Record
                        @endswitch
                    </h3>
                    <button wire:click="closeMatchPanel" class="text-slate-400 hover:text-slate-600">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>

                @if($matchType === 'record_payment')
                    <div class="space-y-4">
                        <div class="relative">
                            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <input wire:model.live.debounce.300ms="matchSearch" type="text" placeholder="Search orders..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Payment amount (&pound;)</label>
                            <input wire:model="paymentAmount" type="number" step="0.01" min="0.01" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                            @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Payment method</label>
                            <select wire:model="paymentMethod" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2.5">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="cash">Cash</option>
                                <option value="other">Other</option>
                            </select>
                            @error('paymentMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Reference (optional)</label>
                            <input wire:model="paymentReference" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Transaction ref..." />
                        </div>

                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            @forelse($matchSearchResults as $result)
                                <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50 cursor-pointer {{ $selectedMatchId === $result['id'] ? 'bg-copper/5 border border-copper/20' : 'border border-transparent' }}">
                                    <input wire:model="selectedMatchId" type="radio" name="match" value="{{ $result['id'] }}" class="mt-0.5 rounded-full border-slate-300 text-copper focus:ring-copper" />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate">{{ $result['label'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $result['detail'] }}</p>
                                    </div>
                                    <p class="text-sm font-medium text-slate-900 whitespace-nowrap">&pound;{{ number_format($result['amount'], 2) }}</p>
                                </label>
                            @empty
                                <p class="text-sm text-slate-500 text-center py-4">No matching orders found.</p>
                            @endforelse
                        </div>
                        @error('selectedMatchId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button wire:click="closeMatchPanel" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
                            <button wire:click="recordPaymentAndLink" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                                <x-lucide-circle-dollar-sign class="w-4 h-4" />
                                Record &amp; Link
                            </button>
                        </div>
                    </div>
                @else
                    <div class="space-y-4">
                        <div class="relative">
                            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <input wire:model.live.debounce.300ms="matchSearch" type="text" placeholder="Search..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Match amount (&pound;)</label>
                            <input wire:model="matchAmount" type="number" step="0.01" min="0.01" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                            @error('matchAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            @forelse($matchSearchResults as $result)
                                <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50 cursor-pointer {{ $selectedMatchId === $result['id'] ? 'bg-copper/5 border border-copper/20' : 'border border-transparent' }}">
                                    <input wire:model="selectedMatchId" type="radio" name="match" value="{{ $result['id'] }}" class="mt-0.5 rounded-full border-slate-300 text-copper focus:ring-copper" />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate">{{ $result['label'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $result['detail'] }} &middot; {{ $result['date'] }}</p>
                                    </div>
                                    <p class="text-sm font-medium text-slate-900 whitespace-nowrap">&pound;{{ number_format($result['amount'], 2) }}</p>
                                </label>
                            @empty
                                <p class="text-sm text-slate-500 text-center py-4">No results found.</p>
                            @endforelse
                        </div>
                        @error('selectedMatchId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button wire:click="closeMatchPanel" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
                            <button wire:click="{{ $matchType === 'payment' ? 'linkToPayment' : ($matchType === 'expense' ? 'linkToExpense' : 'linkToSupplierOrder') }}" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                                <x-lucide-link-2 class="w-4 h-4" />
                                Link
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Raw Data Modal --}}
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
