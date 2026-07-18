<?php

namespace App\Livewire\Backup;

use App\Jobs\RestoreBackupJob;
use App\Models\ManualBackup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class BackupList extends Component
{
    public bool $showRestoreModal = false;

    public ?string $restoreFilename = null;

    public ?string $restoreDisk = null;

    public int $restoreSize = 0;

    public bool $createBackupFirst = true;

    public bool $isRunning = false;

    public function getBackupsProperty(): array
    {
        $backups = [];
        $backupName = config('backup.backup.name');
        $disks = config('backup.backup.destination.disks');
        $manualFilenames = ManualBackup::pluck('filename')->toArray();

        foreach ($disks as $disk) {
            try {
                $files = Storage::disk($disk)->allFiles($backupName);
            } catch (\Exception) {
                continue;
            }

            foreach ($files as $file) {
                if (! str_ends_with($file, '.zip')) {
                    continue;
                }

                $filename = basename($file);
                $backups[] = [
                    'filename' => $filename,
                    'disk' => $disk,
                    'path' => $file,
                    'size' => Storage::disk($disk)->size($file),
                    'last_modified' => Storage::disk($disk)->lastModified($file),
                    'is_manual' => in_array($filename, $manualFilenames),
                    'manual_backup_id' => ManualBackup::where('filename', $filename)
                        ->where('disk', $disk)
                        ->value('id'),
                ];
            }
        }

        usort($backups, fn (array $a, array $b) => $b['last_modified'] <=> $a['last_modified']);

        return $backups;
    }

    public function createBackup(): void
    {
        $this->isRunning = true;

        try {
            $exitCode = Artisan::call('backup:run', [
                '--disable-notifications' => true,
            ]);

            if ($exitCode !== 0) {
                Log::error('Backup failed', ['output' => Artisan::output()]);
                session()->flash('error', 'Backup failed. Check the application logs for details.');

                return;
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
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            session()->flash('success', 'Backup created successfully.');
        } catch (\Exception $e) {
            Log::error('Backup failed with exception', ['message' => $e->getMessage()]);
            session()->flash('error', 'Backup failed: '.$e->getMessage());
        } finally {
            $this->isRunning = false;
        }
    }

    public function confirmRestore(string $filename, string $disk, int $size): void
    {
        $this->restoreFilename = $filename;
        $this->restoreDisk = $disk;
        $this->restoreSize = $size;
        $this->createBackupFirst = true;
        $this->showRestoreModal = true;
    }

    public function executeRestore(): void
    {
        $this->validate([
            'restoreFilename' => 'required|string',
            'restoreDisk' => 'required|string',
        ]);

        if ($this->createBackupFirst) {
            Artisan::call('backup:run', ['--disable-notifications' => true]);
        }

        dispatch(new RestoreBackupJob(
            filename: $this->restoreFilename,
            disk: $this->restoreDisk,
        ));

        $this->showRestoreModal = false;
        session()->flash('warning', 'Restore initiated. The application will be unavailable until the restore completes.');
    }

    public function deleteBackup(int $backupId): void
    {
        $manualBackup = ManualBackup::findOrFail($backupId);

        Storage::disk($manualBackup->disk)->delete(
            $manualBackup->backup_name.'/'.$manualBackup->filename
        );

        $manualBackup->delete();

        session()->flash('success', 'Manual backup deleted.');
    }

    public function formatSize(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function render()
    {
        return view('livewire.backup.backup-list');
    }
}
