<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">AI Model Configs</h1>
            <p class="mt-1 text-sm text-slate-500">Manage provider/model pairings and assign them to AI assistants</p>
        </div>
        <a href="{{ route('admin.ai.configs.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
            <x-lucide-plus class="w-4 h-4" />
            New Config
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if($configs->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-700">Label</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Provider</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Model</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Input Price</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Output Price</th>
                            <th class="px-6 py-3 font-medium text-slate-700">Assigned To</th>
                            <th class="px-6 py-3 font-medium text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($configs as $config)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900">{{ $config->label }}</div>
                                    @if($config->description)
                                        <div class="text-xs text-slate-500 mt-0.5">{{ Str::limit($config->description, 60) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $config->provider }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 font-mono">{{ $config->model }}</code>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-slate-600 font-mono">
                                    {{ $config->input_price !== null ? '$'.number_format($config->input_price, 4) : '—' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-slate-600 font-mono">
                                    {{ $config->output_price !== null ? '$'.number_format($config->output_price, 4) : '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if($assignedChatAgent === $config->id)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-teal/10 px-2.5 py-0.5 text-xs font-medium text-teal-dark border border-teal/20">
                                                <x-lucide-message-square-more class="w-3 h-3" />
                                                Chat Agent
                                            </span>
                                        @endif
                                        @if($assignedUrlExtractor === $config->id)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-teal/10 px-2.5 py-0.5 text-xs font-medium text-teal-dark border border-teal/20">
                                                <x-lucide-link class="w-3 h-3" />
                                                URL Extractor
                                            </span>
                                        @endif
                                        @if($assignedDraftAssistant === $config->id)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-teal/10 px-2.5 py-0.5 text-xs font-medium text-teal-dark border border-teal/20">
                                                <x-lucide-bot class="w-3 h-3" />
                                                Draft Assistant
                                            </span>
                                        @endif
                                        @if($assignedChatAgent !== $config->id && $assignedUrlExtractor !== $config->id && $assignedDraftAssistant !== $config->id)
                                            <span class="text-xs text-slate-400">Not assigned</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            x-data
                                            @click="$dispatch('open-assign-modal', { configId: {{ $config->id }}, label: '{{ $config->label }}' })"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors"
                                            title="Assign to assistant"
                                        >
                                            <x-lucide-link class="w-4 h-4" />
                                        </button>
                                        <a href="{{ route('admin.ai.configs.edit', $config) }}" wire:navigate class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors">
                                            <x-lucide-pencil class="w-4 h-4" />
                                        </a>
                                        <button wire:click="delete({{ $config->id }})" wire:confirm="Delete this config? {{ ($assignedChatAgent === $config->id || $assignedUrlExtractor === $config->id || $assignedDraftAssistant === $config->id) ? 'It is currently assigned to an assistant and will fall back to env defaults. ' : '' }}This cannot be undone." class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-cpu class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No model configs yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Create your first provider/model pairing to get started, then assign it to an AI assistant.</p>
                <a href="{{ route('admin.ai.configs.create') }}" wire:navigate class="inline-flex items-center gap-2 mt-4 rounded-lg bg-copper px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                    <x-lucide-plus class="w-4 h-4" />
                    New Config
                </a>
            </div>
        @endif
    </div>

    {{-- Assign modal --}}
    <div
        x-data="{ open: false, configId: null, label: '' }"
        x-on:open-assign-modal.window="open = true; configId = $event.detail.configId; label = $event.detail.label"
        x-show="open"
        style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center"
    >
        <div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-black/30"></div>
        <div
            x-show="open"
            x-transition
            class="relative bg-white rounded-xl border border-slate-200 shadow-lg p-6 w-full max-w-sm mx-4"
        >
            <h3 class="text-sm font-semibold text-slate-900 mb-1">Assign Config</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="'Assign \"' + label + '\" to which assistant?'"></p>
            <div class="space-y-2">
                <button
                    @click="$wire.assignToAssistant(configId, 'chat-agent'); open = false"
                    class="flex items-center gap-3 w-full px-4 py-3 rounded-lg text-sm font-medium text-slate-700 hover:bg-copper/10 hover:text-copper border border-slate-200 hover:border-copper/30 transition-colors text-left"
                >
                    <x-lucide-message-square-more class="w-5 h-5 shrink-0 text-slate-400" />
                    <div>
                        <div>Chat Agent</div>
                        <div class="text-xs text-slate-400 font-normal">Powers the staff chat widget</div>
                    </div>
                </button>
                <button
                    @click="$wire.assignToAssistant(configId, 'product-url-extractor'); open = false"
                    class="flex items-center gap-3 w-full px-4 py-3 rounded-lg text-sm font-medium text-slate-700 hover:bg-copper/10 hover:text-copper border border-slate-200 hover:border-copper/30 transition-colors text-left"
                >
                    <x-lucide-link class="w-5 h-5 shrink-0 text-slate-400" />
                    <div>
                        <div>Product URL Extractor</div>
                        <div class="text-xs text-slate-400 font-normal">Extracts product data from URLs</div>
                    </div>
                </button>
                <button
                    @click="$wire.assignToAssistant(configId, 'enquiry-draft-assistant'); open = false"
                    class="flex items-center gap-3 w-full px-4 py-3 rounded-lg text-sm font-medium text-slate-700 hover:bg-copper/10 hover:text-copper border border-slate-200 hover:border-copper/30 transition-colors text-left"
                >
                    <x-lucide-bot class="w-5 h-5 shrink-0 text-slate-400" />
                    <div>
                        <div>Enquiry Draft Assistant</div>
                        <div class="text-xs text-slate-400 font-normal">Generates draft responses to enquiries</div>
                    </div>
                </button>
                <button @click="open = false" class="w-full px-4 py-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
