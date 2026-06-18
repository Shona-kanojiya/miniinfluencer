<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Profile::where(function ($q) {
            $q->whereNull('last_refreshed_at')
              ->orWhere('last_refreshed_at', '<', now()->subHour());
        })
        ->whereNotIn('status', ['fetching'])
        ->each(fn ($p) => FetchProfileJob::dispatch($p->id));
})->name('refresh-stale-profiles')->everyTenMinutes()->withoutOverlapping();