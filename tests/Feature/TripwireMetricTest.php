<?php

use App\Services\TripwireEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
 * These metrics once filtered on corridors whose NAME contained NEAR, not on
 * the provider. Half the board is corridors only NEAR quotes, so dominance
 * read 100% (uncontested) and the spread edge read ~0 (NEAR measured against
 * a pool that was mostly NEAR). Both looked plausible, and the bear case
 * tripped on it.
 */

function quote(string $provider, string $corridor, float $spreadBps, string $batch): void
{
    DB::table('raw_quotes')->insert([
        'batch_id' => $batch,
        'provider' => $provider,
        'corridor' => $corridor,
        'trade_size' => 'medium',
        'size_usd' => 1000,
        'quoted_output' => 1,
        'reference_price' => 1,
        'spread_bps' => $spreadBps,
        'latency_ms' => 100,
        'ok' => true,
        'quoted_at' => now(),
    ]);
}

function evaluateMetrics(): array
{
    DB::statement('REFRESH MATERIALIZED VIEW v_leaderboard');

    return app(TripwireEvaluator::class)->evaluateAll();
}

test('win rate ignores corridors where nothing competes', function () {
    $batch = (string) Str::uuid();

    // Contested: NEAR is beaten here.
    quote('one-click', 'ETH_USDC', 40.0, $batch);
    quote('relay', 'ETH_USDC', 10.0, $batch);

    // Uncontested: NEAR is the only quote, so it "wins" by default.
    quote('one-click', 'NEAR_USDC', 40.0, $batch);

    $dominance = evaluateMetrics()['dominance']['current_value'];

    // Counting the uncontested corridor would drag this up towards 50.
    expect($dominance)->toBe(0.0);
});

test('the spread edge is measured against rivals, not against a pool containing NEAR', function () {
    $batch = (string) Str::uuid();

    quote('one-click', 'ETH_USDC', 10.0, $batch);
    quote('relay', 'ETH_USDC', 30.0, $batch);

    // Corridors only NEAR serves must not dilute the comparison.
    quote('one-click', 'NEAR_USDC', 10.0, $batch);
    quote('one-click', 'USDC_NEAR', 10.0, $batch);

    $edge = evaluateMetrics()['bear_case']['current_value'];

    // Rival median 30 minus NEAR median 10 on the one contested corridor.
    expect($edge)->toBe(20.0);
});

test('competitiveness follows the provider, not corridors named after it', function () {
    $batch = (string) Str::uuid();

    quote('one-click', 'ETH_USDC', 20.0, $batch);
    quote('one-click', 'SOL_USDC', 20.0, $batch);
    // A rival quoting a NEAR-named corridor must not become "NEAR's" median.
    quote('relay', 'NEAR_USDC', 900.0, $batch);

    $median = evaluateMetrics()['competitiveness']['current_value'];

    expect($median)->toBe(20.0);
});
