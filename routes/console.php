<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quotes:poll')->everyFiveMinutes();

Schedule::command('settlements:ingest')->everyFifteenMinutes();

Schedule::command('views:refresh')->everyFifteenMinutes();
