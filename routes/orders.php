<?php

use App\Livewire\Orders\OrderForm;
use App\Livewire\Orders\OrderList;
use App\Livewire\Orders\OrderShow;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('orders', OrderList::class)->name('orders.index');
    Route::get('orders/create', OrderForm::class)->name('orders.create');
    Route::get('orders/create/from-quote/{quoteId}', OrderForm::class)->name('orders.create-from-quote');
    Route::get('orders/{id}', OrderShow::class)->name('orders.show');
    Route::get('orders/{id}/edit', OrderForm::class)->name('orders.edit');
});
