<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.ai.assistants.index') }}" wire:navigate class="text-xs font-medium text-copper hover:underline">&larr; AI Assistants</a>
            </div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Product URL Extractor</h1>
            <p class="mt-1 text-sm text-slate-500">Product data extraction from supplier URLs</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Extractions</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalExtractions) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Successful</p>
            <p class="mt-1 text-2xl font-bold text-teal-dark">{{ number_format($successCount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Failed</p>
            <p class="mt-1 text-2xl font-bold {{ $failedCount > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ number_format($failedCount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Tokens</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalTokens) }}</p>
        </div>
    </div>

    @if($providerModelStats->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-900">Provider / Model Breakdown</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 font-medium text-slate-700">Provider</th>
                        <th class="px-6 py-3 font-medium text-slate-700">Model</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Extractions</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Success Rate</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Total Tokens</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Est. Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($providerModelStats as $stat)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $stat['provider'] }}</span>
                        </td>
                        <td class="px-6 py-3">
                            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 font-mono">{{ $stat['model'] }}</code>
                        </td>
                        <td class="px-6 py-3 text-right text-slate-900 font-medium">{{ number_format($stat['extractions']) }}</td>
                        <td class="px-6 py-3 text-right">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $stat['success_rate'] >= 80 ? 'bg-teal/10 text-teal-dark border border-teal/20' : ($stat['success_rate'] >= 50 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-red-50 text-red-700 border border-red-200') }}">
                                {{ $stat['success_rate'] }}%
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right text-slate-900 font-mono">{{ number_format($stat['total_tokens']) }}</td>
                        <td class="px-6 py-3 text-right text-slate-600 font-mono">
                            @if($stat['cost'] !== null)
                                ${{ number_format($stat['cost'], 4) }}
                            @else
                                <span class="text-slate-300">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-900">Extractions</h3>
        </div>

        <div class="p-4 border-b border-slate-100">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="URL or user..."
                           class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                    <select wire:model.live="status"
                            class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                        <option value="">All statuses</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="processing">Processing</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
                    <input type="date" wire:model.live="dateFrom"
                           class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
                    <input type="date" wire:model.live="dateTo"
                           class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                </div>
            </div>
            <div class="mt-3 flex justify-between items-center">
                <button wire:click="clearFilters"
                        class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 transition-colors">
                    <x-lucide-x class="w-3.5 h-3.5" />
                    Clear filters
                </button>
                <span class="text-xs text-slate-400">{{ $extractions->total() }} records</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            @if($extractions->count() > 0)
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Source URL</th>
                            <th class="px-6 py-3 font-medium text-slate-700">User</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Provider / Model</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Tokens</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Date</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($extractions as $extraction)
                            <tr class="hover:bg-slate-50 transition-colors {{ $extraction->status === 'failed' ? 'bg-red-50/50' : '' }}">
                                <td class="px-6 py-4">
                                    <div class="max-w-[250px] truncate font-medium text-slate-900" title="{{ $extraction->source_url }}">
                                        {{ $extraction->source_url }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-600 whitespace-nowrap">{{ $extraction->user?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4">
                                    @if($extraction->provider)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $extraction->provider }}/{{ $extraction->model }}
                                        </span>
                                    @else
                                        <span class="text-slate-300">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($extraction->status === 'completed')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-teal/10 px-2.5 py-0.5 text-xs font-medium text-teal-dark border border-teal/20">
                                            <x-lucide-check class="w-3 h-3" />
                                            Completed
                                        </span>
                                    @elseif($extraction->status === 'failed')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 border border-red-200">
                                            <x-lucide-x class="w-3 h-3" />
                                            Failed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 border border-amber-200">
                                            Processing
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-slate-600 font-mono">
                                    @php $et = ($extraction->input_tokens ?? 0) + ($extraction->output_tokens ?? 0); @endphp
                                    {{ $et > 0 ? number_format($et) : '—' }}
                                </td>
                                <td class="px-6 py-4 text-right text-slate-500 text-xs whitespace-nowrap">{{ $extraction->created_at->format('d M Y, H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                            x-on:click="$wire.viewExtraction({{ $extraction->id }}).then(() => $dispatch('open-modal', 'view-extraction'))"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors"
                                            title="View extraction">
                                        <x-lucide-eye class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-slate-200">
                    {{ $extractions->links() }}
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                        <x-lucide-search class="w-6 h-6 text-slate-400" />
                    </div>
                    <h3 class="text-sm font-medium text-slate-900">No extractions found</h3>
                    <p class="mt-1 text-sm text-slate-500">Try adjusting your search or date filters.</p>
                </div>
            @endif
        </div>
    </div>

    <x-modal name="view-extraction" maxWidth="2xl" focusable>
        @if($viewingExtraction)
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-display font-semibold text-slate-900">Extraction Details</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $viewingExtraction->user?->name ?? 'Unknown' }} &middot;
                        {{ $viewingExtraction->created_at->format('d M Y, H:i') }}
                    </p>
                </div>
                <button x-on:click="$wire.closeExtraction(); $dispatch('close-modal', 'view-extraction')"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Source URL</label>
                    <a href="{{ $viewingExtraction->source_url }}" target="_blank"
                       class="text-sm text-copper hover:underline break-all">
                        {{ $viewingExtraction->source_url }}
                    </a>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                    @if($viewingExtraction->status === 'completed')
                        <span class="inline-flex items-center gap-1 rounded-full bg-teal/10 px-2.5 py-0.5 text-xs font-medium text-teal-dark border border-teal/20">
                            <x-lucide-check class="w-3 h-3" /> Completed
                        </span>
                    @elseif($viewingExtraction->status === 'failed')
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 border border-red-200">
                            <x-lucide-x class="w-3 h-3" /> Failed
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 border border-amber-200">Processing</span>
                    @endif
                </div>

                @if($viewingExtraction->status === 'failed' && $viewingExtraction->error_message)
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-red-700 mb-1">Error:</p>
                    <p class="text-sm text-red-600">{{ $viewingExtraction->error_message }}</p>
                </div>
                @endif

                @if($viewingExtraction->extracted_data)
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-2">Extracted Data</label>
                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-slate-200">
                                @foreach((array) $viewingExtraction->extracted_data as $key => $value)
                                    <tr class="even:bg-slate-50">
                                        <td class="px-4 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider align-top w-1/3">{{ $key }}</td>
                                        <td class="px-4 py-2.5 text-sm text-slate-900">
                                            @if(is_array($value) || is_object($value))
                                                <pre class="text-xs font-mono bg-slate-50 rounded p-2 border border-slate-200 overflow-x-auto whitespace-pre-wrap">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                @php
                    $eInput = $viewingExtraction->input_tokens ?? 0;
                    $eOutput = $viewingExtraction->output_tokens ?? 0;
                    $eConfig = $viewingExtraction->provider ? App\Models\AiModelConfig::where('provider', $viewingExtraction->provider)->where('model', $viewingExtraction->model)->first() : null;
                    $eCost = null;
                    if ($eConfig && $eConfig->input_price !== null && $eConfig->output_price !== null) {
                        $eCost = ($eInput / 1_000_000 * $eConfig->input_price) + ($eOutput / 1_000_000 * $eConfig->output_price);
                    }
                @endphp

                <div class="grid grid-cols-3 gap-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="text-center">
                        <p class="text-xs text-slate-400">Input Tokens</p>
                        <p class="text-sm font-semibold text-slate-900">{{ number_format($eInput) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-slate-400">Output Tokens</p>
                        <p class="text-sm font-semibold text-slate-900">{{ number_format($eOutput) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-slate-400">Est. Cost</p>
                        <p class="text-sm font-semibold text-slate-900 font-mono">
                            {{ $eCost !== null ? '$'.number_format($eCost, 4) : '—' }}
                        </p>
                    </div>
                </div>

                @if($viewingExtraction->prompt_data)
                <div x-data="{ showPrompt: false }">
                    <button @click="showPrompt = !showPrompt"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-copper hover:text-copper-dark transition-colors">
                        <x-lucide-chevron-right class="w-3.5 h-3.5" x-bind:class="showPrompt ? 'rotate-90' : ''" />
                        <span x-text="showPrompt ? 'Hide prompt' : 'Show prompt'"></span>
                    </button>
                    <div x-show="showPrompt" x-collapse class="mt-2">
                        <pre class="text-xs font-mono bg-slate-900 text-slate-100 rounded-lg p-4 overflow-x-auto max-h-64 overflow-y-auto">{{ $viewingExtraction->prompt_data }}</pre>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </x-modal>
</div>
