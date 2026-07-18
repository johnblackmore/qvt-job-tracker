<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.ai.assistants.index') }}" wire:navigate class="text-xs font-medium text-copper hover:underline">&larr; AI Assistants</a>
            </div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Chat Agent</h1>
            <p class="mt-1 text-sm text-slate-500">Staff chat widget conversations and usage</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Conversations</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalConversations) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Messages</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalMessages) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Tokens</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalTokens) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Staff Users</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $uniqueUsers }}</p>
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
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Conversations</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Messages</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Total Tokens</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Avg / Conv</th>
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
                        <td class="px-6 py-3 text-right text-slate-900 font-medium">{{ number_format($stat['conversations']) }}</td>
                        <td class="px-6 py-3 text-right text-slate-600">{{ number_format($stat['messages']) }}</td>
                        <td class="px-6 py-3 text-right text-slate-900 font-mono">{{ number_format($stat['total_tokens']) }}</td>
                        <td class="px-6 py-3 text-right text-slate-500 font-mono">{{ number_format($stat['avg_tokens']) }}</td>
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

    @if($userStats->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-900">Staff Usage</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 font-medium text-slate-700">User</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Conversations</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Messages</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Total Tokens</th>
                        <th class="px-6 py-3 font-medium text-slate-700 text-right">Est. Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($userStats as $stat)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-copper/15 flex items-center justify-center shrink-0">
                                    <span class="text-xs font-medium text-copper-dark">{{ substr($stat['user']->name ?? '?', 0, 1) }}</span>
                                </div>
                                <span class="font-medium text-slate-900">{{ $stat['user']->name ?? 'Unknown' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-right text-slate-900 font-medium">{{ number_format($stat['conversations']) }}</td>
                        <td class="px-6 py-3 text-right text-slate-600">{{ number_format($stat['messages']) }}</td>
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
            <h3 class="text-sm font-semibold text-slate-900">Conversations</h3>
        </div>

        <div class="p-4 border-b border-slate-100">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Title or user..."
                           class="w-full rounded-lg border-slate-300 text-slate-900 placeholder-slate-400 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
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
        </div>

        <div class="overflow-x-auto">
            @if($conversations->count() > 0)
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700 cursor-pointer hover:text-copper transition-colors" wire:click="sortBy('title')">
                                Title
                                @if($sortField === 'title') <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" /> @endif
                            </th>
                            <th class="px-6 py-3 font-medium text-slate-700">User</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Provider / Model</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Messages</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Tokens</th>
                            <th class="px-6 py-3 font-medium text-slate-700 cursor-pointer hover:text-copper transition-colors" wire:click="sortBy('created_at')">
                                Date
                                @if($sortField === 'created_at') <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3 inline" /> @endif
                            </th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($conversations as $conversation)
                            @php
                                $msgCount = $conversation->messages->count();
                                $tokenSum = $conversation->messages->sum('input_tokens') + $conversation->messages->sum('output_tokens');
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 max-w-[200px] truncate">{!! Str::markdown($conversation->title ?? 'Untitled') !!}</div>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $conversation->user?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $conversation->provider }}/{{ $conversation->model }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-slate-600">{{ number_format($msgCount) }}</td>
                                <td class="px-6 py-4 text-right text-slate-600 font-mono">{{ number_format($tokenSum) }}</td>
                                <td class="px-6 py-4 text-slate-500 text-xs whitespace-nowrap">{{ $conversation->created_at->format('d M Y, H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                            x-on:click="$wire.viewConversation({{ $conversation->id }}).then(() => $dispatch('open-modal', 'view-conversation'))"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors"
                                            title="View conversation">
                                        <x-lucide-eye class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-slate-200">
                    {{ $conversations->links() }}
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                        <x-lucide-message-square-more class="w-6 h-6 text-slate-400" />
                    </div>
                    <h3 class="text-sm font-medium text-slate-900">No conversations found</h3>
                    <p class="mt-1 text-sm text-slate-500">Try adjusting your search or date filters.</p>
                </div>
            @endif
        </div>
    </div>

    <x-modal name="view-conversation" maxWidth="2xl" focusable>
        @if($viewingConversation)
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-display font-semibold text-slate-900">{!! Str::markdown($viewingConversation->title ?? 'Untitled Conversation') !!}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $viewingConversation->user?->name ?? 'Unknown' }} &middot;
                        {{ $viewingConversation->created_at->format('d M Y, H:i') }} &middot;
                        {{ $viewingConversation->provider }}/{{ $viewingConversation->model }}
                    </p>
                </div>
                <button x-on:click="$wire.closeConversation(); $dispatch('close-modal', 'view-conversation')"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                @foreach($viewingConversation->messages as $message)
                    <div class="chat {{ $message->role === 'user' ? 'chat-end' : 'chat-start' }}">
                        <div class="chat-bubble text-sm {{ $message->role === 'user' ? 'bg-copper text-white' : 'bg-white border border-slate-200 text-slate-700 shadow-sm' }}">
                            @if($message->content)
                                @if($message->role === 'assistant')
                                    <div class="prose prose-sm max-w-none">{!! Str::markdown($message->content) !!}</div>
                                @else
                                    {{ $message->content }}
                                @endif
                            @endif
                            @if($message->tool_calls)
                                <div class="mt-2 pt-2 border-t {{ $message->role === 'user' ? 'border-copper/30' : 'border-slate-200' }}">
                                    <p class="text-xs font-medium {{ $message->role === 'user' ? 'text-copper-light' : 'text-slate-400' }}">Tool calls:</p>
                                    @foreach($message->tool_calls as $toolCall)
                                        <code class="text-xs block mt-0.5 {{ $message->role === 'user' ? 'text-white/80' : 'text-slate-500' }}">{{ is_string($toolCall) ? $toolCall : ($toolCall['name'] ?? json_encode($toolCall)) }}</code>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @php
                $totalInput = $viewingConversation->messages->sum('input_tokens');
                $totalOutput = $viewingConversation->messages->sum('output_tokens');
                $convTokens = $totalInput + $totalOutput;

                $config = App\Models\AiModelConfig::where('provider', $viewingConversation->provider)
                    ->where('model', $viewingConversation->model)->first();
                $convCost = null;
                if ($config && $config->input_price !== null && $config->output_price !== null) {
                    $convCost = ($totalInput / 1_000_000 * $config->input_price) + ($totalOutput / 1_000_000 * $config->output_price);
                }
            @endphp

            <div class="mt-4 pt-4 border-t border-slate-200 grid grid-cols-4 gap-4 text-center">
                <div>
                    <p class="text-xs text-slate-400">Messages</p>
                    <p class="text-sm font-semibold text-slate-900">{{ $viewingConversation->messages->count() }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Input Tokens</p>
                    <p class="text-sm font-semibold text-slate-900">{{ number_format($totalInput) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Output Tokens</p>
                    <p class="text-sm font-semibold text-slate-900">{{ number_format($totalOutput) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Est. Cost</p>
                    <p class="text-sm font-semibold text-slate-900 font-mono">
                        {{ $convCost !== null ? '$'.number_format($convCost, 4) : '—' }}
                    </p>
                </div>
            </div>
        </div>
        @endif
    </x-modal>
</div>
