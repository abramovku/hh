<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('app:hh-sync')->hourly();
Schedule::command('app:estaff-sync')->hourlyAt(30);
