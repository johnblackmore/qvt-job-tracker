<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Sample Quotes</h1>
            <p class="mt-1 text-sm text-slate-500">Templates you can clone and modify before sending to customers</p>
        </div>
        <a href="{{ route('sample-quotes.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
            <x-lucide-copy-plus class="w-4 h-4" />
            Create Template
        </a>
    </div>

    <div class="mb-6">
        <div class="relative max-w-md">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search templates..." class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm pl-9 pr-4 py-2.5" />
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($sampleQuotes->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Name</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Line Items</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($sampleQuotes as $sample)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900">{{ $sample->name }}</div>
                                    @if($sample->description)
                                        <div class="text-xs text-slate-500 mt-0.5">{{ Str::limit($sample->description, 80) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ count($sample->line_items ?? []) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button wire:click="toggleActive({{ $sample->id }})" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $sample->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                        {{ $sample->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('quotes.create-from-sample', $sample) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Clone to quote">
                                            <x-lucide-copy class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('sample-quotes.edit', $sample) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $sample->id }})" wire:confirm="Delete this sample quote template?" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
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
                {{ $sampleQuotes->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-copy class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No sample quotes yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Create your first template to quickly build quotes for common job types.</p>
            </div>
        @endif
    </div>
</div>
