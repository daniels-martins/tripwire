<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);
test('the scoreboard lists every route, priced ones first', function () {
    $batch = (string) Str::uuid();
    foreach ([['one-click', 40.0], ['relay', 10.0]] as [$p, $bps]) {
        DB::table('raw_quotes')->insert([
            'batch_id' => $batch, 'provider' => $p, 'corridor' => 'ETH_USDC',
            'trade_size' => 'medium', 'size_usd' => 1000, 'quoted_output' => 1,
            'reference_price' => 1, 'spread_bps' => $bps, 'latency_ms' => 100,
            'ok' => true, 'quoted_at' => now(),
        ]);
    }
    // lifi tried and failed; debridge never recorded anything
    DB::table('raw_quotes')->insert([
        'batch_id' => $batch, 'provider' => 'lifi', 'corridor' => 'ETH_USDC',
        'trade_size' => 'medium', 'size_usd' => 1000, 'quoted_output' => 0,
        'reference_price' => 0, 'spread_bps' => 0, 'latency_ms' => 50,
        'ok' => false, 'error' => 'Upstream HTTP 521', 'quoted_at' => now(),
    ]);

    $page = Livewire::test('pages::scoreboard', ['corridor' => 'ETH_USDC', 'size' => 'medium']);
    $rows = $page->instance()->rows();

    expect(array_column($rows, 'provider'))->toBe(['relay', 'one-click', 'lifi', 'debridge'])
        ->and($rows[0]->quoted)->toBeTrue()
        ->and($rows[2]->quoted)->toBeFalse()
        ->and($rows[2]->reason)->toBe('Upstream HTTP 521')
        ->and($rows[3]->reason)->toBe('No quote recorded');
});

test('the front page opens where routes actually compete', function () {
    $batch = (string) Str::uuid();

    $rows = [
        // alphabetically first, but only NEAR serves it
        ['BTC_ETH', 'one-click'],
        // later alphabetically, and genuinely contested
        ['ETH_USDC', 'one-click'], ['ETH_USDC', 'relay'], ['ETH_USDC', 'lifi'],
    ];

    foreach ($rows as [$corridor, $provider]) {
        DB::table('raw_quotes')->insert([
            'batch_id' => $batch, 'provider' => $provider, 'corridor' => $corridor,
            'trade_size' => 'medium', 'size_usd' => 1000, 'quoted_output' => 1,
            'reference_price' => 1, 'spread_bps' => 20.0, 'latency_ms' => 100,
            'ok' => true, 'quoted_at' => now(),
        ]);
    }

    // Landing on BTC_ETH showed one row and read as "only the winner shows".
    expect(Livewire::test('pages::scoreboard')->instance()->corridor)
        ->toBe('ETH_USDC');
});
