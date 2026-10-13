<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Both cleanups run once a day in the application timezone (UTC). They are safe to rerun, so no
// overlap or single-server locking is used. cleanup-oauth purges Passport records itself, so
// passport:purge must not be scheduled separately; it runs after the demos so their tokens are gone.
foreach (['tasknet:cleanup-demos' => '02:00', 'tasknet:cleanup-oauth' => '02:30'] as $command => $time) {
    Schedule::command($command)
        ->dailyAt($time)
        ->onFailure(fn () => Log::error("Scheduled command {$command} failed."));
}
