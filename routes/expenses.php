<?php

use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpenseList;
use App\Livewire\Expenses\ExpenseShow;
use App\Livewire\Expenses\SupplierOrderForm;
use App\Livewire\Expenses\SupplierOrderList;
use App\Livewire\Expenses\SupplierOrderShow;
use App\Models\ExpenseDocument;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/expenses')
    ->name('expenses.')
    ->group(function () {

        // Supplier Orders
        Route::get('/supplier-orders', SupplierOrderList::class)->name('supplier-orders.index');
        Route::get('/supplier-orders/create', SupplierOrderForm::class)->name('supplier-orders.create');
        Route::get('/supplier-orders/{supplierOrderId}', SupplierOrderShow::class)->name('supplier-orders.show');
        Route::get('/supplier-orders/{supplierOrderId}/edit', SupplierOrderForm::class)->name('supplier-orders.edit');

        // Expenses
        Route::get('/', ExpenseList::class)->name('index');
        Route::get('/create', ExpenseForm::class)->name('create');
        Route::get('/{expenseId}', ExpenseShow::class)->name('show');
        Route::get('/{expenseId}/edit', ExpenseForm::class)->name('edit');

        // Documents
        Route::get('/documents/{document}/download', function (ExpenseDocument $document) {
            $path = storage_path('app/private/'.$document->file_path);

            if (! file_exists($path)) {
                abort(404);
            }

            return new StreamedResponse(function () use ($path) {
                $stream = fopen($path, 'rb');
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
                'Content-Length' => $document->file_size,
            ]);
        })->name('documents.download');
    });
