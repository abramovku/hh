<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('app:hh-sync')->everyFiveMinutes();
Schedule::command('app:estaff-sync')->everyTwoMinutes();
