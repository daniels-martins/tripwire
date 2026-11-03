<?php

namespace App\Services;

use App\Models\Tripwire;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TripwireEvaluator
{
    /** The provider the thesis is about. */
    private const NEAR_PROVIDER = 'one-click';

    /**
     * Buckets where someone other than NEAR also quotes.
     *
     * A win rate or a price edge only means something against competition.
     * Half this board is corridors only NEAR serves, and counting those made
     * dominance read 100% and the edge read ~0 (NEAR measured against a pool
     * that was mostly NEAR).
     */
    private function contestedBuckets(): Builder
    {
        return DB::table('v_leaderboard')
            ->select('corridor', 'trade_size')
            ->groupBy('corridor', 'trade_size')
            ->havingRaw('count(DISTINCT provider) > 1');
    }

    /**
     * Evaluate all tripwires and return their current state.
     *
     * @return array<string, array{name: string, status: string, current_value: ?float, threshold: float, description: string}>
     */
    public function evaluateAll(): array
    {
        $results = [];

        foreach (config('tripwires.definitions', []) as $key => $definition) {
            $currentValue = $this->computeMetric($definition['metric']);

            $status = $this->evaluate($currentValue, $definition['operator'], $definition['threshold']);

            $tripwire = Tripwire::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'description' => $definition['description'],
                    'threshold_value' => $definition['threshold'],
                    'current_value' => $currentValue,
                    'status' => $status,
                    'last_evaluated_at' => now(),
                    'config' => [
                        'metric' => $definition['metric'],
                        'operator' => $definition['operator'],
                    ],
                ]
            );

            $results[$key] = [
                'name' => $tripwire->name,
                'status' => $tripwire->status,
                'current_value' => $currentValue,
                'threshold' => $definition['threshold'],
                'operator' => $definition['operator'],
                'description' => $definition['description'],
            ];
        }

        return $results;
    }

    /**
     * The same three figures the tripwires use, for the memo to quote.
     * They lived twice -- here and in GenerateMemo -- and drifted apart,
     * which is how the memo came to print an edge nothing was measuring.
     *
     * @return array{near_median: ?float, rival_median: ?float, edge: ?float}
     */
    public function spreadComparison(): array
    {
        $near = $this->contestedMedianSpread(true);
        $rivals = $this->contestedMedianSpread(false);

        return [
            'near_median' => $this->nearMedianSpread(),
            'rival_median' => $rivals,
            'edge' => ($near === null || $rivals === null) ? null : $rivals - $near,
        ];
    }

    private function computeMetric(string $metric): ?float
    {
        return match ($metric) {
            'near_median_spread_bps' => $this->nearMedianSpread(),
            'near_win_rate_pct' => $this->nearWinRate(),
            'median_slippage_bps' => $this->medianSlippage(),
            'active_corridor_count' => $this->activeCorridorCount(),
            'spread_edge_bps' => $this->spreadEdge(),
            default => null,
        };
    }

    private function evaluate(?float $value, string $operator, float $threshold): string
    {
        if ($value === null) {
            return 'PENDING';
        }

        $holds = match ($operator) {
            '<' => $value < $threshold,
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<=' => $value <= $threshold,
            default => false,
        };

        return $holds ? 'HOLDING' : 'TRIPPED';
    }

    private function nearMedianSpread(): ?float
    {
        // Keyed on the provider, not on corridors whose NAME contains NEAR.
        // The old filter measured whoever quoted NEAR_USDC and USDC_NEAR --
        // two of the eight corridors NEAR Intents actually serves.
        $row = DB::table('v_leaderboard')
            ->where('provider', self::NEAR_PROVIDER)
            ->selectRaw('(percentile_cont(0.5) WITHIN GROUP (ORDER BY median_spread_bps))::numeric(12,4) AS overall_median')
            ->first();

        return $row?->overall_median ? (float) $row->overall_median : null;
    }

    private function nearWinRate(): ?float
    {
        $row = DB::table('v_leaderboard')
            ->joinSub($this->contestedBuckets(), 'c', function ($join) {
                $join->on('v_leaderboard.corridor', '=', 'c.corridor')
                    ->on('v_leaderboard.trade_size', '=', 'c.trade_size');
            })
            ->where('v_leaderboard.provider', self::NEAR_PROVIDER)
            ->selectRaw('(AVG(v_leaderboard.win_rate) * 100)::numeric(6,2) AS avg_win_rate')
            ->first();

        return $row?->avg_win_rate ? (float) $row->avg_win_rate : null;
    }

    private function medianSlippage(): ?float
    {
        $row = DB::table('mv_settlement_accuracy')
            ->selectRaw('(percentile_cont(0.5) WITHIN GROUP (ORDER BY median_slippage_bps))::numeric(12,4) AS overall_median')
            ->first();

        return $row?->overall_median ? (float) $row->overall_median : null;
    }

    private function activeCorridorCount(): float
    {
        return (float) DB::table('mv_corridor_coverage')
            ->where('success_rate', '>', 0)
            ->count();
    }

    private function spreadEdge(): ?float
    {
        // NEAR against its competitors on the same buckets -- not against a
        // "global" median that included NEAR's own rows, and was dominated by
        // them because NEAR quotes eight corridors to the comparators' three.
        // That comparison returned ~0 and tripped the bear case on nothing.
        $near = $this->contestedMedianSpread(true);
        $rivals = $this->contestedMedianSpread(false);

        if ($near === null || $rivals === null) {
            return null;
        }

        return $rivals - $near;
    }

    /** Median spread over contested buckets, for NEAR or for everyone else. */
    private function contestedMedianSpread(bool $near): ?float
    {
        $row = DB::table('v_leaderboard')
            ->joinSub($this->contestedBuckets(), 'c', function ($join) {
                $join->on('v_leaderboard.corridor', '=', 'c.corridor')
                    ->on('v_leaderboard.trade_size', '=', 'c.trade_size');
            })
            ->where('v_leaderboard.provider', $near ? '=' : '!=', self::NEAR_PROVIDER)
            ->selectRaw('(percentile_cont(0.5) WITHIN GROUP (ORDER BY v_leaderboard.median_spread_bps))::numeric(12,4) AS m')
            ->first();

        return $row?->m !== null ? (float) $row->m : null;
    }
}
