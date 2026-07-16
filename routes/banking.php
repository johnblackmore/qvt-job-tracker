<?php

use App\Banking\Webhooks\MonzoWebhookController;
use App\Livewire\Banking\TransactionList;
use App\Livewire\Banking\TransactionShow;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/banking')
    ->name('admin.banking.')
    ->group(function () {
        Route::get('/transactions', TransactionList::class)->name('transactions');
        Route::get('/transactions/{transaction}', TransactionShow::class)->name('transactions.show');
    });

Route::post('/webhooks/monzo', MonzoWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1');
