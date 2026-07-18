<?php

use App\Livewire\AiAssistants\AiAssistantsIndex;
use App\Livewire\AiAssistants\ChatAgentDetail;
use App\Livewire\AiAssistants\EnquiryDraftDetail;
use App\Livewire\AiAssistants\ExpensesAssistantDetail;
use App\Livewire\AiAssistants\ProductExtractorDetail;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/ai/assistants')
    ->name('admin.ai.assistants.')
    ->group(function () {
        Route::get('/', AiAssistantsIndex::class)->name('index');
        Route::get('/chat-agent', ChatAgentDetail::class)->name('chat-agent');
        Route::get('/product-extractor', ProductExtractorDetail::class)->name('product-extractor');
        Route::get('/enquiry-draft', EnquiryDraftDetail::class)->name('enquiry-draft');
        Route::get('/expenses-extractor', ExpensesAssistantDetail::class)->name('expenses-extractor');
    });
