<div class="max-w-2xl">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Backup Settings</h1>
        <p class="mt-1 text-sm text-slate-500">View current backup configuration.</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-200">
        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Backup Name</p>
                <p class="text-sm text-slate-500">Used as the directory name for storing backups</p>
            </div>
            <span class="text-sm font-mono text-slate-700 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">{{ $backupName }}</span>
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Storage Disk</p>
                <p class="text-sm text-slate-500">Where backups are stored. Configured via the <code class="text-xs bg-slate-100 px-1 rounded">BACKUP_DISK</code> environment variable.</p>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-copper/10 text-copper-dark border border-copper/20">{{ $backupDisk }}</span>
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Scheduled Backups</p>
                <p class="text-sm text-slate-500">Daily at 02:30, Weekly on Sunday at 03:00, Monthly on 1st at 04:00</p>
            </div>
            <x-lucide-check-circle class="w-5 h-5 text-teal" />
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Retention Policy</p>
                <p class="text-sm text-slate-500">Daily: 14 days, Weekly: 12 weeks, Monthly: indefinite, Manual: kept until deleted</p>
            </div>
            <x-lucide-check-circle class="w-5 h-5 text-teal" />
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Database Dump</p>
                <p class="text-sm text-slate-500">MySQL with single-transaction mode (no table locking)</p>
            </div>
            <x-lucide-database class="w-5 h-5 text-slate-400" />
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Notification Email</p>
                <p class="text-sm text-slate-500">Receives alerts when backups fail</p>
            </div>
            <span class="text-sm text-slate-700">{{ $notificationEmail ?: 'Not configured' }}</span>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">Changing Backup Provider</h3>
        <p class="text-sm text-slate-600 mb-4">
            To change the backup storage provider, set the following environment variables in your <code class="text-xs bg-slate-100 px-1 rounded">.env</code> file:
        </p>
        <div class="bg-slate-50 rounded-lg p-4 font-mono text-xs text-slate-700 space-y-1 border border-slate-200">
            <div>BACKUP_DISK=s3</div>
            <div>AWS_ACCESS_KEY_ID=your_key</div>
            <div>AWS_SECRET_ACCESS_KEY=your_secret</div>
            <div>AWS_DEFAULT_REGION=eu-west-2</div>
            <div>AWS_BUCKET=qvt-backups</div>
        </div>
        <p class="mt-3 text-xs text-slate-500">The backup system supports any Laravel filesystem disk: local, S3, GCS, SFTP, and more.</p>
    </div>

    <div class="mt-6">
        <a href="{{ route('admin.backups.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-medium text-copper hover:text-copper-dark transition-colors">
            <x-lucide-arrow-left class="w-4 h-4" />
            Back to Backups
        </a>
    </div>
</div>
