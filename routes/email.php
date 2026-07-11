<?php

use App\Http\Controllers\QuotePdfController;
use App\Livewire\EmailTemplates\EmailTemplateForm;
use App\Livewire\EmailTemplates\EmailTemplateList;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('email-templates', EmailTemplateList::class)->name('email-templates.index');
    Route::get('email-templates/create', EmailTemplateForm::class)->name('email-templates.create');
    Route::get('email-templates/{templateId}/edit', EmailTemplateForm::class)->name('email-templates.edit');

    Route::get('quotes/{quote}/pdf', [QuotePdfController::class, 'download'])->name('quotes.pdf.download');
    Route::get('quotes/{quote}/pdf/preview', [QuotePdfController::class, 'stream'])->name('quotes.pdf.preview');
});
