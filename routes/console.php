<?php

use Illuminate\Support\Facades\Schedule;

// 96 jobs per sweep (4 routes x 8 corridors x 3 sizes) at roughly 5s each.
// A five-minute cadence queued faster than one worker could drain, leaving a
// backlog that would eventually record stale prices under current timestamps.
Schedule::command('quotes:poll')->everyFifteenMinutes();

Schedule::command('settlements:ingest')->everyFifteenMinutes();

Schedule::command('views:refresh')->everyFifteenMinutes();

// The thesis page says it regenerates from live data; this is what does it.
Schedule::command('memo:generate')->hourly();
