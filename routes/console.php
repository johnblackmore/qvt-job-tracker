<?php

use App\Banking\Console\ImportTransactionsCommand;
use App\Banking\Console\RefreshBalancesCommand;
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

Artisan::command('banking:import', function () {
    $this->call(ImportTransactionsCommand::class);
})->purpose('Import recent transactions from linked bank accounts');

Schedule::command('banking:import')
    ->hourly()
    ->name('banking-import-transactions');

Artisan::command('banking:refresh-balances', function () {
    $this->call(RefreshBalancesCommand::class);
})->purpose('Refresh cached bank account balances from the banking provider');

Schedule::command('banking:refresh-balances')
    ->everyFourHours()
    ->between('8:00', '20:00')
    ->name('banking-refresh-balances');
