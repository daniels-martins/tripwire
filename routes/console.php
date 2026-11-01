<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quotes:poll')->everyFiveMinutes();

// Materialized view refresh — runs every 15 minutes (Phase 3)
// Schedule::command('views:refresh')->everyFifteenMinutes();
