<?php

use App\Jobs\PollCisaAdvisories;
use App\Jobs\ScanAssetExposure;
use App\Models\Asset;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exposure:scan-due', function () {
    Asset::withoutGlobalScopes()->where('data_source_type', 'cloud_scan')
        ->whereNotNull('external_ip')->pluck('id')->each(fn ($id) => ScanAssetExposure::dispatch($id));
    $this->info('Queued exposure checks for eligible assets.');
})->purpose('Queue Shodan exposure checks for cloud-monitored assets');

Schedule::command('exposure:scan-due')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('advisories:poll', function () {
    PollCisaAdvisories::dispatch();
    $this->info('Queued the CISA advisory feed poll.');
})->purpose('Queue ingestion of recent CISA OT CSAF advisories');

Schedule::command('advisories:poll')->dailyAt('03:00')->withoutOverlapping();
