# Data Backup Strategy — Technical Build Plan

## Overview

Implement full backup/restore functionality using **spatie/laravel-backup v10** (backup creation, cleanup, monitoring) and **wnx/laravel-backup-restore** (database restore from spatie backup archives). Backups include the full MySQL database and user-uploaded content (receipts, expense documents), stored initially on the local filesystem with support for S3 and other Laravel filesystem disks.

---

## 1. Package Installation

### Composer

```bash
composer require spatie/laravel-backup
composer require wnx/laravel-backup-restore
```

Spatie's `BackupServiceProvider` auto-registers. The restore package's `LaravelBackupRestoreServiceProvider` auto-registers.

### Publish Config

```bash
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
php artisan vendor:publish --provider="Wnx\LaravelBackupRestore\LaravelBackupRestoreServiceProvider"
```

This creates:
- `config/backup.php` — Spatie backup configuration
- `config/backup-restore.php` — Restore health checks configuration

---

## 2. Configuration — `config/backup.php`

### 2.1 Backup Name

```php
'name' => env('BACKUP_NAME', env('APP_NAME', 'qvt-job-tracker')),
```

Use an env variable so it's configurable per environment. The backup name determines the subdirectory within each disk where backup zips are stored.

### 2.2 Source — Files to Include

```php
'source' => [
    'files' => [
        'include' => [
            storage_path('app/private'),       // Receipts, expense docs
            storage_path('app/public'),        // Public uploads
        ],
        'exclude' => [
            storage_path('app/backup-temp'),   // Spatie's temp dir (auto-excluded, but explicit)
            storage_path('app/backup-restore-temp'), // Restore package temp dir
        ],
        'follow_links' => false,
        'ignore_unreadable_directories' => false,
    ],
    'databases' => [
        env('DB_CONNECTION', 'mysql'),
    ],
],
```

**What's included:**
- `storage/app/private` — Receipts uploaded via banking/receipt UI, expense documents
- `storage/app/public` — Publicly served uploads
- Full MySQL database dump (via `mysqldump`)

