<?php

use App\Services\Providers\DeBridgeProvider;
use App\Services\Providers\LiFiProvider;
use App\Services\Providers\OneClickProvider;
use App\Services\Providers\RelayProvider;

/*
| 'platform_url' is where a person goes to actually make the swap. We link to
| each bridge's own site rather than building a pre-filled deep link: the URL
| formats are undocumented and change, and a malformed one would send someone
| to the wrong trade with real money. Quotes here are dry runs, so there is no
| live order to hand over either way.
|
| 'slot' picks the route's colour from the validated palette in viz.css.
| Assigned once and never reassigned: colour follows the route, not its rank,
| so a filter that changes the field does not repaint the survivors.
*/

return [

    'one-click' => [
        'name' => 'NEAR 1-Click',
        'slot' => 1,
        'class' => OneClickProvider::class,
        'enabled' => true,
        'base_url' => 'https://1click.chaindefuser.com',
        'platform_url' => 'https://app.near-intents.org',
        'api_key' => env('ONECLICK_API_KEY'),
    ],

    'lifi' => [
        'name' => 'LiFi',
        'slot' => 2,
        'class' => LiFiProvider::class,
        'enabled' => env('LIFI_ENABLED', true),
        'base_url' => 'https://li.quest',
        'platform_url' => 'https://jumper.exchange',
    ],

    'relay' => [
        'name' => 'Relay',
        'slot' => 3,
        'class' => RelayProvider::class,
        'enabled' => env('RELAY_ENABLED', true),
        'base_url' => 'https://api.relay.link',
        'platform_url' => 'https://relay.link',
        'api_key' => env('RELAY_API_KEY'),
    ],

    'debridge' => [
        'name' => 'deBridge',
        'slot' => 4,
        'class' => DeBridgeProvider::class,
        'enabled' => env('DEBRIDGE_ENABLED', true),
        'base_url' => 'https://dln.debridge.finance',
        'platform_url' => 'https://app.debridge.finance',
    ],

    /*
     | Across moves the SAME asset between chains -- USDC on Ethereum to USDC
     | on Base. Every corridor here swaps one asset for another, so Across has
     | no quote to give on any of them. Left disabled rather than polled for
     | guaranteed failures, which would read as unreliability rather than as
     | a different product.
     */
    'across' => [
        'name' => 'Across',
        'slot' => 5,
        'class' => null,
        'enabled' => false,
        'base_url' => 'https://app.across.to',
        'platform_url' => 'https://app.across.to',
        'note' => 'Same-asset bridge; cannot quote cross-asset corridors.',
    ],

    /*
     | Stargate's quote API returns "deprecated" and points at LayerZero's VT
     | endpoint, which 404s for these pairs. Disabled until there is a working
     | endpoint to call.
     */
    'stargate' => [
        'name' => 'Stargate',
        'slot' => 6,
        'class' => null,
        'enabled' => false,
        'base_url' => 'https://stargate.finance',
        'platform_url' => 'https://stargate.finance',
        'note' => 'Quote API deprecated; successor endpoint 404s for these pairs.',
    ],
];
