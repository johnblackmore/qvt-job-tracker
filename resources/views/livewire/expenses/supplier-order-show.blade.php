<div class="max-w-4xl">
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('expenses.supplier-orders.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 mb-2 inline-block">&larr; Back to Supplier Orders</a>
            <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">{{ $supplierOrder->reference_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                @if($supplierOrder->supplier)
                    {{ $supplierOrder->supplier->name }}
                @else
                    No supplier
                @endif
                &middot; {{ $supplierOrder->order_date->format('j M Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('expenses.supplier-orders.edit', $supplierOrder->id) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border-2 border-copper bg-white px-4 py-2 text-sm font-semibold text-copper hover:bg-copper hover:text-white transition-colors">
                <x-lucide-pencil class="w-4 h-4" />
                Edit
            </a>
            <button wire:click="delete" wire:confirm="Delete this supplier order? This cannot be undone." class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition-colors">
                <x-lucide-trash-2 class="w-4 h-4" />
                Delete
            </button>
        </div>
    </div>

    {{-- Status Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Status</p>
            @php
                $statusStyles = [
                    'draft' => 'bg-slate-100 text-slate-600',
                    'ordered' => 'bg-blue-50 text-blue-600',
                    'received' => 'bg-teal/10 text-teal-dark',
                    'partially_received' => 'bg-amber-50 text-amber-600',
                    'paid' => 'bg-teal/10 text-teal-dark',
                    'cancelled' => 'bg-red-50 text-red-600',
                ];
            @endphp
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusStyles[$supplierOrder->status] ?? 'bg-slate-100 text-slate-600' }}">
                {{ str_replace('_', ' ', ucfirst($supplierOrder->status)) }}
            </span>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total</p>
            <p class="text-xl font-display font-semibold text-ink">£{{ number_format($supplierOrder->total_amount, 2) }}</p>
            <p class="text-xs text-slate-500">VAT: £{{ number_format($supplierOrder->vat_total, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Payment</p>
            @if($supplierOrder->paid_at)
                <p class="text-sm font-medium text-teal-dark">Paid {{ $supplierOrder->paid_at->format('j M Y') }}</p>
                @if($supplierOrder->payment_method)
                    <p class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $supplierOrder->payment_method)) }}</p>
                @endif
            @else
                <p class="text-sm text-slate-500">Not yet paid</p>
                @if($supplierOrder->due_date)
                    <p class="text-xs text-slate-500">Due {{ $supplierOrder->due_date->format('j M Y') }}</p>
                @endif
            @endif
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Bank Match</p>
            @if($supplierOrder->bankTransaction)
                <p class="text-sm font-medium text-teal-dark">Matched</p>
                <p class="text-xs text-slate-500">{{ $supplierOrder->bankTransaction->description }}</p>
                <button wire:click="unlinkTransaction" wire:confirm="Remove bank transaction link?" class="text-xs text-red-600 hover:underline mt-1">Unlink</button>
            @else
                <p class="text-sm text-slate-500">Unmatched</p>
                <button wire:click="openLinkModal" class="text-xs text-copper hover:underline mt-1">Link to transaction</button>
            @endif
        </div>
    </div>

    {{-- Order Details --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-base font-display font-semibold text-ink mb-4">Order Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            @if($supplierOrder->invoice_number)
                <div>
                    <dt class="text-slate-500">Invoice number</dt>
                    <dd class="font-medium text-ink">{{ $supplierOrder->invoice_number }}</dd>
                </div>
            @endif
            @if($supplierOrder->invoice_date)
                <div>
                    <dt class="text-slate-500">Invoice date</dt>
                    <dd class="font-medium text-ink">{{ $supplierOrder->invoice_date->format('j M Y') }}</dd>
                </div>
            @endif
            @if($supplierOrder->due_date)
                <div>
                    <dt class="text-slate-500">Due date</dt>
                    <dd class="font-medium text-ink">{{ $supplierOrder->due_date->format('j M Y') }}</dd>
                </div>
            @endif
            @if($supplierOrder->payment_method)
                <div>
                    <dt class="text-slate-500">Payment method</dt>
                    <dd class="font-medium text-ink">{{ ucfirst(str_replace('_', ' ', $supplierOrder->payment_method)) }}</dd>
                </div>
            @endif
            @if($supplierOrder->payment_reference)
                <div>
                    <dt class="text-slate-500">Payment reference</dt>
                    <dd class="font-medium text-ink">{{ $supplierOrder->payment_reference }}</dd>
                </div>
            @endif
            @if($supplierOrder->createdBy)
                <div>
                    <dt class="text-slate-500">Recorded by</dt>
                    <dd class="font-medium text-ink">{{ $supplierOrder->createdBy->name }}</dd>
                </div>
            @endif
        </dl>
        @if($supplierOrder->notes)
            <div class="mt-4">
                <dt class="text-sm text-slate-500">Notes</dt>
                <dd class="text-sm text-ink mt-1 whitespace-pre-wrap">{{ $supplierOrder->notes }}</dd>
            </div>
        @endif
    </div>

    {{-- Line Items with Allocations --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-base font-display font-semibold text-ink">Line Items</h2>
        </div>
        @if($supplierOrder->lineItems->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200">
                            <th class="px-6 py-3 text-left font-medium text-slate-700">Type</th>
                            <th class="px-6 py-3 text-left font-medium text-slate-700">Description</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Qty</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Unit Price</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Total</th>
                            <th class="px-6 py-3 text-center font-medium text-slate-700">Allocation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($supplierOrder->lineItems as $item)
                            <tr>
                                <td class="px-6 py-3">
                                    @php
                                        $typeLabels = ['product' => 'Product', 'service' => 'Service', 'expense' => 'Expense', 'personal' => 'Personal'];
                                        $typeColors = ['product' => 'bg-blue-50 text-blue-600', 'service' => 'bg-purple-50 text-purple-600', 'expense' => 'bg-teal/10 text-teal-dark', 'personal' => 'bg-amber-50 text-amber-600'];
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$item->line_type] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ $typeLabels[$item->line_type] ?? $item->line_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-ink">{{ $item->description }}</td>
                                <td class="px-6 py-3 text-right text-slate-600">{{ $item->quantity }}</td>
                                <td class="px-6 py-3 text-right text-slate-600">£{{ number_format($item->unit_amount, 2) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-ink">£{{ number_format($item->line_total, 2) }}</td>
                                <td class="px-6 py-3 text-center">
                                    @if($item->line_type !== 'personal')
                                        @php
                                            $allocatedTotal = $item->allocations->sum('amount');
                                        @endphp
                                        <button wire:click="startAllocation({{ $item->id }})" class="text-xs font-medium {{ $allocatedTotal > 0 ? 'text-teal-dark' : 'text-slate-400 hover:text-copper' }} transition-colors">
                                            @if($allocatedTotal > 0)
                                                Allocated £{{ number_format($allocatedTotal, 2) }}
                                            @else
                                                Allocate
                                            @endif
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($allocatingLineItemId)
                <div class="border-t border-slate-200 p-6 bg-slate-50/50">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-ink">Allocation</h3>
                        <button wire:click="closeAllocation" class="text-xs text-slate-500 hover:text-slate-700">&larr; Close</button>
                    </div>
                    @php $lineItem = $supplierOrder->lineItems->firstWhere('id', $allocatingLineItemId); @endphp
                    @if($lineItem)
                        <livewire:expenses.allocation-panel :lineItem="$lineItem" :wire:key="'alloc-'.$lineItem->id" />
                    @endif
                </div>
            @endif
        @else
            <div class="p-8 text-center text-sm text-slate-500">No line items on this order.</div>
        @endif
    </div>

    {{-- Documents --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-display font-semibold text-ink">Documents</h2>
        </div>
        @if($supplierOrder->documents->count() > 0)
            <div class="space-y-2">
                @foreach($supplierOrder->documents as $doc)
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                        <div class="flex items-center gap-3">
                            <x-lucide-file-text class="w-5 h-5 text-slate-400" />
                            <div>
                                <p class="text-sm font-medium text-ink">{{ $doc->original_filename }}</p>
                                <p class="text-xs text-slate-500">{{ $doc->document_type }} &middot; {{ $doc->file_size ? round($doc->file_size / 1024, 1) . ' KB' : '' }}</p>
                            </div>
                        </div>
                        <a href="{{ route('expenses.documents.download', $doc) }}" class="text-sm font-medium text-copper hover:underline">Download</a>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-slate-500">No documents uploaded yet.</p>
        @endif
    </div>

    {{-- Link to Bank Transaction Modal --}}
    @if($showLinkModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 w-full max-w-lg max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-display font-semibold text-ink">Link to Bank Transaction</h3>
                    <button wire:click="closeLinkModal" class="text-slate-400 hover:text-slate-600">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="relative">
                        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <input wire:model.live.debounce.300ms="transactionSearch" type="text" placeholder="Search transactions..." class="w-full rounded-lg border-slate-300 text-ink placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Match amount (£)</label>
                        <input wire:model="matchAmount" type="number" step="0.01" min="0.01" class="w-full rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                        @error('matchAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-1 max-h-60 overflow-y-auto">
                        @forelse($transactions as $txn)
                            <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50 cursor-pointer {{ $selectedTransactionId === $txn->id ? 'bg-copper/5 border border-copper/20' : 'border border-transparent' }}">
                                <input wire:model="selectedTransactionId" type="radio" name="txn" value="{{ $txn->id }}" class="mt-0.5 rounded-full border-slate-300 text-copper focus:ring-copper" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-ink truncate">{{ $txn->description }}</p>
                                    <p class="text-xs text-slate-500">{{ $txn->merchant_name ?? 'Unknown' }} &middot; {{ $txn->transaction_date->format('j M Y') }}</p>
                                </div>
                                <p class="text-sm font-medium text-ink whitespace-nowrap">-£{{ number_format(abs($txn->amount), 2) }}</p>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500 text-center py-4">No unmatched debit transactions found.</p>
                        @endforelse
                    </div>
                    @error('selectedTransactionId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button wire:click="closeLinkModal" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
                        <button wire:click="linkTransaction" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                            Link Transaction
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
