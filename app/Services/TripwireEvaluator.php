<?php

namespace App\Services;

use App\Models\Tripwire;
use Illuminate\Support\Facades\DB;

class TripwireEvaluator
{
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
        $row = DB::table('v_leaderboard')
            ->whereIn('corridor', ['NEAR_USDC', 'USDC_NEAR'])
            ->selectRaw('(percentile_cont(0.5) WITHIN GROUP (ORDER BY median_spread_bps))::numeric(12,4) AS overall_median')
            ->first();

        return $row?->overall_median ? (float) $row->overall_median : null;
    }

    private function nearWinRate(): ?float
    {
        $row = DB::table('v_leaderboard')
            ->whereIn('corridor', ['NEAR_USDC', 'USDC_NEAR'])
            ->selectRaw('(AVG(win_rate) * 100)::numeric(6,2) AS avg_win_rate')
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
        $nearMedian = $this->nearMedianSpread();

        $globalMedian = DB::table('v_leaderboard')
            ->selectRaw('(percentile_cont(0.5) WITHIN GROUP (ORDER BY median_spread_bps))::numeric(12,4) AS overall_median')
            ->first();

        $globalValue = $globalMedian?->overall_median ? (float) $globalMedian->overall_median : null;

        if ($nearMedian === null || $globalValue === null) {
            return null;
        }

        return $globalValue - $nearMedian;
    }
}
