<?php

use App\Services\Providers\AcrossProvider;
use App\Services\Providers\DeBridgeProvider;
use App\Services\Providers\OneClickProvider;
use App\Services\Providers\RelayProvider;
use App\Services\Providers\StargateProvider;

return [
    'one-click' => [
        'name' => 'NEAR 1-Click',
        'class' => OneClickProvider::class,
        'enabled' => true,
        'base_url' => 'https://1click.chaindefuser.com',
        'api_key' => env('ONECLICK_API_KEY'),
    ],

    'relay' => [
        'name' => 'Relay',
        'class' => RelayProvider::class,
        'enabled' => env('RELAY_ENABLED', false),
        'base_url' => 'https://api.relay.link',
        'api_key' => env('RELAY_API_KEY'),
    ],

    'stargate' => [
        'name' => 'Stargate',
        'class' => StargateProvider::class,
        'enabled' => env('STARGATE_ENABLED', false),
        'base_url' => 'https://api.stargate.finance',
        'api_key' => env('STARGATE_API_KEY'),
    ],

    'across' => [
        'name' => 'Across',
        'class' => AcrossProvider::class,
        'enabled' => env('ACROSS_ENABLED', false),
        'base_url' => 'https://app.across.to/api',
        'api_key' => env('ACROSS_API_KEY'),
    ],

    'debridge' => [
        'name' => 'deBridge',
        'class' => DeBridgeProvider::class,
        'enabled' => env('DEBRIDGE_ENABLED', false),
        'base_url' => 'https://deswap.debridge.finance/v1.0',
        'api_key' => env('DEBRIDGE_API_KEY'),
    ],
];
