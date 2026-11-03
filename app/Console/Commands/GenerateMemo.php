<?php

namespace App\Console\Commands;

use App\Services\TripwireEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateMemo extends Command
{
    protected $signature = 'memo:generate';

    protected $description = 'Generate investment thesis memo with live data and tripwire evaluations';

    public function handle(TripwireEvaluator $evaluator): int
    {
        $this->info('Evaluating tripwires...');
        $tripwires = $evaluator->evaluateAll();

        $this->info('Gathering data...');
        $data = $this->gatherData($tripwires);

        $outputDir = storage_path('app/memo');
        File::ensureDirectoryExists($outputDir);

        $resultsPath = $outputDir.'/results.json';
        File::put($resultsPath, (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Wrote {$resultsPath}");

        $memo = $this->renderMemo($data);
        $memoPath = $outputDir.'/investment-memo.md';
        File::put($memoPath, $memo);
        $this->info("Wrote {$memoPath}");

        $this->displayTripwireStatus($tripwires);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $tripwires
     * @return array<string, mixed>
     */
    private function gatherData(array $tripwires): array
    {
        $leaderboard = DB::table('v_leaderboard')
            ->orderBy('corridor')
            ->orderBy('trade_size')
            ->get()
            ->toArray();

        $corridorCoverage = DB::table('mv_corridor_coverage')
            ->orderBy('corridor')
            ->get()
            ->toArray();

        $settlementAccuracy = DB::table('mv_settlement_accuracy')
            ->orderBy('corridor')
            ->get()
            ->toArray();

        $spreadSeries = DB::table('mv_spread_series')
            ->orderBy('hour')
            ->get()
            ->toArray();

        $dataWindow = DB::table('raw_quotes')
            ->selectRaw('MIN(quoted_at) AS earliest, MAX(quoted_at) AS latest, COUNT(*) AS total_quotes')
            ->first();

        // One definition, shared with the tripwires.
        $comparison = app(TripwireEvaluator::class)->spreadComparison();
        $nearMedian = $comparison['near_median'];
        $rivalMedian = $comparison['rival_median'];

        $settlementCount = DB::table('settlements')->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'data_window' => [
                'start' => $dataWindow?->earliest,
                'end' => $dataWindow?->latest,
            ],
            'total_quotes' => $dataWindow ? (int) $dataWindow->total_quotes : 0,
            'total_settlements' => $settlementCount,
            'corridor_count' => count($corridorCoverage),
            'near_median_spread_bps' => $nearMedian,
            'rival_median_spread_bps' => $rivalMedian,
            'spread_edge_bps' => $comparison['edge'] !== null ? round($comparison['edge'], 4) : null,
            'tripwires' => $tripwires,
            'leaderboard' => $leaderboard,
            'corridor_coverage' => $corridorCoverage,
            'settlement_accuracy' => $settlementAccuracy,
            'spread_series' => $spreadSeries,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderMemo(array $data): string
    {
        $template = File::get(resource_path('memo/template.md'));

        $tripwireRows = '';
        foreach ($data['tripwires'] as $tw) {
            $statusIcon = match ($tw['status']) {
                'HOLDING' => 'HOLDING',
                'TRIPPED' => '**TRIPPED**',
                default => 'PENDING',
            };
            $currentDisplay = $tw['current_value'] !== null ? number_format($tw['current_value'], 2) : 'N/A';
            $tripwireRows .= "| {$tw['name']} | {$tw['operator']} {$tw['threshold']} | {$currentDisplay} | {$tw['threshold']} | {$statusIcon} |\n";
        }

        $leaderboardRows = '';
        foreach ($data['leaderboard'] as $row) {
            $leaderboardRows .= sprintf(
                "| %s | %s | %s | %s | %d |\n",
                $row->corridor,
                $row->trade_size,
                $row->median_spread_bps,
                number_format((float) $row->win_rate * 100, 1).'%',
                $row->sample_count,
            );
        }

        $corridorRows = '';
        foreach ($data['corridor_coverage'] as $row) {
            $providers = is_array($row->providers) ? implode(', ', $row->providers) : $row->providers;
            $corridorRows .= sprintf(
                "| %s | %s | %d | %s%% |\n",
                $row->corridor,
                $providers,
                $row->quote_count,
                $row->success_rate,
            );
        }

        $settlementSection = count($data['settlement_accuracy']) > 0
            ? $this->renderSettlementTable($data['settlement_accuracy'])
            : "Settlement slippage data is not yet available. The ingester captures deposit transactions from `intents.near`, but actual delivered amounts require correlating separate solver settlement transactions. Deposit counts and corridors are tracked; slippage measurement is a planned enhancement.\n\n**Settlements ingested**: {$data['total_settlements']}";

        $replacements = [
            '{{ generated_at }}' => $data['generated_at'],
            '{{ data_window_start }}' => $data['data_window']['start'] ?? 'N/A',
            '{{ data_window_end }}' => $data['data_window']['end'] ?? 'N/A',
            '{{ total_quotes }}' => number_format($data['total_quotes']),
            '{{ corridor_count }}' => (string) $data['corridor_count'],
            '{{ tripwire_table }}' => $tripwireRows,
            '{{ near_median_spread }}' => $data['near_median_spread_bps'] !== null ? number_format($data['near_median_spread_bps'], 2) : 'N/A',
            '{{ global_median_spread }}' => $data['rival_median_spread_bps'] !== null ? number_format($data['rival_median_spread_bps'], 2) : 'N/A',
            '{{ spread_edge }}' => $data['spread_edge_bps'] !== null ? number_format($data['spread_edge_bps'], 2) : 'N/A',
            '{{ leaderboard_table }}' => $leaderboardRows,
            '{{ corridor_table }}' => $corridorRows,
            '{{ settlement_section }}' => $settlementSection,
            '{{ competitiveness_threshold }}' => '50',
            '{{ dominance_threshold }}' => '40',
            '{{ execution_threshold }}' => '50',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * @param  list<\stdClass>  $rows
     */
    private function renderSettlementTable(array $rows): string
    {
        $table = "| Corridor | Provider | Avg Slippage (bps) | Median Slippage (bps) | Samples |\n";
        $table .= "|----------|----------|--------------------|-----------------------|---------|\n";

        foreach ($rows as $row) {
            /** @var object{corridor: string, provider: string, avg_slippage_bps: ?string, median_slippage_bps: ?string, sample_count: int} $row */
            $table .= sprintf(
                "| %s | %s | %s | %s | %d |\n",
                $row->corridor,
                $row->provider,
                $row->avg_slippage_bps ?? 'N/A',
                $row->median_slippage_bps ?? 'N/A',
                $row->sample_count,
            );
        }

        return $table;
    }

    /**
     * @param  array<string, mixed>  $tripwires
     */
    private function displayTripwireStatus(array $tripwires): void
    {
        $this->newLine();
        $this->info('Tripwire Status:');

        foreach ($tripwires as $tw) {
            $icon = match ($tw['status']) {
                'HOLDING' => '  [HOLDING]',
                'TRIPPED' => '  [TRIPPED]',
                default => '  [PENDING]',
            };
            $value = $tw['current_value'] !== null ? number_format($tw['current_value'], 2) : 'N/A';
            $this->line("  {$icon} {$tw['name']}: {$value} ({$tw['operator']} {$tw['threshold']})");
        }
    }
}
