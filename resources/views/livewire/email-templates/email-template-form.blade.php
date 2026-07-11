<div class="max-w-4xl">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ $template ? 'Edit Email Template' : 'Create Email Template' }}</h1>
        <p class="mt-1 text-sm text-slate-500">Build reusable email templates with variable placeholders</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Template name <span class="text-red-500">*</span></label>
                    <input wire:model="name" id="name" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="e.g. Quote Sent" />
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700 mb-1.5">Slug <span class="text-red-500">*</span></label>
                    <input wire:model="slug" id="slug" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5 font-mono" placeholder="quote-sent" />
                    @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="subject" class="block text-sm font-medium text-slate-700 mb-1.5">Subject <span class="text-red-500">*</span></label>
                <input wire:model="subject" id="subject" type="text" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5" placeholder="Your Quote from Quantock Van Tech — {{ quote_reference }}" />
                @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="body_html" class="block text-sm font-medium text-slate-700 mb-1.5">Body (HTML) <span class="text-red-500">*</span></label>
                <textarea wire:model="body_html" id="body_html" rows="12" required class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5 font-mono" placeholder="<p>Hi {{ customer_name }},</p>..."></textarea>
                @error('body_html') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="body_text" class="block text-sm font-medium text-slate-700 mb-1.5">Body (plain text, optional)</label>
                <textarea wire:model="body_text" id="body_text" rows="6" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5 font-mono" placeholder="Plain text fallback..."></textarea>
            </div>

            <div>
                <label for="variables_json" class="block text-sm font-medium text-slate-700 mb-1.5">Variables (JSON)</label>
                <textarea wire:model="variables_json" id="variables_json" rows="4" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3.5 py-2.5 font-mono" placeholder='["customer_name", "quote_reference", "grand_total"]'></textarea>
                <p class="mt-1 text-xs text-slate-500">List the variable names used in this template. Use <code class="bg-slate-100 px-1 rounded">{{ '{' . '{ variable_name }' . '}' }}</code> in subject and body.</p>
            </div>

            <div class="flex items-center gap-3">
                <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 size-4" />
                <label for="is_active" class="text-sm text-slate-700">Active template</label>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
                <span wire:loading.remove>{{ $template ? 'Save Changes' : 'Create Template' }}</span>
                <span wire:loading>Saving...</span>
            </button>
            <a href="{{ route('email-templates.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
</div>
