<?php

use Illuminate\Support\Facades\Schedule;

// Weekly reports are due by Monday morning; check the week that has just closed.
Schedule::command('alerts:evaluate')->weeklyOn(1, '07:00')->timezone('Africa/Dar_es_Salaam');

// One summary a day for people who prefer a digest to individual emails.
Schedule::command('alerts:digest')->dailyAt('07:30')->timezone('Africa/Dar_es_Salaam');
