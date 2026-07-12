<?php

use App\Services\NetlifyFormService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('netlify:sync-submissions', function () {
    $result = app(NetlifyFormService::class)->sync();
    $this->info("Processed: {$result['processed']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}");
})->purpose('Sync new Netlify form submissions into enquiries');

Schedule::call(function () {
    app(NetlifyFormService::class)->sync();
})->hourly()->name('sync-netlify-submissions');
