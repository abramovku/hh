<?php

use App\Support\Flow;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:estaff-auto-webhook')->everyTenMinutes();
Schedule::command('app:log-rotate')->daily();

if (Flow::newFlowEnabled()) {
    // ТЗ 6: reminder calls in the 09:30–17:00 window on the interview day, feedback calls the day after.
    Schedule::command('app:flow-reminders')
        ->everyFifteenMinutes()
        ->timezone(Flow::timezone())
        ->between('9:30', '17:00')
        ->withoutOverlapping();

    Schedule::command('app:flow-feedback')
        ->hourly()
        ->timezone(Flow::timezone())
        ->between('9:30', '17:00')
        ->withoutOverlapping();
}

if (! Flow::isNew()) {
    // Legacy: HH.ru responses → Estaff → WhatsApp / call. In hybrid mode EstaffSync skips new-flow vacancies.
    Schedule::command('app:hh-sync')->everyFiveMinutes();
    Schedule::command('app:estaff-sync')->everyTwoMinutes();
}
