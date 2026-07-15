<div>
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('customers.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-copper transition-colors">
                Customers
            </a>
            <x-lucide-chevron-right class="w-4 h-4 text-slate-400" />
            <span class="text-sm text-slate-900 font-medium">{{ $customer->name }}</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">{{ $customer->name }}</h1>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-500">
                    @if($customer->email)
                        <span class="flex items-center gap-1">
                            <x-lucide-mail class="w-3.5 h-3.5" />
                            {{ $customer->email }}
                        </span>
                    @endif
                    @if($customer->phone)
                        <span class="flex items-center gap-1">
                            <x-lucide-phone class="w-3.5 h-3.5" />
                            {{ $customer->phone }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('customers.edit', $customer) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <x-lucide-pencil class="w-4 h-4" />
                    Edit
                </a>
            </div>
        </div>
        @if($customer->address)
            <p class="mt-2 text-sm text-slate-500 flex items-center gap-1">
                <x-lucide-map-pin class="w-3.5 h-3.5" />
                {{ $customer->address }}
            </p>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Left column --}}
        <div class="xl:col-span-2 space-y-6">
            {{-- Vehicles --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-base font-display font-semibold text-slate-900">Vehicles</h2>
                    <a href="{{ route('customers.vehicles.create', $customer) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-copper hover:text-copper transition-colors">
                        <x-lucide-plus class="w-4 h-4" />
                        Add Vehicle
                    </a>
                </div>
                @if($customer->vehicles->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach($customer->vehicles as $vehicle)
                            <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                                        <x-lucide-car class="w-5 h-5 text-slate-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">
                                            {{ $vehicle->make }} {{ $vehicle->model }}
                                            @if($vehicle->year)
                                                <span class="text-slate-500 font-normal">({{ $vehicle->year }})</span>
                                            @endif
                                        </p>
                                        @if($vehicle->registration)
                                            <p class="text-xs text-slate-500 mt-0.5">Reg: {{ $vehicle->registration }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('customers.vehicles.edit', [$customer, $vehicle]) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                        <x-lucide-pencil class="w-4 h-4" />
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <p class="text-sm text-slate-500">No vehicles registered for this customer yet.</p>
                        <a href="{{ route('customers.vehicles.create', $customer) }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-copper hover:text-copper">
                            <x-lucide-plus class="w-4 h-4" />
                            Add first vehicle
                        </a>
                    </div>
                @endif
            </div>

            {{-- Enquiries --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-base font-display font-semibold text-slate-900">Enquiries</h2>
                    <a href="{{ route('enquiries.create', ['customerId' => $customer->id]) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-copper hover:text-copper transition-colors">
                        <x-lucide-plus class="w-4 h-4" />
                        Log Enquiry
                    </a>
                </div>
                @if($customer->enquiries->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach($customer->enquiries as $enquiry)
                            <a href="{{ route('enquiries.show', $enquiry) }}" wire:navigate class="block px-6 py-4 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                            {{ $enquiry->status === 'new' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                            {{ $enquiry->status === 'responded' ? 'bg-teal/10 text-teal-dark border border-teal/20' : '' }}
                                            {{ $enquiry->status === 'in_progress' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                            {{ $enquiry->status === 'closed' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                                        ">
                                            {{ ucfirst(str_replace('_', ' ', $enquiry->status)) }}
                                        </span>
                                        <span class="text-xs text-slate-400">{{ $enquiry->created_at->format('d M Y') }}</span>
                                    </div>
                                    <span class="text-xs text-slate-400">{{ $enquiry->source }}</span>
                                </div>
                                @if($enquiry->subject)
                                    <p class="text-sm font-medium text-slate-900">{{ $enquiry->subject }}</p>
                                @endif
                                <p class="text-sm text-slate-600 mt-1 whitespace-pre-line">{{ $enquiry->message }}</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <p class="text-sm text-slate-500">No enquiries logged for this customer yet.</p>
                    </div>
                @endif
            </div>

            {{-- Quotes --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-base font-display font-semibold text-slate-900">Quotes</h2>
                    <a href="{{ route('quotes.create', ['customerId' => $customer->id]) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-copper hover:text-copper transition-colors">
                        <x-lucide-plus class="w-4 h-4" />
                        Add Quote
                    </a>
                </div>
                @if($customer->quotes->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach($customer->quotes as $quote)
                            <a href="{{ route('quotes.show', $quote->id) }}" wire:navigate class="px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                                        <x-lucide-file-text class="w-5 h-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ $quote->reference_number }}</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Created {{ $quote->created_at->format('d M Y') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium text-slate-900">&pound;{{ number_format($quote->grand_total, 2) }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $quote->status === 'draft' ? 'bg-slate-100 text-slate-700 border border-slate-200' : '' }}
                                        {{ $quote->status === 'sent' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                        {{ $quote->status === 'accepted' ? 'bg-teal/10 text-teal-dark border border-teal/20' : '' }}
                                        {{ $quote->status === 'declined' ? 'bg-red-50 text-red-700 border border-red-200' : '' }}
                                        {{ $quote->status === 'converted' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                    ">
                                        {{ ucfirst($quote->status) }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <p class="text-sm text-slate-500">No quotes for this customer yet.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right column --}}
        <div class="space-y-6">
            {{-- Notes card --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Notes</h2>
                @if($customer->notes)
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $customer->notes }}</p>
                @else
                    <p class="text-sm text-slate-400 italic">No notes added.</p>
                @endif
            </div>

            {{-- Stats --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-3">
                <h2 class="text-sm font-semibold text-slate-900">Overview</h2>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-500">Vehicles</span>
                    <span class="text-sm font-medium text-slate-900">{{ $customer->vehicles->count() }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-500">Enquiries</span>
                    <span class="text-sm font-medium text-slate-900">{{ $customer->enquiries->count() }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-500">Quotes</span>
                    <span class="text-sm font-medium text-slate-900">{{ $customer->quotes->count() }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
