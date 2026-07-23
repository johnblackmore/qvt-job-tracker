<?php

use App\Livewire\Quotes\QuoteBuilder;
use App\Livewire\Quotes\QuoteList;
use App\Livewire\Quotes\QuoteShow;
use App\Livewire\SampleQuotes\SampleQuoteForm;
use App\Livewire\SampleQuotes\SampleQuoteList;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('quotes', QuoteList::class)->name('quotes.index');
    Route::get('quotes/create', QuoteBuilder::class)->name('quotes.create');
    Route::get('quotes/create/from-sample/{sampleQuoteId}', QuoteBuilder::class)->name('quotes.create-from-sample');
    Route::get('quotes/create/from-existing/{sourceQuoteId}', QuoteBuilder::class)->name('quotes.create-from-existing');
    Route::get('quotes/{id}', QuoteShow::class)->name('quotes.show');
    Route::get('quotes/{quoteId}/edit', QuoteBuilder::class)->name('quotes.edit');

    Route::get('sample-quotes', SampleQuoteList::class)->name('sample-quotes.index');
    Route::get('sample-quotes/create', SampleQuoteForm::class)->name('sample-quotes.create');
    Route::get('sample-quotes/{sampleQuoteId}/edit', SampleQuoteForm::class)->name('sample-quotes.edit');
});
