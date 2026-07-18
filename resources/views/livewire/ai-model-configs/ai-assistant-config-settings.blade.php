<div class="max-w-2xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Assistant Settings</h1>
        <p class="mt-1 text-sm text-slate-500">Assign which model config each AI assistant should use</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center shrink-0">
                    <x-lucide-message-square-more class="w-5 h-5 text-copper" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Chat Agent</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Powers the staff chat widget. Answers questions about customers, quotes, orders, and the business.</p>
                    <div class="mt-4">
                        <select wire:model="chat_agent_config_id" id="chat_agent_config_id" class="w-full max-w-xs rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                            <option value="">None — use env defaults</option>
                            @foreach($configs as $config)
                                <option value="{{ $config->id }}">{{ $config->label }} ({{ $config->provider }}/{{ $config->model }})</option>
                            @endforeach
                        </select>
                        @error('chat_agent_config_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center shrink-0">
                    <x-lucide-link class="w-5 h-5 text-copper" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Product URL Extractor</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Extracts product data (name, specs, price) from supplier website URLs.</p>
                    <div class="mt-4">
                        <select wire:model="product_url_extractor_config_id" id="product_url_extractor_config_id" class="w-full max-w-xs rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                            <option value="">None — use env defaults</option>
                            @foreach($configs as $config)
                                <option value="{{ $config->id }}">{{ $config->label }} ({{ $config->provider }}/{{ $config->model }})</option>
                            @endforeach
                        </select>
                        @error('product_url_extractor_config_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center shrink-0">
                    <x-lucide-bot class="w-5 h-5 text-copper" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Enquiry Draft Assistant</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Generates draft email responses for customer enquiries. Staff review and edit before sending — never sends automatically.</p>
                    <div class="mt-4">
                        <select wire:model="enquiry_draft_assistant_config_id" id="enquiry_draft_assistant_config_id" class="w-full max-w-xs rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                            <option value="">None — use env defaults</option>
                            @foreach($configs as $config)
                                <option value="{{ $config->id }}">{{ $config->label }} ({{ $config->provider }}/{{ $config->model }})</option>
                            @endforeach
                        </select>
                        @error('enquiry_draft_assistant_config_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center shrink-0">
                    <x-lucide-receipt class="w-5 h-5 text-copper" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Expenses Extractor (Text)</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Extracts invoice/receipt data from uploaded PDFs and text files. Used when text can be read directly.</p>
                    <div class="mt-4">
                        <select wire:model="expenses_extractor_config_id" id="expenses_extractor_config_id" class="w-full max-w-xs rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                            <option value="">None — use env defaults</option>
                            @foreach($configs as $config)
                                <option value="{{ $config->id }}">{{ $config->label }} ({{ $config->provider }}/{{ $config->model }})</option>
                            @endforeach
                        </select>
                        @error('expenses_extractor_config_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center shrink-0">
                    <x-lucide-image class="w-5 h-5 text-copper" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Expenses Extractor (Vision)</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Vision-capable model used when uploading images (JPG/PNG) of invoices or receipts. Only models marked as having vision support are listed.</p>
                    <div class="mt-4">
                        <select wire:model="expenses_extractor_vision_config_id" id="expenses_extractor_vision_config_id" class="w-full max-w-xs rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                            <option value="">None — use env defaults</option>
                            @foreach($visionConfigs as $config)
                                <option value="{{ $config->id }}">{{ $config->label }} ({{ $config->provider }}/{{ $config->model }})</option>
                            @endforeach
                        </select>
                        @error('expenses_extractor_vision_config_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>Save Settings</span>
                <span wire:loading>Saving...</span>
            </button>
        </div>
    </form>
</div>
