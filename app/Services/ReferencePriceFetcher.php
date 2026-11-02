<?php

namespace App\Services;

use App\Models\ReferencePrice;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReferencePriceFetcher
{
    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    public function fetchForCorridor(string $corridorKey, array $corridorConfig): ?float
    {
        $symbol = $corridorConfig['binance_symbol'];
        $invert = $corridorConfig['binance_invert'] ?? false;

        try {
            $response = $this->tickerWithFallback($symbol);

            if (! $response || ! $response->successful()) {
                Log::warning('Binance price fetch failed', [
                    'symbol' => $symbol,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $price = (float) $response->json('price');

            if ($price <= 0) {
                return null;
            }

            if ($invert) {
                $price = 1 / $price;
            }

            ReferencePrice::create([
                'pair' => $corridorKey,
                'price' => $price,
                'source' => 'binance',
                'fetched_at' => now(),
            ]);

            return $price;
        } catch (\Throwable $e) {
            Log::error('Binance price exception', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getLatestPrice(string $corridorKey): ?float
    {
        $latest = ReferencePrice::where('pair', $corridorKey)
            ->latest('fetched_at')
            ->first();

        return $latest?->price ? (float) $latest->price : null;
    }

    public function fetchOriginPriceUsd(string $tokenSymbol): ?float
    {
        $symbols = [$tokenSymbol.'USDC', $tokenSymbol.'USDT'];

        foreach ($symbols as $symbol) {
            try {
                $response = Http::get(config('reference.base_url').'/api/v3/ticker/price', [
                    'symbol' => $symbol,
                ]);

                if ($response->successful()) {
                    $price = (float) $response->json('price');
                    if ($price > 0) {
                        return $price;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Venues do not all list the same quote asset. Try the configured symbol,
     * then the same base against each fallback quote, so one missing pair does
     * not silently drop a corridor from the benchmark.
     */
    private function tickerWithFallback(string $symbol): ?Response
    {
        $candidates = [$symbol];

        foreach ((array) config('reference.quote_fallbacks', []) as $quote) {
            foreach ((array) config('reference.quote_fallbacks', []) as $configured) {
                if ($quote !== $configured && str_ends_with($symbol, $configured)) {
                    $candidates[] = substr($symbol, 0, -strlen($configured)).$quote;
                }
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            $response = Http::timeout(15)
                ->get(config('reference.base_url').'/api/v3/ticker/price', ['symbol' => $candidate]);

            if ($response->successful()) {
                return $response;
            }
        }

        return null;
    }
}
