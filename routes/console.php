<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:remind')->everyFiveMinutes();
Schedule::command('attendance:auto-punch-out')->everyFiveMinutes();
Schedule::command('tasks:remind-overdue')->dailyAt('10:00');
Schedule::command('meeting:remind')->everyMinute();
