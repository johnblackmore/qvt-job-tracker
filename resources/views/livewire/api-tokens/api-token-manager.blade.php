<div class="max-w-2xl mx-auto space-y-6 p-6">
    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">
        AI Agent Access
    </h1>

    <p class="text-slate-600 text-sm">
        Create API tokens for AI agents (e.g., OpenCode, Cursor, Claude Desktop) to access the QVT Job Tracker via MCP.
        Tokens are long-lived but can be revoked at any time.
    </p>

    {{-- Create new token --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Generate New Token</h2>

        <div class="flex gap-3">
            <input
                type="text"
                wire:model="tokenName"
                placeholder="e.g. OpenCode Desktop, Cursor Editor"
                class="flex-1 rounded-lg border-slate-300 px-4 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
            >
            <button
                wire:click="createToken"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition"
            >
                Create Token
            </button>
        </div>

        @error('tokenName')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        @if ($newPlainTextToken)
            <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-4">
                <p class="text-sm font-medium text-amber-800 mb-2">
                    Copy this token now. It will not be shown again.
                </p>
                <div class="flex gap-2 items-center">
                    <code class="flex-1 rounded bg-white border border-amber-300 px-3 py-2 text-xs text-amber-900 font-mono break-all">
                        {{ $newPlainTextToken }}
                    </code>
                    <button
                        onclick="navigator.clipboard.writeText('{{ $newPlainTextToken }}')"
                        class="rounded-lg bg-amber-200 px-3 py-2 text-xs font-medium text-amber-900 hover:bg-amber-300 transition"
                    >
                        Copy
                    </button>
                    <button
                        wire:click="clearNewToken"
                        class="rounded-lg bg-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-300 transition"
                    >
                        Done
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Existing tokens --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Your Tokens</h2>

        @if ($tokens->isEmpty())
            <p class="text-sm text-slate-500">No tokens created yet.</p>
        @else
            <div class="space-y-3">
                @foreach ($tokens as $token)
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 p-3">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $token->name }}</p>
                            <p class="text-xs text-slate-500">
                                Created {{ $token->created_at->diffForHumans() }}
                                @if ($token->last_used_at)
                                    &middot; Last used {{ $token->last_used_at->diffForHumans() }}
                                @else
                                    &middot; Never used
                                @endif
                            </p>
                        </div>
                        <button
                            wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="Are you sure you want to revoke this token? The agent will immediately lose access."
                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 transition"
                        >
                            Revoke
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Client configuration help --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">How to Connect Your Agent</h2>

        <div class="space-y-4 text-sm text-slate-600">
            <div>
                <p class="font-medium text-slate-800 mb-1">OpenCode (Remote)</p>
                <p>Add to <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">.opencode/opencode.json</code>:</p>
                <pre class="mt-2 rounded-lg bg-slate-900 p-3 text-xs text-slate-300 overflow-x-auto">{{
    "mcp": {
        "qvt-job-tracker": {
            "type": "remote",
            "url": "{{ url('/mcp/qvt') }}",
            "headers": {
                "Authorization": "Bearer &lt;your-token&gt;"
            }
        }
    }
}}</pre>
            </div>

            <div>
                <p class="font-medium text-slate-800 mb-1">Claude Desktop (Local)</p>
                <p>Add to <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">claude_desktop_config.json</code>:</p>
                <pre class="mt-2 rounded-lg bg-slate-900 p-3 text-xs text-slate-300 overflow-x-auto">{{
    "mcpServers": {
        "qvt-job-tracker": {
            "command": "php",
            "args": ["artisan", "mcp:start", "qvt", "--user={{ auth()->user()->email }}"],
            "cwd": "{{ base_path() }}"
        }
    }
}}</pre>
            </div>
        </div>
    </div>
</div>
