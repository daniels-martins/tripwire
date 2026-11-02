<?php

namespace App\Services;

use App\Models\ReferencePrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReferencePriceFetcher
{
    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    public function fetchForCorridor(string $corridorKey, array $corridorConfig): ?float
    {
        $from = $corridorConfig['from'];
        $to = $corridorConfig['to'];

        try {
            // Always derive from the USD legs rather than a direct cross symbol.
            // Cross pairs are thin on secondary venues: binance.us quoted SOLETH
            // at 0.0680 while SOLUSDT/ETHUSDT gave 0.0412, a 65% error that read
            // as a 3,992 bps spread. The USD legs are the liquid ones.
            $fromUsd = $this->usdPrice($from);
            $toUsd = $this->usdPrice($to);

            if (! $fromUsd || ! $toUsd || $toUsd <= 0) {
                Log::warning('Reference price unavailable', [
                    'corridor' => $corridorKey,
                    'from' => $from,
                    'to' => $to,
                ]);

                return null;
            }

            $price = $fromUsd / $toUsd;

            if ($price <= 0) {
                return null;
            }

            ReferencePrice::create([
                'pair' => $corridorKey,
                'price' => $price,
                'source' => 'derived',
                'fetched_at' => now(),
            ]);

            return $price;
        } catch (\Throwable $e) {
            Log::error('Reference price exception', [
                'corridor' => $corridorKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Price of one unit of $asset in USD. Stablecoins are taken as 1. */
    public function usdPrice(string $asset): ?float
    {
        if (in_array($asset, ['USDC', 'USDT', 'USD'], true)) {
            return 1.0;
        }

        foreach ((array) config('reference.quote_fallbacks', ['USDT']) as $quote) {
            $response = Http::timeout(15)->get(
                config('reference.base_url').'/api/v3/ticker/price',
                ['symbol' => $asset.$quote]
            );

            if ($response->successful()) {
                $price = (float) $response->json('price');

                if ($price > 0) {
                    return $price;
                }
            }
        }

        return null;
    }
}
