<?php

use App\Http\Controllers\Ai\ChatStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/ai')->name('admin.ai.')->group(function () {
        Route::get('/chat/{conversation}/stream', ChatStreamController::class)
            ->name('chat.stream')
            ->middleware('throttle:30,1');
    });
