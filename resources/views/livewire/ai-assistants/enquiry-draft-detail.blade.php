<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.ai.assistants.index') }}" wire:navigate class="text-xs font-medium text-copper hover:underline">&larr; AI Assistants</a>
            </div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Enquiry Draft Assistant</h1>
            <p class="mt-1 text-sm text-slate-500">AI-generated draft responses to customer enquiries</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Drafts</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalDrafts) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Linked to Enquiries</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($linkedDrafts) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Tokens</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalTokens) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Not Linked</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalDrafts - $linkedDrafts) }}</p>
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
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Drafts</th>
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
                        <td class="px-6 py-3 text-right text-slate-900 font-medium">{{ number_format($stat['drafts']) }}</td>
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
            <h3 class="text-sm font-semibold text-slate-900">Draft Generations</h3>
        </div>

        <div class="p-4 border-b border-slate-100">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Subject, summary or user..."
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
                <span class="text-xs text-slate-400">{{ $drafts->total() }} records</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            @if($drafts->count() > 0)
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Enquiry</th>
                            <th class="px-6 py-3 font-medium text-slate-700">User</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Tone</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Provider / Model</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Confidence</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Tokens</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Date</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($drafts as $draft)
                            @php $dt = ($draft->input_tokens ?? 0) + ($draft->output_tokens ?? 0); @endphp
                            <tr class="hover:bg-slate-50 transition-colors {{ $draft->status === 'failed' ? 'bg-red-50/50' : '' }}">
                                <td class="px-6 py-4">
                                    @if($draft->enquiry)
                                        <a href="{{ route('enquiries.show', $draft->enquiry) }}" wire:navigate
                                           class="text-sm font-medium text-copper hover:underline">
                                            #{{ $draft->enquiry->id }}
                                        </a>
                                    @else
                                        <span class="text-sm text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600 whitespace-nowrap">{{ $draft->user?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 capitalize">{{ $draft->tone ?? '—' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($draft->provider)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $draft->provider }}/{{ $draft->model }}
                                        </span>
                                    @else
                                        <span class="text-slate-300">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($draft->confidence === 'high')
                                        <span class="inline-flex items-center rounded-full bg-teal/10 px-2.5 py-0.5 text-xs font-medium text-teal-dark border border-teal/20">High</span>
                                    @elseif($draft->confidence === 'medium')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 border border-amber-200">Medium</span>
                                    @elseif($draft->confidence === 'low')
                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 border border-red-200">Low</span>
                                    @else
                                        <span class="text-slate-300">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-slate-600 font-mono">{{ $dt > 0 ? number_format($dt) : '—' }}</td>
                                <td class="px-6 py-4 text-right text-slate-500 text-xs whitespace-nowrap">{{ $draft->created_at->format('d M Y, H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                            x-on:click="$wire.viewDraft({{ $draft->id }}).then(() => $dispatch('open-modal', 'view-draft'))"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors"
                                            title="View draft">
                                        <x-lucide-eye class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-slate-200">
                    {{ $drafts->links() }}
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                        <x-lucide-file-text class="w-6 h-6 text-slate-400" />
                    </div>
                    <h3 class="text-sm font-medium text-slate-900">No draft generations found</h3>
                    <p class="mt-1 text-sm text-slate-500">Try adjusting your search or date filters.</p>
                </div>
            @endif
        </div>
    </div>

    <x-modal name="view-draft" maxWidth="2xl" focusable>
        @if($viewingDraft)
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-display font-semibold text-slate-900">Draft Details</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $viewingDraft->user?->name ?? 'Unknown' }} &middot;
                        {{ $viewingDraft->created_at->format('d M Y, H:i') }}
                        @if($viewingDraft->tone) &middot; Tone: {{ ucfirst($viewingDraft->tone) }} @endif
                    </p>
                </div>
                <button x-on:click="$wire.closeDraft(); $dispatch('close-modal', 'view-draft')"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <div class="space-y-4">
                @if($viewingDraft->enquiry)
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Related Enquiry</label>
                    <a href="{{ route('enquiries.show', $viewingDraft->enquiry) }}" wire:navigate
                       class="text-sm text-copper hover:underline">
                        Enquiry #{{ $viewingDraft->enquiry->id }}
                        @if($viewingDraft->enquiry->customer)
                            — {{ $viewingDraft->enquiry->customer->name }}
                        @endif
                    </a>
                </div>
                @endif

                <div class="flex items-center gap-2">
                    <label class="block text-xs font-medium text-slate-500">Confidence:</label>
                    @if($viewingDraft->confidence === 'high')
                        <span class="inline-flex items-center rounded-full bg-teal/10 px-2.5 py-0.5 text-xs font-medium text-teal-dark border border-teal/20">High</span>
                    @elseif($viewingDraft->confidence === 'medium')
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 border border-amber-200">Medium</span>
                    @elseif($viewingDraft->confidence === 'low')
                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 border border-red-200">Low</span>
                    @else
                        <span class="text-xs text-slate-400">&mdash;</span>
                    @endif
                </div>

                @if($viewingDraft->summary)
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Summary</label>
                    <p class="text-sm text-slate-900 bg-slate-50 border border-slate-200 rounded-lg p-3">{{ $viewingDraft->summary }}</p>
                </div>
                @endif

                @if($viewingDraft->draft_subject || $viewingDraft->draft_body)
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Draft Email</label>
                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                        @if($viewingDraft->draft_subject)
                        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                            <p class="text-sm font-medium text-slate-900">
                                <span class="text-xs text-slate-400 font-normal">Subject: </span>{{ $viewingDraft->draft_subject }}
                            </p>
                        </div>
                        @endif
                        @if($viewingDraft->draft_body)
                        <div class="px-4 py-3 text-sm text-slate-700 leading-relaxed max-h-64 overflow-y-auto">{!! nl2br(e($viewingDraft->draft_body)) !!}</div>
                        @endif
                    </div>
                </div>
                @endif

                @if(!empty($viewingDraft->suggested_next_steps))
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Suggested Next Steps</label>
                    <ul class="space-y-1">
                        @foreach($viewingDraft->suggested_next_steps as $step)
                            <li class="flex items-start gap-2 text-sm text-slate-700">
                                <x-lucide-arrow-right class="w-3.5 h-3.5 text-copper mt-0.5 shrink-0" />
                                <span>{{ $step }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if(!empty($viewingDraft->knowledge_gaps))
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Knowledge Gaps</label>
                    <ul class="space-y-1">
                        @foreach($viewingDraft->knowledge_gaps as $gap)
                            <li class="flex items-start gap-2 text-sm text-amber-700">
                                <x-lucide-alert-triangle class="w-3.5 h-3.5 mt-0.5 shrink-0" />
                                <span>{{ $gap }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if($viewingDraft->status === 'failed' && $viewingDraft->error_message)
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-red-700 mb-1">Error:</p>
                    <p class="text-sm text-red-600">{{ $viewingDraft->error_message }}</p>
                </div>
                @endif

                @php
                    $dInput = $viewingDraft->input_tokens ?? 0;
                    $dOutput = $viewingDraft->output_tokens ?? 0;
                    $dConfig = $viewingDraft->provider ? App\Models\AiModelConfig::where('provider', $viewingDraft->provider)->where('model', $viewingDraft->model)->first() : null;
                    $dCost = null;
                    if ($dConfig && $dConfig->input_price !== null && $dConfig->output_price !== null) {
                        $dCost = ($dInput / 1_000_000 * $dConfig->input_price) + ($dOutput / 1_000_000 * $dConfig->output_price);
                    }
                @endphp

                <div class="grid grid-cols-3 gap-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="text-center">
                        <p class="text-xs text-slate-400">Input Tokens</p>
                        <p class="text-sm font-semibold text-slate-900">{{ number_format($dInput) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-slate-400">Output Tokens</p>
                        <p class="text-sm font-semibold text-slate-900">{{ number_format($dOutput) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-slate-400">Est. Cost</p>
                        <p class="text-sm font-semibold text-slate-900 font-mono">
                            {{ $dCost !== null ? '$'.number_format($dCost, 4) : '—' }}
                        </p>
                    </div>
                </div>

                @if($viewingDraft->prompt_data)
                <div x-data="{ showPrompt: false }">
                    <button @click="showPrompt = !showPrompt"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-copper hover:text-copper-dark transition-colors">
                        <x-lucide-chevron-right class="w-3.5 h-3.5" x-bind:class="showPrompt ? 'rotate-90' : ''" />
                        <span x-text="showPrompt ? 'Hide prompt' : 'Show prompt'"></span>
                    </button>
                    <div x-show="showPrompt" x-collapse class="mt-2">
                        <pre class="text-xs font-mono bg-slate-900 text-slate-100 rounded-lg p-4 overflow-x-auto max-h-64 overflow-y-auto">{{ $viewingDraft->prompt_data }}</pre>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </x-modal>
</div>
