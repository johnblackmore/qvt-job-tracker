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
                            <tr class="hover:bg-slate-50 transition-colors cursor-pointer" wire:click="viewExtraction({{ $extraction->id }})">
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
                                    <button wire:click="viewExtraction({{ $extraction->id }})" class="text-xs font-medium text-copper hover:underline">View</button>
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
    @if($viewingExtraction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 w-full max-w-2xl max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-display font-semibold text-ink">Extraction Details</h3>
                    <button wire:click="closeExtraction" class="text-slate-400 hover:text-slate-600">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div>
                        <dt class="text-slate-500">Date</dt>
                        <dd class="font-medium text-ink">{{ $viewingExtraction->created_at->format('j M Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">User</dt>
                        <dd class="font-medium text-ink">{{ $viewingExtraction->user?->name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $viewingExtraction->status === 'completed' ? 'bg-teal/10 text-teal-dark' : 'bg-red-50 text-red-600' }}">
                                {{ ucfirst($viewingExtraction->status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Provider / Model</dt>
                        <dd class="font-medium text-ink">{{ $viewingExtraction->provider }} / {{ $viewingExtraction->model }}</dd>
                    </div>
                    @if($viewingExtraction->source_url)
                        <div class="col-span-2">
                            <dt class="text-slate-500">Source</dt>
                            <dd class="font-medium text-ink break-all">{{ $viewingExtraction->source_url }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-slate-500">Input Tokens</dt>
                        <dd class="font-medium text-ink">{{ number_format($viewingExtraction->input_tokens ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Output Tokens</dt>
                        <dd class="font-medium text-ink">{{ number_format($viewingExtraction->output_tokens ?? 0) }}</dd>
                    </div>
                </dl>

                @if($viewingExtraction->extracted_data)
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-ink mb-2">Extracted Data</h4>
                        <pre class="bg-slate-50 rounded-lg p-4 text-xs text-slate-700 overflow-x-auto max-h-60">{{ json_encode($viewingExtraction->extracted_data, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @endif

                @if($viewingExtraction->error_message)
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-red-600 mb-2">Error</h4>
                        <p class="bg-red-50 rounded-lg p-3 text-sm text-red-700">{{ $viewingExtraction->error_message }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
