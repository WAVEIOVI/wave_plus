<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('inventory:cancel-expired-reservations')->hourly();
Schedule::command('email:sync-mailboxes')->everyFiveMinutes();
Schedule::command('email:run-automation')->hourly();
