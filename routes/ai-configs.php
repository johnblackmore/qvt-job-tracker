<?php

use App\Livewire\AiModelConfigs\AiAssistantConfigSettings;
use App\Livewire\AiModelConfigs\AiModelConfigForm;
use App\Livewire\AiModelConfigs\AiModelConfigList;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin/ai')
    ->name('admin.ai.')
    ->group(function () {
        Route::get('/configs', AiModelConfigList::class)->name('configs.index');
        Route::get('/configs/create', AiModelConfigForm::class)->name('configs.create');
        Route::get('/configs/{aiModelConfig}/edit', AiModelConfigForm::class)->name('configs.edit');
        Route::get('/assistant-settings', AiAssistantConfigSettings::class)->name('assistant-settings');
    });
