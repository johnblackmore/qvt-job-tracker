<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Enquiries</h1>
            <p class="mt-1 text-sm text-slate-500">Track and manage customer enquiries</p>
        </div>
        <a href="{{ route('enquiries.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
            <x-lucide-plus-circle class="w-4 h-4" />
            Log Enquiry
        </a>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-3">
        <div class="relative max-w-md flex-1">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search enquiries..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm pl-9 pr-4 py-2.5" />
        </div>
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5">
            <option value="">All statuses</option>
            <option value="new">New</option>
            <option value="in_progress">In Progress</option>
            <option value="responded">Responded</option>
            <option value="closed">Closed</option>
        </select>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($enquiries->count() > 0)
            <div class="divide-y divide-slate-100">
                @foreach($enquiries as $enquiry)
                    <div class="px-6 py-4 hover:bg-slate-50 transition-colors">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $enquiry->status === 'new' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                        {{ $enquiry->status === 'responded' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : '' }}
                                        {{ $enquiry->status === 'in_progress' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                        {{ $enquiry->status === 'closed' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                                    ">
                                        {{ ucfirst(str_replace('_', ' ', $enquiry->status)) }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $enquiry->created_at->format('d M Y') }}</span>
                                    <span class="text-xs text-slate-400">{{ $enquiry->source }}</span>
                                </div>
                                @if($enquiry->customer)
                                    <p class="text-sm font-medium text-emerald-600">{{ $enquiry->customer->name }}</p>
                                @else
                                    <p class="text-sm font-medium text-slate-500">Unlinked enquiry</p>
                                @endif
                                @if($enquiry->subject)
                                    <p class="text-sm font-medium text-slate-900 mt-0.5">{{ $enquiry->subject }}</p>
                                @endif
                                <p class="text-sm text-slate-600 mt-0.5">{{ Str::limit($enquiry->message, 200) }}</p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                @if($enquiry->status === 'new' || $enquiry->status === 'in_progress')
                                    <button wire:click="markResponded({{ $enquiry->id }})" class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Mark as responded">
                                        <x-lucide-check-circle class="w-4 h-4" />
                                    </button>
                                @endif
                                <a href="{{ route('enquiries.edit', $enquiry) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                    <x-lucide-pencil class="w-4 h-4" />
                                </a>
                                <button wire:click="delete({{ $enquiry->id }})" wire:confirm="Delete this enquiry?" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $enquiries->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-inbox class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No enquiries yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Log your first customer enquiry to start tracking.</p>
            </div>
        @endif
    </div>
</div>
