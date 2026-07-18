<div class="max-w-3xl">
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('expenses.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 mb-2 inline-block">&larr; Back to Expenses</a>
            <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">{{ $expense->reference_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $expense->merchant_name ?? 'Expense' }}
                &middot; {{ $expense->expense_date->format('j M Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('expenses.edit', $expense->id) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border-2 border-copper bg-white px-4 py-2 text-sm font-semibold text-copper hover:bg-copper hover:text-white transition-colors">
                <x-lucide-pencil class="w-4 h-4" />
                Edit
            </a>
            <button wire:click="delete" wire:confirm="Delete this expense? This cannot be undone." class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition-colors">
                <x-lucide-trash-2 class="w-4 h-4" />
                Delete
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Status</p>
            @php
                $statusStyles = [
                    'draft' => 'bg-slate-100 text-slate-600',
                    'approved' => 'bg-teal/10 text-teal-dark',
                    'paid' => 'bg-teal/10 text-teal-dark',
                    'cancelled' => 'bg-red-50 text-red-600',
                ];
            @endphp
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusStyles[$expense->status] ?? 'bg-slate-100 text-slate-600' }}">
                {{ ucfirst($expense->status) }}
            </span>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total</p>
            <p class="text-xl font-display font-semibold text-ink">£{{ number_format($expense->total_amount, 2) }}</p>
            @if($expense->vat_total > 0)
                <p class="text-xs text-slate-500">VAT: £{{ number_format($expense->vat_total, 2) }}</p>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Category</p>
            @if($expense->category)
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                    {{ $expense->category->name }}
                </span>
            @else
                <p class="text-sm text-slate-500">Uncategorised</p>
            @endif
        </div>
    </div>

    {{-- Details --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-base font-display font-semibold text-ink mb-4">Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-slate-500">Description</dt>
                <dd class="font-medium text-ink mt-0.5">{{ $expense->description }}</dd>
            </div>
            @if($expense->merchant_name)
                <div>
                    <dt class="text-slate-500">Merchant</dt>
                    <dd class="font-medium text-ink mt-0.5">{{ $expense->merchant_name }}</dd>
                </div>
            @endif
            @if($expense->payment_method)
                <div>
                    <dt class="text-slate-500">Payment method</dt>
                    <dd class="font-medium text-ink mt-0.5">{{ ucfirst(str_replace('_', ' ', $expense->payment_method)) }}</dd>
                </div>
            @endif
            @if($expense->payment_reference)
                <div>
                    <dt class="text-slate-500">Payment reference</dt>
                    <dd class="font-medium text-ink mt-0.5">{{ $expense->payment_reference }}</dd>
                </div>
            @endif
            @if($expense->paid_at)
                <div>
                    <dt class="text-slate-500">Paid at</dt>
                    <dd class="font-medium text-ink mt-0.5">{{ $expense->paid_at->format('j M Y H:i') }}</dd>
                </div>
            @endif
            @if($expense->createdBy)
                <div>
                    <dt class="text-slate-500">Recorded by</dt>
                    <dd class="font-medium text-ink mt-0.5">{{ $expense->createdBy->name }}</dd>
                </div>
            @endif
        </dl>
        @if($expense->notes)
            <div class="mt-4">
                <dt class="text-sm text-slate-500">Notes</dt>
                <dd class="text-sm text-ink mt-1 whitespace-pre-wrap">{{ $expense->notes }}</dd>
            </div>
        @endif
    </div>

    {{-- Line Items --}}
    @if($expense->lineItems->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="text-base font-display font-semibold text-ink">Line Items</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-6 py-3 text-left font-medium text-slate-700">Type</th>
                        <th class="px-6 py-3 text-left font-medium text-slate-700">Description</th>
                        <th class="px-6 py-3 text-right font-medium text-slate-700">Amount</th>
                        <th class="px-6 py-3 text-right font-medium text-slate-700">VAT</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($expense->lineItems as $item)
                        <tr>
                            <td class="px-6 py-3">
                                @php
                                    $typeColors = ['business' => 'bg-teal/10 text-teal-dark', 'personal' => 'bg-amber-50 text-amber-600'];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$item->line_type] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($item->line_type) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-ink">{{ $item->description }}</td>
                            <td class="px-6 py-3 text-right font-medium text-ink">£{{ number_format($item->amount, 2) }}</td>
                            <td class="px-6 py-3 text-right text-slate-600">£{{ number_format($item->vat_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Documents --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-display font-semibold text-ink">Documents</h2>
        </div>
        @if($expense->documents->count() > 0)
            <div class="space-y-2">
                @foreach($expense->documents as $doc)
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
</div>
