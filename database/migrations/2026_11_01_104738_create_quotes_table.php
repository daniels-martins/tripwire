<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            // One sweep across every provider shares a batch_id, so "who was
            // cheapest at this instant" is answerable without time-bucketing.
            $table->uuid('batch_id')->index();
            $table->timestampTz('polled_at');

            $table->string('provider', 32);   // across|jupiter|lifi|near_intents|relay|thorchain
            $table->string('corridor', 64);   // e.g. BTC->USDC.SOL
            $table->integer('size_usd');      // notional bucket: 100 | 10000 | 100000

            $table->decimal('amount_in', 36, 18)->nullable();
            $table->decimal('amount_out', 36, 18)->nullable();
            $table->decimal('reference_price', 24, 10)->nullable();  // CEX mid at polled_at
            $table->decimal('spread_bps', 12, 4)->nullable();        // vs reference, + is worse

            $table->integer('latency_ms')->nullable();
            $table->boolean('ok')->default(true);
            $table->text('error')->nullable();
            $table->jsonb('raw')->nullable();

            $table->timestamps();

            $table->index(['corridor', 'size_usd', 'polled_at']);
            $table->index(['provider', 'polled_at']);
        });

        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW v_leaderboard AS
            WITH scored AS (
                SELECT
                    q.corridor,
                    q.size_usd,
                    q.provider,
                    q.spread_bps,
                    q.latency_ms,
                    q.polled_at,
                    MIN(q.spread_bps) OVER (
                        PARTITION BY q.batch_id, q.corridor, q.size_usd
                    ) AS best_spread_bps
                FROM quotes q
                WHERE q.ok AND q.spread_bps IS NOT NULL
            )
            SELECT
                corridor,
                size_usd,
                provider,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY spread_bps))::numeric(12,4)
                    AS median_spread_bps,
                (AVG(CASE WHEN spread_bps <= best_spread_bps THEN 1 ELSE 0 END))::numeric(6,4)
                    AS win_rate,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms))::integer
                    AS median_latency_ms,
                COUNT(*)::bigint AS sample_count,
                MAX(polled_at) AS last_seen_at
            FROM scored
            GROUP BY corridor, size_usd, provider
        SQL);

        // Unique index is required for REFRESH MATERIALIZED VIEW CONCURRENTLY.
        DB::statement(
            'CREATE UNIQUE INDEX v_leaderboard_pk ON v_leaderboard (corridor, size_usd, provider)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS v_leaderboard');
        Schema::dropIfExists('quotes');
    }
};
