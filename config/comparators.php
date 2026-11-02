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

        /*
         * Bitcoin as a DESTINATION only. Relay refuses to quote a Bitcoin
         * origin without a funded address ("No UTXOs available to spend"),
         * and a dry quote has no wallet behind it. So BTC_USDC and BTC_ETH
         * stay NEAR-only, while ETH_BTC is comparable.
         */
        'ETH_BTC' => [
            'from' => ['chain' => 'ethereum', 'asset' => 'ETH', 'decimals' => 18],
            'to' => ['chain' => 'bitcoin', 'asset' => 'BTC', 'decimals' => 8],
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
        'bitcoin' => [
            // Relay's own identifier for native BTC, from its /chains response.
            'BTC' => 'bc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqmql8k8',
        ],
    ],

    /*
     * Same chain, different number depending on who you ask.
     */
    'chain_ids' => [
        'lifi' => ['ethereum' => 1, 'base' => 8453, 'solana' => 1151111081099710],
        'relay' => ['ethereum' => 1, 'base' => 8453, 'solana' => 792703809, 'bitcoin' => 8253038],
        'debridge' => ['ethereum' => 1, 'base' => 8453, 'solana' => 7565164],
    ],

    'placeholder_address' => [
        'evm' => '0x000000000000000000000000000000000000dEaD',
        'solana' => 'HN7cABqLq46Es1jh92dQQisAq662SmxELLLsHHe4YWrH',
        // BIP173 reference address. Only ever a quote recipient, never funded.
        'bitcoin' => 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4',
    ],
];
