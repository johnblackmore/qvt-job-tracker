<div>
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('quotes.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-copper transition-colors">Quotes</a>
            <x-lucide-chevron-right class="w-4 h-4 text-slate-400" />
            <span class="text-sm text-slate-900 font-medium font-mono">{{ $quote->reference_number }}</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Quote {{ $quote->reference_number }}</h1>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-500">
                    <span>{{ $quote->customer->name }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ $quote->status === 'draft' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                        {{ $quote->status === 'sent' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                        {{ $quote->status === 'accepted' ? 'bg-teal/10 text-teal-dark border border-teal/20' : '' }}
                        {{ $quote->status === 'declined' ? 'bg-red-50 text-red-700 border border-red-200' : '' }}
                        {{ $quote->status === 'expired' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                    ">
                        {{ ucfirst($quote->status) }}
                    </span>
                    @if($quote->valid_until)
                        <span class="text-xs">Valid until {{ $quote->valid_until->format('d M Y') }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('quotes.pdf.preview', $quote) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <x-lucide-eye class="w-4 h-4" />
                    Preview PDF
                </a>
                <a href="{{ route('quotes.pdf.download', $quote) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <x-lucide-download class="w-4 h-4" />
                    PDF
                </a>
                <button wire:click="openSendModal" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    <x-lucide-send class="w-4 h-4" />
                    Send Quote
                </button>
                <a href="{{ route('quotes.edit', $quote) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <x-lucide-pencil class="w-4 h-4" />
                    Edit
                </a>
                @if($quote->status === 'accepted')
                    <button wire:click="convertToOrder" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                        <x-lucide-clipboard-plus class="w-4 h-4" />
                        Convert to Order
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if($sendStatus)
        <div class="mb-4 rounded-lg px-4 py-3 text-sm {{ str_starts_with($sendStatus, 'sent') ? 'bg-teal/10 text-teal-dark border border-teal/20' : 'bg-red-50 text-red-700 border border-red-200' }}">
            {{ $sendStatus }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            {{-- Line items --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-base font-display font-semibold text-slate-900">Quote Items</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($quote->lineItems as $item)
                        <div class="px-6 py-4 flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                        {{ $item->line_type === 'product' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                        {{ $item->line_type === 'labour' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                        {{ $item->line_type === 'ad_hoc' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                                    ">
                                        {{ str_replace('_', ' ', $item->line_type) }}
                                    </span>
                                    <span class="text-sm font-medium text-slate-900">{{ $item->description }}</span>
                                </div>
                                @if($item->notes)
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $item->notes }}</p>
                                @endif
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ $item->quantity }} x £{{ number_format($item->unit_retail_price, 2) }}
                                </p>
                                <p class="text-sm font-semibold text-slate-900">
                                    £{{ number_format($item->line_total_retail, 2) }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($quote->notes)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">Internal Notes</h2>
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $quote->notes }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Customer card --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Customer</h2>
                <p class="text-sm font-medium text-slate-900">{{ $quote->customer->name }}</p>
                @if($quote->customer->email)
                    <p class="text-xs text-slate-500 mt-1">{{ $quote->customer->email }}</p>
                @endif
                @if($quote->customer->phone)
                    <p class="text-xs text-slate-500">{{ $quote->customer->phone }}</p>
                @endif
            </div>

            {{-- Totals --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Summary</h2>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-500">Subtotal</span>
                    <span class="text-sm font-medium text-slate-900">£{{ number_format($quote->total_retail, 2) }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-500">Labour</span>
                    <span class="text-sm font-medium text-slate-900">£{{ number_format($quote->labour_total, 2) }}</span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-base font-semibold text-slate-900">Grand total</span>
                    <span class="text-base font-bold text-copper-light">£{{ number_format($quote->grand_total, 2) }}</span>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs text-slate-400">Trade cost (internal)</span>
                    <span class="text-xs text-slate-400">£{{ number_format($quote->total_trade, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Send Quote Modal --}}
    @if($showSendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40" wire:click="closeSendModal"></div>
            <div class="relative bg-white rounded-xl border border-slate-200 shadow-lg max-w-lg w-full p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Send Quote to Customer</h3>
                    <button wire:click="closeSendModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">To</label>
                        <p class="text-sm text-slate-900">{{ $quote->customer->name }} &lt;{{ $quote->customer->email }}&gt;</p>
                    </div>

                    <div>
                        <label for="template" class="block text-sm font-medium text-slate-700 mb-1.5">Email template (optional)</label>
                        <select wire:model="selectedTemplateId" id="template" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                            <option value="">Default quote email</option>
                            @foreach($templates as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="customMessage" class="block text-sm font-medium text-slate-700 mb-1.5">Custom message (optional)</label>
                        <textarea wire:model="customMessage" id="customMessage" rows="3" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Add a personal note to the customer..."></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="closeSendModal" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">Cancel</button>
                    <button wire:click="sendQuote" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                        <span wire:loading.remove>Send Quote</span>
                        <span wire:loading>Sending...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
