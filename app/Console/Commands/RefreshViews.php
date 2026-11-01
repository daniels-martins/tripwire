<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('views:refresh')]
#[Description('Refresh materialized views for dashboard analytics')]
class RefreshViews extends Command
{
    public function handle(): int
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY v_leaderboard');
        $this->info('Refreshed v_leaderboard.');

        return self::SUCCESS;
    }
}
