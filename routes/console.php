<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quotes:poll')->everyFiveMinutes();

Schedule::command('settlements:ingest')->everyFifteenMinutes();

Schedule::command('views:refresh')->everyFifteenMinutes();

// The thesis page says it regenerates from live data; this is what does it.
Schedule::command('memo:generate')->hourly();
