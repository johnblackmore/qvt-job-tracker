<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Data Backups</h1>
            <p class="mt-1 text-sm text-slate-500">Manage database and file backups for the application.</p>
        </div>
        <div class="flex items-center gap-3">
            <button
                wire:click="createBackup"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors disabled:opacity-50"
            >
                <x-lucide-hard-drive class="w-4 h-4" />
                <span wire:loading.remove wire:target="createBackup">Create Backup</span>
                <span wire:loading wire:target="createBackup">Starting...</span>
            </button>
            <a
                href="{{ route('admin.backups.settings') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors"
            >
                <x-lucide-settings class="w-4 h-4" />
                Settings
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        @if (count($this->backups) > 0)
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Filename</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Disk</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->backups as $backup)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $backup['filename'] }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $this->formatSize($backup['size']) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $backup['disk'] }}</td>
                            <td class="px-6 py-4">
                                @if ($backup['is_manual'])
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-copper/10 text-copper-dark border border-copper/20">Manual</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">Scheduled</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])->format('j M Y, H:i') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a
                                        href="{{ route('admin.backups.download', ['disk' => $backup['disk'], 'path' => $backup['path']]) }}"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-copper hover:bg-copper/10 transition-colors"
                                        title="Download"
                                    >
                                        <x-lucide-download class="w-4 h-4" />
                                    </a>
                                    <button
                                        wire:click="confirmRestore('{{ $backup['filename'] }}', '{{ $backup['disk'] }}', {{ $backup['size'] }})"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-teal hover:bg-teal/10 transition-colors"
                                        title="Restore"
                                    >
                                        <x-lucide-rotate-ccw class="w-4 h-4" />
                                    </button>
                                    @if ($backup['is_manual'] && $backup['manual_backup_id'])
                                        <button
                                            wire:click="deleteBackup({{ $backup['manual_backup_id'] }})"
                                            wire:confirm="Delete this manual backup?"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Delete"
                                        >
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                    <x-lucide-hard-drive class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-medium text-slate-900">No backups yet</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Create your first backup to protect your data. Backups include the database and all uploaded files.</p>
            </div>
        @endif
    </div>

    @if ($showRestoreModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" wire:click.self="showRestoreModal = false">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-display font-semibold text-slate-900 mb-2">Restore Backup</h3>
                <p class="text-sm text-slate-600 mb-4">
                    You are about to restore <strong>{{ $restoreFilename }}</strong> ({{ $this->formatSize($restoreSize) }}).
                    All current data will be replaced with the backup contents.
                </p>
                <label class="flex items-start gap-3 mb-6">
                    <input type="checkbox" wire:model="createBackupFirst" class="mt-0.5 rounded border-slate-300 text-copper focus:ring-copper">
                    <span class="text-sm text-slate-700">Create a backup of the current data before restoring (recommended)</span>
                </label>
                <div class="flex items-center gap-3 justify-end">
                    <button wire:click="$set('showRestoreModal', false)" class="px-4 py-2 text-sm font-medium text-slate-700 hover:text-slate-900 transition-colors">
                        Cancel
                    </button>
                    <button wire:click="executeRestore" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 transition-colors">
                        <x-lucide-alert-triangle class="w-4 h-4" />
                        Restore
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
