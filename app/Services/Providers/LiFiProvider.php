<?php

namespace App\Services\Providers;

use App\DTOs\QuoteResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LiFi is a cross-chain aggregator: it routes a swap through whichever bridge
 * is cheapest at the time, which makes it the closest like-for-like comparator
 * to NEAR Intents rather than a single-bridge competitor.
 *
 * Corridors absent from config/comparators.php return null, so they record as
 * "no quote" instead of silently disappearing.
 */
class LiFiProvider implements QuoteProvider
{
    public function name(): string
    {
        return 'lifi';
    }

    public function isEnabled(): bool
    {
        return (bool) config('providers.lifi.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    public function getQuote(string $corridorKey, array $corridorConfig, string $sizeKey, float $sizeUsd): ?QuoteResult
    {
        $map = config("comparators.corridors.{$corridorKey}");

        if (! $map) {
            return null;
        }

        try {
            $fromAmount = $this->baseUnits($sizeUsd, $corridorConfig['from'], (int) $map['from']['decimals']);

            if (! $fromAmount) {
                return null;
            }

            $response = Http::timeout(30)->get(config('providers.lifi.base_url').'/v1/quote', [
                'fromChain' => $map['from']['chain'],
                'toChain' => $map['to']['chain'],
                'fromToken' => $map['from']['token'],
                'toToken' => $map['to']['token'],
                'fromAmount' => $fromAmount,
                'fromAddress' => $map['from_address'],
                'toAddress' => $map['to_address'],
            ]);

            if (! $response->successful()) {
                Log::warning('LiFi quote failed', [
                    'corridor' => $corridorKey,
                    'size' => $sizeKey,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return null;
            }

            $data = $response->json();
            $toAmount = $data['estimate']['toAmount'] ?? null;

            if (! $toAmount) {
                return null;
            }

            // FetchQuoteJob compares against a human-readable destination amount,
            // so convert out of base units here rather than downstream.
            $formatted = $this->fromBaseUnits((string) $toAmount, (int) $map['to']['decimals']);

            return new QuoteResult(
                provider: $this->name(),
                corridor: $corridorKey,
                tradeSize: $sizeKey,
                quotedOutput: $formatted,
                quotedOutputFormatted: $formatted,
                rawResponse: [
                    'tool' => $data['tool'] ?? null,
                    'toAmount' => $toAmount,
                    'toAmountUSD' => $data['estimate']['toAmountUSD'] ?? null,
                    'fromAmountUSD' => $data['estimate']['fromAmountUSD'] ?? null,
                    'toAmountMin' => $data['estimate']['toAmountMin'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('LiFi quote exception', [
                'corridor' => $corridorKey,
                'size' => $sizeKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function baseUnits(float $sizeUsd, string $fromToken, int $decimals): ?string
    {
        $priceUsd = $this->originPriceUsd($fromToken);

        if (! $priceUsd || $priceUsd <= 0) {
            return null;
        }

        return bcmul(
            number_format($sizeUsd / $priceUsd, 18, '.', ''),
            bcpow('10', (string) $decimals, 0),
            0
        );
    }

    private function fromBaseUnits(string $amount, int $decimals): string
    {
        return rtrim(rtrim(
            bcdiv($amount, bcpow('10', (string) $decimals, 0), $decimals),
            '0'
        ), '.') ?: '0';
    }

    /**
     * Same Binance reference the rest of the scanner uses, so a size of $1,000
     * means the same thing for every provider. Cached briefly so a polling
     * sweep does not hit the ticker once per corridor per size.
     */
    private function originPriceUsd(string $token): ?float
    {
        if (in_array($token, ['USDC', 'USDT'], true)) {
            return 1.0;
        }

        return Cache::remember("origin-price:{$token}", 60, function () use ($token) {
            foreach ([$token.'USDC', $token.'USDT'] as $symbol) {
                try {
                    $response = Http::timeout(10)
                        ->get('https://api.binance.com/api/v3/ticker/price', ['symbol' => $symbol]);

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
        });
    }
}
