<?php

/*
|--------------------------------------------------------------------------
| Comparator corridor mappings
|--------------------------------------------------------------------------
|
| config/corridors.php describes each corridor in NEAR Intents terms, using
| nep141 asset identifiers. Competing bridges address assets by chain id and
| contract address instead, so this file says what each corridor means to
| them.
|
| A corridor with no entry here is one the comparator cannot serve. That is
| left as a genuine gap rather than papered over: which corridors a route
| does NOT cover is part of the finding.
|
| Caveat worth keeping in mind when reading the dashboard: NEAR Intents
| settles USDC to its own ledger, while these routes deliver to a specific
| chain. The economic question ("I hold X, I want Y, who gives me most?") is
| the same, but the destinations are not identical.
|
*/

return [

    'chains' => [
        'ethereum' => 1,
        'base' => 8453,
        'arbitrum' => 42161,
        'solana' => 1151111081099710,
    ],

    'native' => [
        'evm' => '0x0000000000000000000000000000000000000000',
        'solana' => '11111111111111111111111111111111',
    ],

    /*
     * from/to: [chain id, token address, decimals]
     */
    'corridors' => [

        'ETH_USDC' => [
            'from' => ['chain' => 1, 'token' => '0x0000000000000000000000000000000000000000', 'decimals' => 18],
            'to' => ['chain' => 8453, 'token' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', 'decimals' => 6],
            'from_address' => '0x000000000000000000000000000000000000dEaD',
            'to_address' => '0x000000000000000000000000000000000000dEaD',
        ],

        'SOL_USDC' => [
            'from' => ['chain' => 1151111081099710, 'token' => '11111111111111111111111111111111', 'decimals' => 9],
            'to' => ['chain' => 1, 'token' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'decimals' => 6],
            'from_address' => 'HN7cABqLq46Es1jh92dQQisAq662SmxELLLsHHe4YWrH',
            'to_address' => '0x000000000000000000000000000000000000dEaD',
        ],

        'SOL_ETH' => [
            'from' => ['chain' => 1151111081099710, 'token' => '11111111111111111111111111111111', 'decimals' => 9],
            'to' => ['chain' => 1, 'token' => '0x0000000000000000000000000000000000000000', 'decimals' => 18],
            'from_address' => 'HN7cABqLq46Es1jh92dQQisAq662SmxELLLsHHe4YWrH',
            'to_address' => '0x000000000000000000000000000000000000dEaD',
        ],

        /*
         * BTC_USDC, BTC_ETH, ETH_BTC, NEAR_USDC and USDC_NEAR have no entry:
         * neither LiFi nor Relay covers NEAR, and BTC support is patchy. Those
         * corridors stay NEAR-only in the data, which is itself a result.
         */
    ],
];
