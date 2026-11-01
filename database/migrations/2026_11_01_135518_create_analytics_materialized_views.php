<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_spread_series AS
            SELECT
                date_trunc('hour', quoted_at) AS hour,
                provider,
                corridor,
                trade_size,
                (AVG(spread_bps))::numeric(12,4) AS avg_spread_bps,
                COUNT(*)::bigint AS sample_count
            FROM raw_quotes
            WHERE ok AND spread_bps IS NOT NULL
            GROUP BY date_trunc('hour', quoted_at), provider, corridor, trade_size
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX mv_spread_series_pk ON mv_spread_series (hour, provider, corridor, trade_size)'
        );

        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_size_curve AS
            SELECT
                provider,
                corridor,
                trade_size,
                size_usd,
                (AVG(spread_bps))::numeric(12,4) AS avg_spread_bps,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY spread_bps))::numeric(12,4)
                    AS median_spread_bps,
                COUNT(*)::bigint AS sample_count
            FROM raw_quotes
            WHERE ok AND spread_bps IS NOT NULL
            GROUP BY provider, corridor, trade_size, size_usd
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX mv_size_curve_pk ON mv_size_curve (provider, corridor, trade_size, size_usd)'
        );

        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_settlement_accuracy AS
            SELECT
                corridor,
                provider,
                (AVG(
                    CASE WHEN quoted_amount > 0 AND delivered_amount IS NOT NULL
                    THEN ((quoted_amount - delivered_amount) / quoted_amount * 10000)
                    ELSE slippage_bps END
                ))::numeric(12,4) AS avg_slippage_bps,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY
                    CASE WHEN quoted_amount > 0 AND delivered_amount IS NOT NULL
                    THEN ((quoted_amount - delivered_amount) / quoted_amount * 10000)
                    ELSE slippage_bps END
                ))::numeric(12,4) AS median_slippage_bps,
                (AVG(quoted_amount))::numeric(20,8) AS avg_quoted_amount,
                (AVG(delivered_amount))::numeric(20,8) AS avg_delivered_amount,
                COUNT(*)::bigint AS sample_count,
                MIN(settled_at) AS earliest_settlement,
                MAX(settled_at) AS latest_settlement
            FROM settlements
            WHERE delivered_amount IS NOT NULL OR slippage_bps IS NOT NULL
            GROUP BY corridor, provider
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX mv_settlement_accuracy_pk ON mv_settlement_accuracy (corridor, provider)'
        );

        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_corridor_coverage AS
            SELECT
                corridor,
                array_agg(DISTINCT provider) AS providers,
                COUNT(*)::bigint AS quote_count,
                COUNT(DISTINCT provider)::integer AS provider_count,
                (AVG(CASE WHEN ok THEN 1 ELSE 0 END) * 100)::numeric(6,2) AS success_rate
            FROM raw_quotes
            GROUP BY corridor
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX mv_corridor_coverage_pk ON mv_corridor_coverage (corridor)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_corridor_coverage');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_settlement_accuracy');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_size_curve');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_spread_series');
    }
};
