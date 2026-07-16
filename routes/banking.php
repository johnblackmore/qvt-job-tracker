<?php

use App\Banking\Controllers\MonzoOAuthController;
use App\Banking\Webhooks\MonzoWebhookController;
use App\Livewire\Banking\ApproveConnection;
use App\Livewire\Banking\ReconciliationView;
use App\Livewire\Banking\SelectAccount;
use App\Livewire\Banking\TransactionList;
use App\Livewire\Banking\TransactionShow;
use App\Models\Receipt;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/banking')
    ->name('admin.banking.')
    ->group(function () {
        Route::get('/transactions', TransactionList::class)->name('transactions');
        Route::get('/transactions/{transaction}', TransactionShow::class)->name('transactions.show');
        Route::get('/reconciliation', ReconciliationView::class)->name('reconciliation');
        Route::get('/connect', [MonzoOAuthController::class, 'redirect'])->name('connect');
        Route::get('/approve', ApproveConnection::class)->name('approve');
        Route::get('/select-account', SelectAccount::class)->name('select-account');
        Route::get('/receipts/{receipt}/download', function (Receipt $receipt) {
            $path = storage_path('app/'.$receipt->file_path);

            if (! file_exists($path)) {
                abort(404);
            }

            return new StreamedResponse(function () use ($path) {
                $stream = fopen($path, 'rb');
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type' => $receipt->mime_type ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$receipt->original_filename.'"',
                'Content-Length' => $receipt->file_size,
            ]);
        })->name('receipts.download');
    });

Route::middleware(['web'])
    ->get('/monzo/callback', [MonzoOAuthController::class, 'callback'])
    ->name('monzo.callback');

Route::middleware(['auth', 'verified', 'role:admin'])
    ->get('/monzo/retry', [MonzoOAuthController::class, 'retry'])
    ->name('monzo.retry');

Route::post('/webhooks/monzo', MonzoWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1');
