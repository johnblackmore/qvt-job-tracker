<div>
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">System Settings</h1>
        <p class="mt-1 text-sm text-slate-500">Configure email templates, AI integration, and other system preferences.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a
            href="{{ route('email-templates.index') }}"
            wire:navigate
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-copper/30 hover:shadow-md transition-all"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal/10">
                <x-lucide-mail-plus class="h-5 w-5 text-teal" />
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-slate-900">Email Templates</h3>
                <p class="mt-0.5 text-xs text-slate-500">Manage transactional email templates and content.</p>
            </div>
        </a>

        <a
            href="{{ route('admin.ai.configs.index') }}"
            wire:navigate
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-copper/30 hover:shadow-md transition-all"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal/10">
                <x-lucide-cpu class="h-5 w-5 text-teal" />
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-slate-900">AI Models</h3>
                <p class="mt-0.5 text-xs text-slate-500">Configure AI provider connections and model settings.</p>
            </div>
        </a>

        <a
            href="{{ route('admin.ai.assistants.index') }}"
            wire:navigate
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-copper/30 hover:shadow-md transition-all"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal/10">
                <x-lucide-bot class="h-5 w-5 text-teal" />
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-slate-900">AI Assistants</h3>
                <p class="mt-0.5 text-xs text-slate-500">Manage AI assistant configurations and personas.</p>
            </div>
        </a>

        <a
            href="{{ route('admin.api-tokens') }}"
            wire:navigate
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-copper/30 hover:shadow-md transition-all"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal/10">
                <x-lucide-key class="h-5 w-5 text-teal" />
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-slate-900">AI Agent Access</h3>
                <p class="mt-0.5 text-xs text-slate-500">Manage API tokens for AI agent integrations.</p>
            </div>
        </a>

        <a
            href="{{ route('admin.vat-settings') }}"
            wire:navigate
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-copper/30 hover:shadow-md transition-all"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal/10">
                <x-lucide-pound-sterling class="h-5 w-5 text-teal" />
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-slate-900">VAT Settings</h3>
                <p class="mt-0.5 text-xs text-slate-500">Configure VAT rates for cost price calculations.</p>
            </div>
        </a>

        <a
            href="{{ route('admin.backups.index') }}"
            wire:navigate
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-copper/30 hover:shadow-md transition-all"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal/10">
                <x-lucide-hard-drive class="h-5 w-5 text-teal" />
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-slate-900">Data Backups</h3>
                <p class="mt-0.5 text-xs text-slate-500">View download and manage database backups.</p>
            </div>
        </a>
    </div>
</div>
