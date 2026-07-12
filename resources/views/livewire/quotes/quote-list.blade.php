<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Quotes</h1>
            <p class="mt-1 text-sm text-slate-500">Manage customer quotes and proposals</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('sample-quotes.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                <x-lucide-copy class="w-4 h-4" />
                Templates
            </a>
            <a href="{{ route('quotes.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                <x-lucide-file-plus class="w-4 h-4" />
                New Quote
            </a>
        </div>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-3">
        <div class="relative max-w-md flex-1">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by reference or customer..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
        </div>
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3 py-2.5">
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="accepted">Accepted</option>
            <option value="declined">Declined</option>
            <option value="expired">Expired</option>
        </select>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($quotes->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Reference</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Customer</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Total</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Date</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($quotes as $quote)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="font-medium text-slate-900 hover:text-copper transition-colors font-mono text-xs">
                                        {{ $quote->reference_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $quote->customer->name }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $quote->status === 'draft' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                                        {{ $quote->status === 'sent' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                        {{ $quote->status === 'accepted' ? 'bg-teal/10 text-teal-dark border border-teal/20' : '' }}
                                        {{ $quote->status === 'declined' ? 'bg-red-50 text-red-700 border border-red-200' : '' }}
                                        {{ $quote->status === 'expired' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                    ">
                                        {{ ucfirst($quote->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-slate-900">
                                    £{{ number_format($quote->grand_total, 2) }}
                                </td>
                                <td class="px-6 py-4 text-right text-xs text-slate-400">
                                    {{ $quote->created_at->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('quotes.pdf.download', $quote) }}" class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors" title="Download PDF">
                                            <x-lucide-download class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('quotes.edit', $quote) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $quote->id }})" wire:confirm="Delete this quote?" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $quotes->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-file-text class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No quotes yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Create your first quote or clone from a sample template.</p>
            </div>
        @endif
    </div>
</div>
