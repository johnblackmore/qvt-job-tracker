<?php

use App\Livewire\ApiTokens\ApiTokenManager;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/api-tokens', ApiTokenManager::class)
        ->name('api-tokens');
});

require __DIR__.'/auth.php';
require __DIR__.'/customers.php';
require __DIR__.'/products.php';
require __DIR__.'/quotes.php';
require __DIR__.'/orders.php';
require __DIR__.'/email.php';
require __DIR__.'/chat.php';
require __DIR__.'/ai-configs.php';
require __DIR__.'/admin-settings.php';
