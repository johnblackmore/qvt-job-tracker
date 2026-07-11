<?php

use App\Livewire\Products\ProductCategoryForm;
use App\Livewire\Products\ProductCategoryList;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductList;
use App\Livewire\Products\ProductShow;
use App\Livewire\Suppliers\SupplierForm;
use App\Livewire\Suppliers\SupplierList;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('suppliers', SupplierList::class)->name('suppliers.index');
    Route::get('suppliers/create', SupplierForm::class)->name('suppliers.create');
    Route::get('suppliers/{supplierId}/edit', SupplierForm::class)->name('suppliers.edit');

    Route::get('products/categories', ProductCategoryList::class)->name('products.categories.index');
    Route::get('products/categories/create', ProductCategoryForm::class)->name('products.categories.create');
    Route::get('products/categories/{categoryId}/edit', ProductCategoryForm::class)->name('products.categories.edit');

    Route::get('products', ProductList::class)->name('products.index');
    Route::get('products/create', ProductForm::class)->name('products.create');
    Route::get('products/{id}', ProductShow::class)->name('products.show');
    Route::get('products/{id}/edit', ProductForm::class)->name('products.edit');
});
