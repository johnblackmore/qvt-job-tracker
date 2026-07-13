<?php

use App\Livewire\Customers\CustomerForm;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Customers\CustomerShow;
use App\Livewire\Enquiries\EnquiryForm;
use App\Livewire\Enquiries\EnquiryList;
use App\Livewire\Enquiries\EnquiryShow;
use App\Livewire\Vehicles\VehicleForm;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('customers', CustomerList::class)->name('customers.index');
    Route::get('customers/create', CustomerForm::class)->name('customers.create');
    Route::get('customers/{id}', CustomerShow::class)->name('customers.show');
    Route::get('customers/{customerId}/edit', CustomerForm::class)->name('customers.edit');
    Route::get('customers/{customerId}/vehicles/create', VehicleForm::class)->name('customers.vehicles.create');
    Route::get('customers/{customerId}/vehicles/{vehicleId}/edit', VehicleForm::class)->name('customers.vehicles.edit');

    Route::get('enquiries', EnquiryList::class)->name('enquiries.index');
    Route::get('enquiries/create', EnquiryForm::class)->name('enquiries.create');
    Route::get('enquiries/{enquiryId}', EnquiryShow::class)->name('enquiries.show');
    Route::get('enquiries/{enquiryId}/edit', EnquiryForm::class)->name('enquiries.edit');
});
