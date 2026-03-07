<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Schedule
|--------------------------------------------------------------------------
|
| Define your application's command schedule here. Commands defined here
| will run on the schedule you specify.
|
*/

// Scheduled publishing safety net: run every minute as backup to delayed queue jobs
Schedule::command('numen:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();
