<?php

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
        $backupName = config('backup.backup.name');

        $exitCode = Artisan::call('backup:restore', [
            '--disk' => $this->disk,
            '--backup' => $backupName.'/'.$this->filename,
            '--connection' => $this->connection,
            '--reset' => true,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Restore failed: '.Artisan::output());
        }
    }
}
