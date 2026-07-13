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
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
            <textarea wire:model="description" id="description" rows="2" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Optional notes about this config..."></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
