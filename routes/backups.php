<?php

use App\Livewire\Backup\BackupList;
use App\Livewire\Backup\BackupSettings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/backups')
    ->name('admin.backups.')
    ->group(function () {
        Route::get('/', BackupList::class)->name('index');
        Route::get('/settings', BackupSettings::class)->name('settings');
        Route::get('/download/{disk}/{path}', function (string $disk, string $path) {
            if (! Storage::disk($disk)->exists($path)) {
                abort(404);
            }

            return new StreamedResponse(function () use ($disk, $path) {
                $stream = Storage::disk($disk)->readStream($path);
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
                'Content-Length' => Storage::disk($disk)->size($path),
            ]);
        })->name('download')
            ->where('path', '.*');
    });
