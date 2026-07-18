<?php

namespace App\Backup\Cleanup;

use App\Models\ManualBackup;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;

class PreserveManualBackupsStrategy extends DefaultStrategy
{
    public function deleteOldBackups(BackupCollection $backupCollection): void
    {
        $manualFilenames = ManualBackup::pluck('filename')
            ->map(fn (string $name) => $name)
            ->flip();

        $filtered = new BackupCollection(
            $backupCollection->reject(fn (Backup $backup) => $manualFilenames->has($backup->path())
            )
        );

        parent::deleteOldBackups($filtered);
    }
}
