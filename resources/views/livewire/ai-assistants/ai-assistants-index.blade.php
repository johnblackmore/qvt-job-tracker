<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">AI Assistants</h1>
            <p class="mt-1 text-sm text-slate-500">Usage statistics and logs for each AI assistant</p>
        </div>
        <a href="{{ route('admin.ai.assistant-settings') }}" wire:navigate
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 hover:border-slate-300 transition-colors">
            <x-lucide-settings class="w-4 h-4" />
            Assistant Settings
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <a href="{{ route('admin.ai.assistants.chat-agent') }}" wire:navigate
           class="group bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-all block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 rounded-lg bg-copper/10 flex items-center justify-center group-hover:bg-copper/15 transition-colors">
                    <x-lucide-message-square-more class="w-5 h-5 text-copper" />
                </div>
                <span class="text-xs font-medium text-copper group-hover:underline">View Details &rarr;</span>
            </div>
            <h3 class="text-base font-display font-semibold text-slate-900 mb-1">Chat Agent</h3>
            <p class="text-xs text-slate-500 mb-4">Staff chat widget conversations</p>
            <div class="grid grid-cols-3 gap-3 pt-3 border-t border-slate-100">
                <div>
                    <p class="text-xs text-slate-400">Conversations</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($chatConversations) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Messages</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($chatMessages) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Tokens</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($chatTokens) }}</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.ai.assistants.product-extractor') }}" wire:navigate
           class="group bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-all block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                    <x-lucide-search class="w-5 h-5 text-blue-600" />
                </div>
                <span class="text-xs font-medium text-copper group-hover:underline">View Details &rarr;</span>
            </div>
            <h3 class="text-base font-display font-semibold text-slate-900 mb-1">Product URL Extractor</h3>
            <p class="text-xs text-slate-500 mb-4">Extract product data from supplier URLs</p>
            <div class="grid grid-cols-3 gap-3 pt-3 border-t border-slate-100">
                <div>
                    <p class="text-xs text-slate-400">Total</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($productExtractions) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Success Rate</p>
                    <p class="text-lg font-bold text-teal-dark">{{ number_format($productExtractionsSuccessRate, 2) }}%</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Tokens</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($productExtractionTokens) }}</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.ai.assistants.expenses-extractor') }}" wire:navigate
           class="group bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-all block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center group-hover:bg-amber-100 transition-colors">
                    <x-lucide-receipt class="w-5 h-5 text-amber-600" />
                </div>
                <span class="text-xs font-medium text-copper group-hover:underline">View Details &rarr;</span>
            </div>
            <h3 class="text-base font-display font-semibold text-slate-900 mb-1">Expenses Assistant</h3>
            <p class="text-xs text-slate-500 mb-4">Extract invoice and receipt data with AI</p>
            <div class="grid grid-cols-3 gap-3 pt-3 border-t border-slate-100">
                <div>
                    <p class="text-xs text-slate-400">Total</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($expensesExtractions) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Success Rate</p>
                    <p class="text-lg font-bold text-teal-dark">{{ number_format($expensesExtractionsSuccessRate, 2) }}%</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Tokens</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($expensesExtractionTokens) }}</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.ai.assistants.enquiry-draft') }}" wire:navigate
           class="group bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:border-copper/30 transition-all block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center group-hover:bg-purple-100 transition-colors">
                    <x-lucide-file-text class="w-5 h-5 text-purple-600" />
                </div>
                <span class="text-xs font-medium text-copper group-hover:underline">View Details &rarr;</span>
            </div>
            <h3 class="text-base font-display font-semibold text-slate-900 mb-1">Enquiry Draft Assistant</h3>
            <p class="text-xs text-slate-500 mb-4">AI-generated draft responses to enquiries</p>
            <div class="grid grid-cols-3 gap-3 pt-3 border-t border-slate-100">
                <div>
                    <p class="text-xs text-slate-400">Drafts</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($drafts) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Tokens</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($draftTokens) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400"></p>
                    <p class="text-lg font-bold text-slate-900"></p>
                </div>
            </div>
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-display font-semibold text-slate-900">Platform-wide AI Usage</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-slate-400">Total Tokens</p>
                <p class="text-xl font-bold text-slate-900">{{ number_format($totalTokens) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Conversations</p>
                <p class="text-xl font-bold text-slate-900">{{ number_format($chatConversations) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Extractions</p>
                <p class="text-xl font-bold text-slate-900">{{ number_format($extractions) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Draft Generations</p>
                <p class="text-xl font-bold text-slate-900">{{ number_format($drafts) }}</p>
            </div>
        </div>
    </div>
</div>
