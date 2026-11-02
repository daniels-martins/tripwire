<?php

use App\Services\Providers\LiFiProvider;
use App\Services\Providers\OneClickProvider;

return [
    'lifi' => [
        'name' => 'LiFi',
        'slot' => 2,
        'class' => LiFiProvider::class,
        'enabled' => env('LIFI_ENABLED', true),
        'base_url' => 'https://li.quest',
    ],

    'one-click' => [
        'name' => 'NEAR 1-Click',
        'slot' => 1,
        'class' => OneClickProvider::class,
        'enabled' => true,
        'base_url' => 'https://1click.chaindefuser.com',
        'api_key' => env('ONECLICK_API_KEY'),
    ],

    'relay' => [
        'name' => 'Relay',
        'class' => 'App\Services\Providers\RelayProvider',
        'enabled' => env('RELAY_ENABLED', false),
        'base_url' => 'https://api.relay.link',
        'api_key' => env('RELAY_API_KEY'),
    ],

    'stargate' => [
        'name' => 'Stargate',
        'class' => 'App\Services\Providers\StargateProvider',
        'enabled' => env('STARGATE_ENABLED', false),
        'base_url' => 'https://api.stargate.finance',
        'api_key' => env('STARGATE_API_KEY'),
    ],

    'across' => [
        'name' => 'Across',
        'class' => 'App\Services\Providers\AcrossProvider',
        'enabled' => env('ACROSS_ENABLED', false),
        'base_url' => 'https://app.across.to/api',
        'api_key' => env('ACROSS_API_KEY'),
    ],

    'debridge' => [
        'name' => 'deBridge',
        'class' => 'App\Services\Providers\DeBridgeProvider',
        'enabled' => env('DEBRIDGE_ENABLED', false),
        'base_url' => 'https://deswap.debridge.finance/v1.0',
        'api_key' => env('DEBRIDGE_API_KEY'),
    ],
];
