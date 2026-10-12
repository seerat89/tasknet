<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasknet:cleanup-demos')->daily();
// Keep expired access tokens until the refresh tokens that belong to them have expired too,
// so a still-valid refresh token can always be traced back to its user and client.
Schedule::command('passport:purge', ['--hours' => config('tasknet.demo.lifetime_days') * 24])->daily();
