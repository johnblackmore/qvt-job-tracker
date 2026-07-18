<?php

use App\Livewire\Admin\SystemSettings;
use App\Livewire\Admin\VatSettings;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/system-settings', SystemSettings::class)->name('system-settings');
        Route::get('/vat-settings', VatSettings::class)->name('vat-settings');
    });
