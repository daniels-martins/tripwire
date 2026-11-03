<?php

use App\Services\TokenRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/*
 * The registry is what lets a person ask about a corridor nobody polls.
 * Its job is to offer EVERY corridor NEAR serves, including the ones no
 * comparator reaches -- listing only the comparable ones would misreport
 * NEAR's coverage as narrower than it is.
 */

function writeRegistry(array $tokens, array $chains): void
{
    $dir = storage_path('app/'.TokenRegistry::DIRECTORY);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/tokens.json', json_encode($tokens));
    File::put($dir.'/chains.json', json_encode($chains));
    cache()->forget('registry:tokens');
    cache()->forget('registry:chains');
}

beforeEach(function () {
    writeRegistry(
        [
            ['asset_id' => 'nep141:a', 'symbol' => 'USDC', 'chain' => 'eth', 'decimals' => 6, 'contract' => '0xaaa'],
            ['asset_id' => 'nep141:b', 'symbol' => 'USDC', 'chain' => 'arb', 'decimals' => 6, 'contract' => '0xbbb'],
            ['asset_id' => 'nep141:c', 'symbol' => 'XRP', 'chain' => 'xrp', 'decimals' => 6, 'contract' => null],
        ],
        [
            'eth' => ['lifi' => 1, 'relay' => 1, 'debridge' => 1],
            'arb' => ['lifi' => 42161, 'relay' => 42161],
        ]
    );
});

test('a corridor no comparator reaches is still offered', function () {
    $registry = app(TokenRegistry::class);

    // XRP has no comparator entry at all, so nothing else can quote it...
    expect($registry->comparatorsFor('eth', 'xrp'))->toBe([]);

    // ...but the asset is still in the list a person picks from.
    $symbols = array_column($registry->tokens(), 'symbol');
    expect($symbols)->toContain('XRP');
});

test('only comparators that reach BOTH chains are eligible', function () {
    $registry = app(TokenRegistry::class);

    // deBridge covers eth but not arb, so it cannot quote eth -> arb.
    expect($registry->comparatorsFor('eth', 'arb'))
        ->toEqualCanonicalizing(['lifi', 'relay']);
});

test('assets are grouped by chain for the pickers', function () {
    $grouped = app(TokenRegistry::class)->byChain();

    expect(array_keys($grouped))->toEqualCanonicalizing(['eth', 'arb', 'xrp'])
        ->and($grouped['eth'][0]['symbol'])->toBe('USDC');
});
