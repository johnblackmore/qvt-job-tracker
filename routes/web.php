<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
require __DIR__.'/customers.php';
require __DIR__.'/products.php';
require __DIR__.'/quotes.php';
require __DIR__.'/email.php';
