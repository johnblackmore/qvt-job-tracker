<?php

namespace App\Livewire\Backup;

use Livewire\Component;

class BackupSettings extends Component
{
    public string $backupDisk;

    public string $backupName;

    public string $backupDiskInfo;

    public ?string $notificationEmail;

    public function mount(): void
    {
        $this->backupDisk = config('backup.backup.destination.disks.0', 'local');
        $this->backupName = config('backup.backup.name');
        $this->notificationEmail = config('backup.notifications.mail.to');
    }

    public function render()
    {
        return view('livewire.backup.backup-settings');
    }
}
