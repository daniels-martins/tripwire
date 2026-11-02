<?php

/*
|--------------------------------------------------------------------------
| Reference price source
|--------------------------------------------------------------------------
|
| Spread is measured against a centralised-exchange mid price, so the whole
| benchmark depends on reaching one. api.binance.com answers 451 from some
| regions -- including the deployment host -- which fails every quote for
| every route, not just one.
|
| api.binance.us speaks the identical endpoint and payload, so switching is
| a base-URL change. It carries fewer pairs, hence the quote-asset fallback
| below: NEARUSDC does not exist there while NEARUSDT does.
|
*/

return [
    'base_url' => env('REFERENCE_BASE_URL', 'https://api.binance.com'),

    // Tried in order when the configured symbol is unavailable.
    'quote_fallbacks' => ['USDC', 'USDT', 'USD'],
];
