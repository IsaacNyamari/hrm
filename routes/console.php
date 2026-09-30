<?php

use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire')->dailyAt('00:10')->onOneServer();
Schedule::command('subscriptions:send-reminders')->dailyAt('08:00')->onOneServer();
Schedule::command('leave:accrue')->monthlyOn(1, '01:00')->onOneServer();
Schedule::command('documents:check-expiry')->dailyAt('07:00')->onOneServer();
Schedule::command('attendance:check-devices')->hourly()->onOneServer();

// Delete acknowledged/failed ZKTeco device commands past their retention
// window, so the command log doesn't grow unbounded.
Schedule::command('model:prune', ['--model' => [ZktecoDeviceCommand::class]])->dailyAt('02:00')->onOneServer();
