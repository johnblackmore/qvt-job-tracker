<div>
    <div class="mb-8">
        <a href="{{ route('admin.ai.assistants.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 mb-2 inline-block">&larr; Back to AI Assistants</a>
        <h1 class="text-2xl font-display font-semibold text-ink tracking-tight">Expenses Assistant</h1>
        <p class="mt-1 text-sm text-slate-500">AI-powered extraction of invoice and receipt data</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total Extractions</p>
            <p class="text-2xl font-display font-semibold text-ink">{{ number_format($totalExtractions) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Successful</p>
            <p class="text-2xl font-display font-semibold text-teal-dark">{{ number_format($successCount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Failed</p>
            <p class="text-2xl font-display font-semibold text-red-600">{{ number_format($failedCount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total Tokens</p>
            <p class="text-2xl font-display font-semibold text-ink">{{ number_format($totalTokens) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1 max-w-md">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search extractions..." class="w-full rounded-lg border-slate-300 text-ink placeholder-slate-400 focus:border-copper focus:ring-copper text-sm pl-9 pr-4 py-2.5" />
        </div>
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
            <option value="">All Statuses</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="processing">Processing</option>
        </select>
        <input wire:model.live="dateFrom" type="date" placeholder="From" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
        <input wire:model.live="dateTo" type="date" placeholder="To" class="rounded-lg border-slate-300 text-ink focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
        @if($search || $status || $dateFrom || $dateTo)
            <button wire:click="clearFilters" class="text-sm text-copper hover:underline">Clear</button>
        @endif
    </div>

    {{-- Provider/Model Stats --}}
    @if($providerModelStats->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-sm font-display font-semibold text-ink">Provider & Model Usage</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-slate-700">Provider</th>
                            <th class="px-6 py-3 text-left font-medium text-slate-700">Model</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Extractions</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Success</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Rate</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Tokens</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($providerModelStats as $stat)
                            <tr>
                                <td class="px-6 py-3 text-ink">{{ $stat['provider'] }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-ink">{{ $stat['model'] }}</td>
                                <td class="px-6 py-3 text-right text-ink">{{ $stat['extractions'] }}</td>
                                <td class="px-6 py-3 text-right text-teal-dark">{{ $stat['successful'] }}</td>
                                <td class="px-6 py-3 text-right">{{ $stat['success_rate'] }}%</td>
                                <td class="px-6 py-3 text-right text-slate-600">{{ number_format($stat['total_tokens']) }}</td>
                                <td class="px-6 py-3 text-right text-slate-600">@if($stat['cost']) £{{ number_format($stat['cost'], 4) }} @else — @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Extraction Log --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-sm font-display font-semibold text-ink">Extraction Log</h3>
        </div>
        @if($extractions->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-slate-700">Date</th>
                            <th class="px-6 py-3 text-left font-medium text-slate-700">User</th>
                            <th class="px-6 py-3 text-left font-medium text-slate-700">Source</th>
                            <th class="px-6 py-3 text-center font-medium text-slate-700">Status</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-700">Tokens</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($extractions as $extraction)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-3 text-slate-600 text-xs">{{ $extraction->created_at->format('j M Y H:i') }}</td>
                                <td class="px-6 py-3 text-ink">{{ $extraction->user?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-3 text-slate-500 text-xs max-w-[200px] truncate">{{ $extraction->source_url ?? '—' }}</td>
                                <td class="px-6 py-3 text-center">
                                    @php
                                        $statusColors = ['completed' => 'bg-teal/10 text-teal-dark', 'failed' => 'bg-red-50 text-red-600', 'processing' => 'bg-blue-50 text-blue-600'];
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$extraction->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ ucfirst($extraction->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right text-slate-600 text-xs">{{ number_format(($extraction->input_tokens ?? 0) + ($extraction->output_tokens ?? 0)) }}</td>
                                <td class="px-6 py-3 text-right">
                                    <button x-on:click="$wire.viewExtraction({{ $extraction->id }}).then(() => $dispatch('open-modal', 'view-extraction'))"
                                            class="text-xs font-medium text-copper hover:underline">View</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $extractions->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-receipt class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-ink">No extractions yet</h3>
                <p class="mt-1 text-sm text-slate-500">Upload an invoice to start extracting expense data with AI.</p>
            </div>
        @endif
    </div>

    {{-- Extraction Detail Modal --}}
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
                @if($viewingExtraction->source_url)
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Source</label>
                    <a href="{{ $viewingExtraction->source_url }}" target="_blank"
                       class="text-sm text-copper hover:underline break-all">
                        {{ $viewingExtraction->source_url }}
                    </a>
                </div>
                @endif

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

                @if($viewingExtraction->error_message)
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
            </div>
        </div>
        @endif
    </x-modal>
</div>
