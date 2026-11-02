<?php

/*
|--------------------------------------------------------------------------
| Comparator corridor mappings
|--------------------------------------------------------------------------
|
| config/corridors.php describes corridors in NEAR Intents terms, using
| nep141 asset identifiers. Competing bridges address assets by chain and
| contract instead, and each of them numbers chains differently -- Solana is
| 1151111081099710 to LiFi, 792703809 to Relay and 7565164 to deBridge. So
| corridors are described here in neutral names and each provider translates.
|
| A corridor with no entry is one the comparators cannot serve. That is left
| as a real gap: which corridors a route does NOT cover is part of the
| finding, and NEAR Intents covering all eight is the point.
|
| Caveat: NEAR Intents settles to its own ledger while these routes deliver
| to a named chain. The economic question is the same -- "I hold X, I want Y,
| who gives me most?" -- but the destinations are not identical.
|
*/

return [

    'corridors' => [
        'ETH_USDC' => [
            'from' => ['chain' => 'ethereum', 'asset' => 'ETH', 'decimals' => 18],
            'to' => ['chain' => 'base', 'asset' => 'USDC', 'decimals' => 6],
        ],
        'SOL_USDC' => [
            'from' => ['chain' => 'solana', 'asset' => 'SOL', 'decimals' => 9],
            'to' => ['chain' => 'ethereum', 'asset' => 'USDC', 'decimals' => 6],
        ],
        'SOL_ETH' => [
            'from' => ['chain' => 'solana', 'asset' => 'SOL', 'decimals' => 9],
            'to' => ['chain' => 'ethereum', 'asset' => 'ETH', 'decimals' => 18],
        ],
    ],

    'tokens' => [
        'ethereum' => [
            'ETH' => '0x0000000000000000000000000000000000000000',
            'USDC' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ],
        'base' => [
            'ETH' => '0x0000000000000000000000000000000000000000',
            'USDC' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        ],
        'solana' => [
            'SOL' => '11111111111111111111111111111111',
            'USDC' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        ],
    ],

    /*
     * Same chain, different number depending on who you ask.
     */
    'chain_ids' => [
        'lifi' => ['ethereum' => 1, 'base' => 8453, 'solana' => 1151111081099710],
        'relay' => ['ethereum' => 1, 'base' => 8453, 'solana' => 792703809],
        'debridge' => ['ethereum' => 1, 'base' => 8453, 'solana' => 7565164],
    ],

    'placeholder_address' => [
        'evm' => '0x000000000000000000000000000000000000dEaD',
        'solana' => 'HN7cABqLq46Es1jh92dQQisAq662SmxELLLsHHe4YWrH',
    ],
];
