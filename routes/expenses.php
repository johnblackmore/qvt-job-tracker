<?php

use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpenseList;
use App\Livewire\Expenses\ExpenseShow;
use App\Livewire\Expenses\SupplierOrderForm;
use App\Livewire\Expenses\SupplierOrderList;
use App\Livewire\Expenses\SupplierOrderShow;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\SupplierOrder;
use App\Settings\AccountingMappingSettings;
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
        // Export
        Route::get('/export', function (AccountingMappingSettings $mapping) {
            $type = request('type', 'all');
            $dateFrom = request('date_from');
            $dateTo = request('date_to');

            $rows = [];

            if (in_array($type, ['all', 'supplier_orders'])) {
                $query = SupplierOrder::whereIn('status', ['received', 'partially_received', 'paid']);
                if ($dateFrom) {
                    $query->whereDate('order_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('order_date', '<=', $dateTo);
                }
                $query->chunk(100, function ($orders) use (&$rows, $mapping) {
                    foreach ($orders as $o) {
                        $rows[] = [
                            $o->order_date->format('Y-m-d'),
                            $o->reference_number,
                            $o->supplier?->name ?? '',
                            'Supplier Order: '.($o->invoice_number ?: $o->reference_number),
                            number_format($o->subtotal, 2),
                            number_format($o->vat_total, 2),
                            number_format($o->total_amount, 2),
                            $mapping->category_account_codes['stock'] ?? '5000',
                            $o->status,
                            'supplier_order',
                        ];
                    }
                });
            }

            if (in_array($type, ['all', 'expenses'])) {
                $query = Expense::whereIn('status', ['approved', 'paid']);
                if ($dateFrom) {
                    $query->whereDate('expense_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('expense_date', '<=', $dateTo);
                }
                $query->chunk(100, function ($expenses) use (&$rows, $mapping) {
                    foreach ($expenses as $e) {
                        $netAmount = $e->total_amount - $e->vat_total;
                        $categorySlug = $e->category?->slug ?? 'other';
                        $accountCode = $mapping->category_account_codes[$categorySlug] ?? '5900';
                        $rows[] = [
                            $e->expense_date->format('Y-m-d'),
                            $e->reference_number,
                            $e->merchant_name ?? '',
                            $e->description,
                            number_format($netAmount, 2),
                            number_format($e->vat_total, 2),
                            number_format($e->total_amount, 2),
                            $accountCode,
                            $e->status,
                            'expense',
                        ];
                    }
                });
            }

            $csv = implode("\n", array_map(function ($row) {
                return implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row));
            }, $rows));

            $headers = "Date,Reference,Supplier/ Merchant,Description,Net Amount,VAT Amount,Gross Amount,Account Code,Status,Type\n".$csv;

            return new StreamedResponse(function () use ($headers) {
                echo $headers;
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="expenses-export-'.now()->format('Y-m-d').'.csv"',
            ]);
        })->name('export');

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
