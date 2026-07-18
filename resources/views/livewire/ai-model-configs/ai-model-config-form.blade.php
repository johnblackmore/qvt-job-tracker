<div class="max-w-2xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">{{ $config ? 'Edit Config' : 'New Config' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $config ? 'Update this AI model configuration' : 'Create a new provider/model pairing' }}</p>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div>
            <label for="label" class="block text-sm font-medium text-slate-700 mb-1.5">Label <span class="text-red-500">*</span></label>
            <input wire:model="label" id="label" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="OpenCode DeepSeek Flash Free" />
            @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="provider" class="block text-sm font-medium text-slate-700 mb-1.5">Provider <span class="text-red-500">*</span></label>
                <select wire:model="provider" id="provider" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                    <option value="">Select a provider</option>
                    @foreach($providers as $key)
                        <option value="{{ $key }}">{{ $key }}</option>
                    @endforeach
                </select>
                @error('provider') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="model" class="block text-sm font-medium text-slate-700 mb-1.5">Model <span class="text-red-500">*</span></label>
                <input wire:model="model" id="model" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5 font-mono" placeholder="deepseek-v4-flash-free" />
                @error('model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="api_type" class="block text-sm font-medium text-slate-700 mb-1.5">API Protocol</label>
            <select wire:model="api_type" id="api_type" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5">
                <option value="">OpenAI-compatible (default)</option>
                <option value="openai">OpenAI (genuine OpenAI models)</option>
                <option value="anthropic">Anthropic</option>
                <option value="google">Google Gemini</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">The API protocol this model uses. Leave as default for OpenAI-compatible models like DeepSeek, Groq, etc.</p>
            @error('api_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="input_price" class="block text-sm font-medium text-slate-700 mb-1.5">Input Price (per 1M tokens)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-slate-500 font-medium">$</span>
                    <input wire:model="input_price" id="input_price" type="number" step="0.0001" min="0"
                           class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm pl-7 pr-3.5 py-2.5"
                           placeholder="0.14" />
                </div>
                <p class="mt-1 text-xs text-slate-400">USD cost per million input tokens</p>
                @error('input_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="output_price" class="block text-sm font-medium text-slate-700 mb-1.5">Output Price (per 1M tokens)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-slate-500 font-medium">$</span>
                    <input wire:model="output_price" id="output_price" type="number" step="0.0001" min="0"
                           class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm pl-7 pr-3.5 py-2.5"
                           placeholder="0.28" />
                </div>
                <p class="mt-1 text-xs text-slate-400">USD cost per million output tokens</p>
                @error('output_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
            <textarea wire:model="description" id="description" rows="2" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Optional notes about this config..."></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-3">
            <p class="text-sm font-medium text-slate-700">Capabilities</p>
            <div class="flex items-center gap-3">
                <input wire:model="supports_text" id="supports_text" type="checkbox" class="rounded border-slate-300 text-copper focus:ring-copper cursor-pointer" />
                <label for="supports_text" class="text-sm font-medium text-slate-700 cursor-pointer">Supports text processing</label>
                @error('supports_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-3">
                <input wire:model="has_vision" id="has_vision" type="checkbox" class="rounded border-slate-300 text-copper focus:ring-copper cursor-pointer" />
                <label for="has_vision" class="text-sm font-medium text-slate-700 cursor-pointer">Supports vision (can process images)</label>
                @error('has_vision') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-3">
                <input wire:model="supports_audio" id="supports_audio" type="checkbox" class="rounded border-slate-300 text-copper focus:ring-copper cursor-pointer" />
                <label for="supports_audio" class="text-sm font-medium text-slate-700 cursor-pointer">Supports audio processing</label>
                @error('supports_audio') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-3">
                <input wire:model="supports_file_uploads" id="supports_file_uploads" type="checkbox" class="rounded border-slate-300 text-copper focus:ring-copper cursor-pointer" />
                <label for="supports_file_uploads" class="text-sm font-medium text-slate-700 cursor-pointer">Supports file uploads</label>
                @error('supports_file_uploads') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark focus:outline-none focus:ring-2 focus:ring-copper focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $config ? 'Save Changes' : 'Create Config' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('admin.ai.configs.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