**What's excluded:**
- Vendor directory (not in source, so it's naturally excluded)
- `node_modules` (not in source)
- `storage/framework/` (not in source — caches, sessions, views)
- Spatie's own temp directory
- Restore temp directory
- Other backups (the `backups/` directory on the backup disk itself — to prevent backing up backups)

**Note:** The spatie package creates zips whose paths are relative to `base_path()` by default. Since we're only including storage subdirectories, relative paths are fine. But we should set `'relative_path' => base_path()` to keep zip entries tidy.

### 2.3 Destination — Disks

```php
'destination' => [
    'disks' => [
        env('BACKUP_DISK', 'local'),
    ],
],
```

**In development (local):** Uses the `local` disk (`storage/app/private/backups/`).

**In production:** Set `BACKUP_DISK=s3` in `.env`. An S3-compatible bucket (or any configured filesystem disk) can be used. The existing `s3` disk config in `config/filesystems.php` is already set up — just needs the correct `AWS_*` env values.

**Future options supported by Laravel filesystem drivers:**
| Driver | Package/Notes |
|--------|--------------|
| `s3` | AWS S3 / MinIO / DigitalOcean Spaces / Wasabi (compatible) — built-in Laravel support |
| `gcs` | Google Cloud Storage via `google/cloud-storage` + `superbalist/flysystem-google-cloud-storage` |
| `sftp` | SFTP server — built-in Laravel support (Laravel 11+) |
| `ftp` | FTP server — built-in Laravel support |
| `dropbox` | Via `spatie/flysystem-dropbox` |

The backup disk is entirely env-configurable. **No code changes needed** to switch providers — just set `BACKUP_DISK` and the relevant credentials in `.env`.

### 2.4 Retention Policy — Cleanup Strategy

The spatie `DefaultStrategy` provides exactly the required retention tiers:

```php
'cleanup' => [
    'strategy' => \Spatie\Backup\Tasks\Cleanup\DefaultStrategy::class,
    'default_strategy' => [
        'keep_all_backups_for_days' => 0,           // Skip "keep all" period for scheduled
        'keep_daily_backups_for_days' => 14,         // Keep daily for 14 days
        'keep_weekly_backups_for_weeks' => 12,       // Keep weekly for 12 weeks
        'keep_monthly_backups_for_months' => 120,    // "Keep all monthly" = effectively forever (10 years)
        'keep_yearly_backups_for_years' => 10,
        'delete_oldest_backups_when_using_more_megabytes_than' => null, // No storage cap
    ],
],
```

**How this works with manual backups:**

The `DefaultStrategy` **never deletes the newest backup**. However, it will delete manual (ad-hoc) backups over time unless we customise the strategy. **Solution:** Create a custom cleanup strategy that:
1. Marks manually created backups (by setting a tag in the zip filename or maintaining a DB table of manual backups)
2. Never deletes backups that are in the "manual" set

**Alternative approach (simpler):** Use the `backup:clean` command only for scheduled cleanup runs, and run a **custom cleanup** that filters out manual backups. Or, more simply:

- Store manual backup references in a `backup_manifests` database table
- Custom cleanup strategy checks this table before deleting backups
- UI for deleting manual backups calls the database + deletes from the disk directly

**Recommended approach — Custom Cleanup Strategy:**

Create a custom cleanup strategy that extends `DefaultStrategy` and overrides the deletion logic to skip backups whose filenames are stored in a `manual_backups` table.

```php
namespace App\Backup\Cleanup;

use Spatie\Backup\Tasks\Cleanup\DefaultStrategy;
use Spatie\Backup\BackupDestination\BackupCollection;

class PreserveManualBackupsStrategy extends DefaultStrategy
{
    protected function shouldDelete(BackupCollection $backups, $backup): bool
    {
        // Check if this backup filename is in the manual_backups table
        // If so, skip deletion
        if (ManualBackup::where('filename', $backup->path())->exists()) {
            return false;
        }
        return parent::shouldDelete(...);
    }
}
```

Actually, looking more carefully at the spatie cleanup mechanism — the `DefaultStrategy` uses `deleteOldBackups(BackupCollection $backupCollection)` method. A simpler approach would be to:

1. Store manual backups in a `manual_backups` table (filename, created_by, created_at, notes)
2. Before scheduling `backup:clean`, run a custom Artisan command that re-tags older scheduled backups for cleanup, but doesn't touch manual ones
3. Override the cleanup strategy

**Simplest approach:** Create a new cleanup strategy that filters out manual backups:

```php
namespace App\Backup\Cleanup;

use Spatie\Backup\Tasks\Cleanup\DefaultStrategy;
use Spatie\Backup\BackupDestination\BackupCollection;
use App\Models\ManualBackup;

class PreserveManualBackupsStrategy extends DefaultStrategy
{
    public function deleteOldBackups(BackupCollection $backupCollection): void
    {
        parent::deleteOldBackups($backupCollection);
        
        // Re-add manual backups that might have been deleted
        $manualFilenames = ManualBackup::pluck('filename')->toArray();
        // Actually this won't work because the parent already deleted them...
    }
}
```

**Better approach:** Tag manual backups with a prefix in the filename (e.g., `manual_2026-07-18-14-30-00.zip`) and create a custom strategy that never deletes them.

```php
namespace App\Backup\Cleanup;

use Spatie\Backup\Tasks\Cleanup\DefaultStrategy;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\BackupDestination\Backup;

class PreserveManualBackupsStrategy extends DefaultStrategy
{
    public function deleteOldBackups(BackupCollection $backupCollection): void
    {
        // Partition into manual and automatic
        $manual = $backupCollection->filter(fn (Backup $backup) => 
            str_starts_with($backup->path(), 'manual_')
        );
        
        $automatic = $backupCollection->reject(fn (Backup $backup) => 
            str_starts_with($backup->path(), 'manual_')
        );
        
        // Run parent cleanup on automatic only
        parent::deleteOldBackups($automatic);
        
        // Manual backups are never touched
    }
}
```

This is clean, simple, and requires no database table. The `manual_` prefix can be prepended when creating the backup via `--filename` option or by renaming after creation.

**Wait — does spatie/laravel-backup support a `--filename` option?** Let me check... Looking at the command, it doesn't have a `--filename` flag. The filename is auto-generated as `{YYYY-MM-DD-HH-mm-SS}.zip`.

**Alternative approach — use a database table and a custom event listener:**

1. Create `manual_backups` table: `id`, `filename` (the zip filename), `created_by_user_id`, `created_at`
2. Listen to `BackupZipWasCreated` event — if the backup was triggered manually (user-initiated), record it
3. Custom cleanup strategy checks `manual_backups` table before deleting

This is the most robust approach.

**Actually, the simplest viable approach:**

Since the backup:run command doesn't support custom filenames, and we need to distinguish manual from scheduled backups:

1. Create `manual_backups` table
2. When a manual backup is triggered (via UI or MCP), first create a DB record, then run `backup:run`
3. The custom cleanup strategy uses the `ManualBackup` model to preserve those filenames

Here's the plan for the custom strategy:

```php
class PreserveManualBackupsStrategy extends DefaultStrategy
{
    public function deleteOldBackups(BackupCollection $backupCollection): void
    {
        $manualFilenames = ManualBackup::pluck('filename')->flip();
        
        // Temporarily remove manual backups from the collection
        $filtered = new BackupCollection(
            $backupCollection->reject(fn (Backup $backup) =>
                $manualFilenames->has($backup->path())
            )
        );
        
        parent::deleteOldBackups($filtered);
    }
}
```

### 2.5 Compression & Encryption

```php
'database_dump_compressor' => \Spatie\Backup\Compressors\GzipCompressor::class,
'destination' => [
    'compression_method' => ZipArchive::CM_DEFLATE,
    'compression_level' => 9,
    'filename_prefix' => '',
],
'password' => env('BACKUP_ARCHIVE_PASSWORD'),
'encryption' => 'default',  // AES-256
```

**Recommendation:** Skip encryption initially (leave `password` null). It adds complexity for restore (need to pass password). Add encryption when backups are stored off-site (S3).

### 2.6 MySQL Dump Settings

In `config/database.php`, add to the `mysql` connection:

```php
'mysql' => [
    // ... existing config ...
    'dump' => [
        'use_single_transaction' => true,
        'timeout' => 60 * 10,  // 10 minutes
        'exclude_tables' => [],
        'add_extra_option' => '--column-statistics=0',
    ],
],
```

`use_single_transaction` prevents table locking (InnoDB-safe). `--column-statistics=0` avoids MySQL 8+ dump warnings.

---

## 3. Files Created

### 3.1 Models

| File | Purpose |
|------|---------|
| `app/Models/ManualBackup.php` | Tracks manually-created backups for preservation by cleanup |

**Schema for `manual_backups` table (migration):**

```php
Schema::create('manual_backups', function (Blueprint $table) {
    $table->id();
    $table->string('filename');                    // e.g., "2026-07-18-14-30-00.zip"
    $table->string('disk')->default('local');      // Which disk the backup is on
    $table->string('backup_name')->default('qvt-job-tracker'); // The backup name/path
    $table->foreignId('created_by_user_id')->constrained('users');
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->unique(['filename', 'disk', 'backup_name']);
});
```

### 3.2 Custom Cleanup Strategy

| File | Purpose |
|------|---------|
| `app/Backup/Cleanup/PreserveManualBackupsStrategy.php` | Extends DefaultStrategy to skip manual backups |

### 3.3 Console Commands

| File | Purpose |
|------|---------|
| `routes/console.php` (amend) | Add backup schedule (daily, weekly, monthly) |

### 3.4 Livewire Components

| Component | View | Purpose |
|-----------|------|---------|
| `app/Livewire/Backup/BackupList.php` | `resources/views/livewire/backup/backup-list.blade.php` | List all backups, create/restore/delete actions |
| `app/Livewire/Backup/BackupSettings.php` | `resources/views/livewire/backup/backup-settings.blade.php` | Configure backup disk, schedule overrides |

### 3.5 Route File

| File | Purpose |
|------|---------|
| `routes/backups.php` | Admin backup routes |
| `routes/web.php` (amend) | Add `require __DIR__.'/backups.php'` |

### 3.6 Sidebar Navigation

| File | Purpose |
|------|---------|
| `resources/views/layouts/app.blade.php` (amend) | Add "Data Backups" link under Admin section |

### 3.7 MCP Tools

| File | Purpose |
|------|---------|
| `app/Mcp/Tools/Backup/ListBackupsTool.php` | List all backups with metadata |
| `app/Mcp/Tools/Backup/CreateBackupTool.php` | Trigger ad-hoc backup (preview/confirmed) |
| `app/Mcp/Tools/Backup/RestoreBackupTool.php` | Restore a backup (preview/confirmed, prompts to create backup first) |
| `app/Mcp/Tools/Backup/DeleteBackupTool.php` | Delete a manual backup (preview/confirmed) |

### 3.8 Config

| File | Purpose |
|------|---------|
| `config/backup.php` | Spatie backup configuration (published + customised) |
| `config/backup-restore.php` | Restore health checks (published) |

---

## 4. Scheduled Jobs — `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

// Daily backup — runs at 2am
Schedule::command('backup:clean')
    ->dailyAt('02:00')
    ->name('backup-daily-clean');
Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->name('backup-daily-run');

// Weekly backup — Sunday at 3am
Schedule::command('backup:run')
    ->weeklyOn(0, '03:00')
    ->name('backup-weekly-run');

// Monthly backup — 1st of month at 4am
Schedule::command('backup:run')
    ->monthlyOn(1, '04:00')
    ->name('backup-monthly-run');

// Monitor — hourly during working hours
Schedule::command('backup:monitor')
    ->hourly()
    ->between('8:00', '20:00')
    ->name('backup-monitor');
```

**Important:** The daily, weekly, and monthly schedules all run `backup:run`. Each creates a distinct backup zip with its own timestamp. The cleanup strategy manages retention per tier.

The cleanup runs **before** the daily backup to free space before the new backup is created.

---

## 5. Admin UI — Backup Section

### 5.1 Route Structure (`routes/backups.php`)

```php
Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/backups')
    ->name('admin.backups.')
    ->group(function () {
        Route::get('/', BackupList::class)->name('index');
        Route::get('/settings', BackupSettings::class)->name('settings');
    });
```

The backup list is the main page. Settings is a sub-page for configuring the backup disk and other options.

### 5.2 BackupList Component

**Page structure:**
- Header: "Data Backups" title
- Action buttons: "Create Backup" (ad-hoc), "Settings" (cog icon linking to settings page)
- Backup table with columns:
  | Column | Description |
  |--------|-------------|
  | Filename | Full backup filename |
  | Size | Human-readable file size |
  | Disk | Storage disk name (local/s3) |
  | Type | Manual / Daily / Weekly / Monthly (badge) |
  | Created | Timestamp |
  | Actions | Download, Restore, Delete (if manual) |
- Pagination (if many backups)
- Empty state: "No backups yet. Create your first backup to protect your data."

**Backup listing logic:**
```php
// Use Storage::disk($disk)->allFiles($backupName) to list zips
// Cross-reference with ManualBackup model for type detection
// Enrich with disk, size, and type metadata

$disks = config('backup.backup.destination.disks');
$backupName = config('backup.backup.name');

foreach ($disks as $disk) {
    $files = Storage::disk($disk)->allFiles($backupName);
    // Filter for .zip files
    // Cross-reference with ManualBackup to mark manual backups
}
```

**Download:**
Stream the backup file from the disk to the user:
```php
return Storage::disk($disk)->download($path);
```

**Create Backup:**
Call `Artisan::call('backup:run')` or dispatch a queued job that runs the backup. Since backups can take time, the recommended approach is to dispatch a job:

```php
use App\Jobs\CreateBackupJob;

// In the Livewire component:
public function createBackup(): void
{
    $this->validate([
        'notes' => 'nullable|string|max:500',
    ]);
    
    dispatch(new CreateBackupJob(
        userId: Auth::id(),
        notes: $this->notes,
    ));
    
    session()->flash('success', 'Backup has been started. It will appear in the list once completed.');
}
```

The job runs `backup:run`, then records the filename in `ManualBackup`.

**Restore:**
Dispatches a confirmation modal:
1. Show "Are you sure?" modal with selected backup details
2. Optionally trigger a new backup first (checkbox, checked by default)
3. On confirm: dispatch `RestoreBackupJob` which runs `backup:restore`

**Delete:**
Only available for manual backups. Simple confirmation flow:
```php
public function deleteBackup(int $backupId): void
{
    $manualBackup = ManualBackup::findOrFail($backupId);
    // Delete from disk
    Storage::disk($manualBackup->disk)->delete($manualBackup->filename);
    // Delete from DB
    $manualBackup->delete();
    
    session()->flash('success', 'Manual backup deleted.');
}
```

### 5.3 BackupSettings Component

Uses spatie/laravel-settings (already in the project — `settings` table exists). Creates a `BackupSettings` class:

```php
namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BackupSettings extends Settings
{
    public string $backup_disk;
    public ?string $aws_key;
    public ?string $aws_secret;
    public ?string $aws_region;
    public ?string $aws_bucket;
    
    public static function group(): string
    {
        return 'backup';
    }
}
```

**Settings page fields:**
- Backup disk driver (select: local / s3 / sftp / etc.)
- Conditional fields based on driver choice
- S3 fields (key, secret, region, bucket, endpoint) when S3 is selected
- Test connection button

**Migration:** `database/settings/2026_07_18_000000_create_backup_settings.php`

Note: For simplicity and security (keys in DB is not ideal), an alternative is to keep the existing `BACKUP_DISK` env-based approach and not build a settings UI for provider config. Instead, document that changing the backup provider requires updating `.env`. The settings page could just show the current backup disk name (read-only).

**Recommendation:** Keep backup provider configuration env-based and read-only in the UI. Add a `backup_disk` env option that can be changed at deployment level. This avoids storing cloud credentials in the database.

### 5.4 Sidebar Addition

Add after "VAT Settings" in `resources/views/layouts/app.blade.php`:

```blade
<a
    href="{{ route('admin.backups.index') }}"
    wire:navigate
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.backups.*') ? 'bg-copper/10 text-copper' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
>
    <x-lucide-hard-drive class="w-5 h-5 shrink-0" />
    Data Backups
</a>
```

---

## 6. MCP Tools

Each tool follows the project's established MCP pattern (see `app/Mcp/Tools/` for examples).

### 6.1 `ListBackupsTool`

- **Class:** `App\Mcp\Tools\Backup\ListBackupsTool`
- **Annotation:** `#[IsReadOnly]`
- **Schema:** `disk` (nullable string, default all configured disks)
- **Output:** List of backups with filename, size, disk, type (manual/auto), created_at
- **Response:** `{ status: 'completed', message: 'Found N backups.', backups: [...] }`

### 6.2 `CreateBackupTool`

- **Class:** `App\Mcp\Tools\Backup\CreateBackupTool`
- **Annotation:** `#[IsIdempotent]`
- **Schema:** `notes` (nullable string), `preview`, `confirmed`
- **Preview response:** "This will create a new full backup (database + uploads). Estimated size: ~X MB."
- **Execute response:** Dispatches `CreateBackupJob`. Returns: `{ status: 'completed', message: 'Backup started...', backup: { ... } }`

### 6.3 `RestoreBackupTool`

- **Class:** `App\Mcp\Tools\Backup\RestoreBackupTool`
- **Annotation:** `#[IsDestructive]`
- **Schema:** `filename` (required string), `disk` (nullable string), `create_backup_first` (boolean, default true), `preview`, `confirmed`
- **Preview response:** "This will restore the backup '2026-07-18-02-30-00.zip'. All current data will be replaced. A new backup will be created first: Yes/No."
- **Execute response:** If `create_backup_first` is true, dispatches `CreateBackupJob` first, then `RestoreBackupJob` with a delay. Returns: `{ status: 'completed', message: 'Restore initiated...' }`

### 6.4 `DeleteBackupTool`

- **Class:** `App\Mcp\Tools\Backup\DeleteBackupTool`
- **Annotation:** `#[IsDestructive]`
- **Schema:** `filename` (required string), `disk` (nullable string), `preview`, `confirmed`
- **Preview response:** Shows backup details and confirms it's a manual backup.
- **Execute response:** Deletes from disk and DB. Returns: `{ status: 'completed', message: 'Deleted backup X.' }`

All tools registered in `QvtServer.php`:
```php
\App\Mcp\Tools\Backup\ListBackupsTool::class,
\App\Mcp\Tools\Backup\CreateBackupTool::class,
\App\Mcp\Tools\Backup\RestoreBackupTool::class,
\App\Mcp\Tools\Backup\DeleteBackupTool::class,
```

---

## 7. Jobs

### 7.1 `CreateBackupJob`

```php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use App\Models\ManualBackup;

class CreateBackupJob implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        public int $userId,
        public ?string $notes = null,
    ) {}
    
    public function handle(): void
    {
        // Run the backup
        $exitCode = Artisan::call('backup:run', [
            '--disable-notifications' => true,
        ]);
        
        if ($exitCode !== 0) {
            throw new \RuntimeException('Backup failed: ' . Artisan::output());
        }
        
        // Find the newly created backup zip
        $backupName = config('backup.backup.name');
        $disk = config('backup.backup.destination.disks.0');
        $files = Storage::disk($disk)->allFiles($backupName);
        $latest = collect($files)->filter(fn($f) => str_ends_with($f, '.zip'))->last();
        
        if ($latest) {
            ManualBackup::create([
                'filename' => basename($latest),
                'disk' => $disk,
                'backup_name' => $backupName,
                'created_by_user_id' => $this->userId,
                'notes' => $this->notes,
            ]);
        }
    }
}
```

### 7.2 `RestoreBackupJob`

```php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class RestoreBackupJob implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        public string $filename,
        public string $disk,
        public string $connection = 'mysql',
    ) {}
    
    public function handle(): void
    {
        $exitCode = Artisan::call('backup:restore', [
            '--disk' => $this->disk,
            '--backup' => $this->filename,
            '--connection' => $this->connection,
            '--reset' => true,
            '--no-interaction' => true,
        ]);
        
        if ($exitCode !== 0) {
            throw new \RuntimeException('Restore failed: ' . Artisan::output());
        }
    }
}
```

---

## 8. Retention Policy — Summary

| Tier | Retention | How enforced |
|------|-----------|-------------|
| **Manual backups** | Forever (or until manually deleted) | `ManualBackup` table tracks filenames; custom strategy skips them |
| **Daily backups** | Last 14 days | `keep_daily_backups_for_days => 14` in cleanup config |
| **Weekly backups** | Last 12 weeks | `keep_weekly_backups_for_weeks => 12` in cleanup config |
| **Monthly backups** | Forever | `keep_monthly_backups_for_months => 120` (effectively 10 years) |

---

## 9. Implementation Order

### Phase 1 — Foundation
1. Install composer packages
2. Publish and configure `config/backup.php` and `config/backup-restore.php`
3. Add MySQL dump settings to `config/database.php`
4. Add backup schedule to `routes/console.php`
5. Create `PreserveManualBackupsStrategy`
6. Create migration + model for `manual_backups`
7. Create `CreateBackupJob` and `RestoreBackupJob`
8. Run `php artisan migrate` (safe — new table, no existing data affected)

### Phase 2 — Admin UI
9. Create `routes/backups.php` and register in `routes/web.php`
10. Create `BackupList` Livewire component + view
11. Create `BackupSettings` Livewire component + view
12. Add sidebar link to `resources/views/layouts/app.blade.php`

### Phase 3 — MCP Tools
13. Create `ListBackupsTool`
14. Create `CreateBackupTool`
15. Create `RestoreBackupTool`
16. Create `DeleteBackupTool`
17. Register all four in `QvtServer.php`

### Phase 4 — Polish
18. Configure notifications (email/Slack on failure)
19. Test backup creation and cleanup
20. Test restore on staging
21. Update `.env.example` with new variables

---

## 10. Environment Variables

```env
# Backup
BACKUP_NAME=qvt-job-tracker
BACKUP_DISK=local                    # Change to 's3' for cloud storage
BACKUP_ARCHIVE_PASSWORD=             # Optional: encrypt backup archives

# S3 (if BACKUP_DISK=s3)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-west-2
AWS_BUCKET=qvt-backups
AWS_ENDPOINT=                        # Optional: for MinIO/DO Spaces
```

Add these to `.env.example` and document in `AGENTS.md`.

---

## 11. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Large database + uploads may cause backup to exceed memory/timeout | Use queued job for backup; set timeout to 10+ minutes |
| Restore drops existing data if it fails partway | `--reset` drops tables before restore; if restore fails mid-way, DB is empty. Run backup BEFORE restore always. |
| Manual backups fill disk if never deleted | UI shows manual backups with delete button; add storage usage indicator to backup list |
| S3 credentials in `.env` are production secrets | Use Laravel's env-based config (not stored in DB); document in AGENTS.md NOT to commit `.env` |
| `backup:restore` is interactive by default | Use `--no-interaction` + all options in the job to avoid prompts |
| MySQL GTID enabled (DigitalOcean managed DBs) | Restore may fail. Document this limitation. Ensure `dump.add_extra_option` doesn't cause issues. |
| Encryption password lost means unrecoverable backups | Skip encryption initially; document that if enabled, `BACKUP_ARCHIVE_PASSWORD` must be stored securely |

---

## 12. Testing

### Unit/Feature Tests
- `ManualBackup` model: factory, scopes, relationships
- `PreserveManualBackupsStrategy`: verify manual backups survive cleanup
- `CreateBackupJob`: verify the job dispatches and records backup

### MCP Tool Tests (following existing pattern)
- `ListBackupsTool`: returns backups, auth check
- `CreateBackupTool`: preview mode, execute mode, validation, auth
- `RestoreBackupTool`: preview mode, execute mode, prompts backup first, auth
- `DeleteBackupTool`: preview mode, execute mode, auth, only manual

### Manual QA
- Run `backup:run` manually, verify zip created in `storage/app/private/backups/`
- Run `backup:list` to verify monitoring output
- Run `backup:clean --dry-run` (if available) to preview cleanup
- Test restore on a copy of the database (never on production)

---

## 13. Post-Implementation Checklist

- [ ] Packages installed and config published
- [ ] `config/backup.php` customised per this plan
- [ ] MySQL dump config added to `config/database.php`
- [ ] `PreserveManualBackupsStrategy` created and configured
- [ ] `manual_backups` migration created and run
- [ ] `ManualBackup` model created
- [ ] `CreateBackupJob` created
- [ ] `RestoreBackupJob` created
- [ ] Console schedule updated (daily, weekly, monthly, monitor)
- [ ] Backup routes created and registered
- [ ] `BackupList` Livewire component + view created
- [ ] `BackupSettings` Livewire component + view created (read-only disk info)
- [ ] Sidebar link added
- [ ] 4 MCP tools created and registered
- [ ] `.env.example` updated
- [ ] `AGENTS.md` updated with backup section
- [ ] Notifications configured (mail/Slack)
- [ ] `vendor/bin/pint --format agent` run on all new/modified PHP files
- [ ] `npm run build` verified (no Vite changes needed)
- [ ] Tests written and passing
