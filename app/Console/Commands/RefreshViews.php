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
    /** @var list<string> */
    private const VIEWS = [
        'v_leaderboard',
        'mv_spread_series',
        'mv_size_curve',
        'mv_settlement_accuracy',
        'mv_corridor_coverage',
    ];

    public function handle(): int
    {
        foreach (self::VIEWS as $view) {
            DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY {$view}");
            $this->info("Refreshed {$view}.");
        }

        return self::SUCCESS;
    }
}
