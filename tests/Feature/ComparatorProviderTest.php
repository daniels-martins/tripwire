<?php

use App\Services\Providers\LiFiProvider;
use App\Services\Providers\RelayProvider;

/*
 * The comparator mapping decides which routes may quote which corridors.
 * Getting it wrong is how a provider silently quotes the wrong trade, which
 * is what produced a -7,514,685 bps reading earlier in this project.
 */

function callMapping(object $provider, string $corridor): ?array
{
    $method = new ReflectionMethod($provider, 'mapping');

    return $method->invoke($provider, $corridor);
}

test('an unmapped corridor yields no quote rather than a wrong one', function () {
    expect(callMapping(new LiFiProvider, 'NEAR_USDC'))->toBeNull();
});

test('a chain the provider does not number is treated as unsupported', function () {
    // Bitcoin is only in Relay's chain list, so LiFi must decline ETH_BTC.
    expect(callMapping(new LiFiProvider, 'ETH_BTC'))->toBeNull();
    expect(callMapping(new RelayProvider, 'ETH_BTC'))->not->toBeNull();
});

test('a mapped corridor resolves both ends', function () {
    $map = callMapping(new LiFiProvider, 'ETH_USDC');

    expect($map)->not->toBeNull()
        ->and($map['from_chain'])->toBe(1)
        ->and($map['to_chain'])->toBe(8453)
        ->and($map['from_decimals'])->toBe(18)
        ->and($map['to_decimals'])->toBe(6);
});

test('the same corridor gets a different chain id per provider', function () {
    // Solana is numbered differently by each bridge; mixing them up would
    // quote the wrong chain entirely.
    expect(callMapping(new LiFiProvider, 'SOL_USDC')['from_chain'])->toBe(1151111081099710)
        ->and(callMapping(new RelayProvider, 'SOL_USDC')['from_chain'])->toBe(792703809);
});

test('base units convert back to a human amount', function () {
    $provider = new LiFiProvider;
    $method = new ReflectionMethod($provider, 'fromBaseUnits');

    expect($method->invoke($provider, '22785797', 6))->toBe('22.785797')
        ->and($method->invoke($provider, '1000000', 6))->toBe('1')
        ->and($method->invoke($provider, '0', 6))->toBe('0');
});

test('a declined corridor says why, so causes can be told apart', function () {
    // The reliability page grouped every failure under one "No quote returned"
    // label, which hid an upstream outage among corridors nobody maps.
    $unconfigured = new LiFiProvider;
    callMapping($unconfigured, 'NEAR_USDC');

    $unserved = new LiFiProvider;
    callMapping($unserved, 'ETH_BTC');

    expect($unconfigured->failureReason())->toBe('Corridor not configured')
        ->and($unserved->failureReason())->toBe('Chain not served by this provider');
});

test('a provider that has not declined anything reports no reason', function () {
    $provider = new LiFiProvider;
    callMapping($provider, 'ETH_USDC');

    expect($provider->failureReason())->toBeNull();
});
