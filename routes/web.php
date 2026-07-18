<?php

use App\Http\Controllers\Webhook\PostmarkWebhookController;
use App\Livewire\ApiTokens\ApiTokenManager;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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

Route::post('/webhooks/postmark', [PostmarkWebhookController::class, '__invoke'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('webhooks.postmark');

require __DIR__.'/auth.php';
require __DIR__.'/customers.php';
require __DIR__.'/products.php';
require __DIR__.'/quotes.php';
require __DIR__.'/orders.php';
require __DIR__.'/email.php';
require __DIR__.'/expenses.php';
require __DIR__.'/chat.php';
require __DIR__.'/ai-configs.php';
require __DIR__.'/ai-assistants.php';
require __DIR__.'/admin-settings.php';
