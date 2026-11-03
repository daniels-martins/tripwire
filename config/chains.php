<?php

/*
|--------------------------------------------------------------------------
| Chain aliases
|--------------------------------------------------------------------------
|
| NEAR names chains with short codes; the comparators each use their own.
| `registry:sync` resolves these aliases against every provider's live chain
| endpoint, so the numeric ids are never hand-typed. Getting one wrong quotes
| a different chain and still returns a plausible number, which is how this
| project once recorded -7,514,685 bps.
|
| A chain missing here is not an error. It means NEAR serves it and we have
| not matched it to a comparator, so it quotes through 1-Click alone.
|
*/

return [

    'aliases' => [
        // near code => [lifi key, relay name, debridge name]
        'eth' => ['eth', 'ethereum', 'ethereum'],
        'arb' => ['arb', 'arbitrum', 'arbitrum'],
        'base' => ['bas', 'base', 'base'],
        'op' => ['opt', 'optimism', 'optimism'],
        'pol' => ['pol', 'polygon', 'polygon'],
        'avax' => ['ava', 'avalanche', 'avalanche'],
        'bsc' => ['bsc', 'bsc', 'bsc'],
        'sol' => ['sol', 'solana', 'solana'],
        'btc' => ['btc', 'bitcoin', 'bitcoin'],
        'gnosis' => ['dai', 'gnosis', 'gnosis'],
        'scroll' => ['scl', 'scroll', 'scroll'],
        'monad' => ['mon', 'monad', 'monad'],
        'tron' => ['tro', 'tron', 'tron'],
        'bera' => ['ber', 'berachain', 'berachain'],
        'abs' => ['abs', 'abstract', 'abstract'],
        'ton' => ['ton', 'ton', 'ton'],
        'sui' => ['sui', 'sui', 'sui'],
        'zec' => ['zec', 'zcash', 'zcash'],
    ],

    /*
     | Names that look like a match but are not the same execution
     | environment. HyperCore is Hyperliquid's order book; LiFi and deBridge
     | expose HyperEVM, and Relay carries both under separate ids. Guessing
     | between them would quote the wrong chain, so NEAR serves these alone
     | until someone verifies the pairing by hand.
     */
    'ambiguous' => ['hypercore'],

    /*
     | Known ids, asserted on every sync. If a provider renumbers a chain or
     | an alias silently stops matching, the sync fails loudly instead of
     | writing a registry that quotes the wrong asset.
     */
    'known_ids' => [
        'eth' => ['lifi' => 1, 'relay' => 1, 'debridge' => 1],
        'base' => ['lifi' => 8453, 'relay' => 8453, 'debridge' => 8453],
        'sol' => ['lifi' => 1151111081099710, 'relay' => 792703809, 'debridge' => 7565164],
    ],

];
