<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire')->dailyAt('00:10')->onOneServer();
Schedule::command('subscriptions:send-reminders')->dailyAt('08:00')->onOneServer();
Schedule::command('leave:accrue')->monthlyOn(1, '01:00')->onOneServer();
