<?php

namespace App\Services;

use App\Models\ReferencePrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReferencePriceFetcher
{
    public function fetchForCorridor(string $corridorKey, array $corridorConfig): ?float
    {
        $symbol = $corridorConfig['binance_symbol'];
        $invert = $corridorConfig['binance_invert'] ?? false;

        try {
            $response = Http::get('https://api.binance.com/api/v3/ticker/price', [
                'symbol' => $symbol,
            ]);

            if (! $response->successful()) {
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
                $response = Http::get('https://api.binance.com/api/v3/ticker/price', [
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
}
