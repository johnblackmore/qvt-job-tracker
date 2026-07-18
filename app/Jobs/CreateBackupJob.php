<?php

namespace App\Jobs;

use App\Models\ManualBackup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class CreateBackupJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public ?string $notes = null,
    ) {}

    public function handle(): void
    {
        $exitCode = Artisan::call('backup:run', [
            '--disable-notifications' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Backup failed: '.Artisan::output());
        }

        $backupName = config('backup.backup.name');
        $disks = config('backup.backup.destination.disks');
        $disk = $disks[0] ?? 'local';

        $files = Storage::disk($disk)->allFiles($backupName);
        $latest = collect($files)
            ->filter(fn (string $f) => str_ends_with($f, '.zip'))
            ->sort()
            ->last();

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
