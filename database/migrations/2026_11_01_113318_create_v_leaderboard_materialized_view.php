<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW v_leaderboard AS
            WITH scored AS (
                SELECT
                    q.corridor,
                    q.size_usd,
                    q.trade_size,
                    q.provider,
                    q.spread_bps,
                    q.latency_ms,
                    q.quoted_at,
                    MIN(q.spread_bps) OVER (
                        PARTITION BY q.batch_id, q.corridor, q.size_usd
                    ) AS best_spread_bps
                FROM raw_quotes q
                WHERE q.ok AND q.spread_bps IS NOT NULL
            )
            SELECT
                corridor,
                size_usd,
                trade_size,
                provider,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY spread_bps))::numeric(12,4)
                    AS median_spread_bps,
                (AVG(CASE WHEN spread_bps <= best_spread_bps THEN 1 ELSE 0 END))::numeric(6,4)
                    AS win_rate,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms))::integer
                    AS median_latency_ms,
                COUNT(*)::bigint AS sample_count,
                MAX(quoted_at) AS last_seen_at
            FROM scored
            GROUP BY corridor, size_usd, trade_size, provider
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX v_leaderboard_pk ON v_leaderboard (corridor, size_usd, trade_size, provider)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS v_leaderboard');
    }
};
