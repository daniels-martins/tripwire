<?php

namespace App\Services\Providers;

use App\DTOs\QuoteResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * deBridge's DLN is an order-based liquidity network rather than a pooled
 * bridge, so its pricing behaves differently at size -- worth having in the
 * comparison for exactly that reason.
 */
class DeBridgeProvider extends AbstractComparatorProvider
{
    public function name(): string
    {
        return 'debridge';
    }

    protected function chainKey(): string
    {
        return 'debridge';
    }

    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    public function getQuote(string $corridorKey, array $corridorConfig, string $sizeKey, float $sizeUsd): ?QuoteResult
    {
        $map = $this->mapping($corridorKey);

        if (! $map) {
            return null;
        }

        try {
            $amount = $this->baseUnits($sizeUsd, $corridorConfig['from'], $map['from_decimals']);

            if (! $amount) {
                return null;
            }

            $response = Http::timeout(30)
                ->get(config('providers.debridge.base_url').'/v1.0/dln/order/quote', [
                    'srcChainId' => $map['from_chain'],
                    'srcChainTokenIn' => $map['from_token'],
                    'srcChainTokenInAmount' => $amount,
                    'dstChainId' => $map['to_chain'],
                    'dstChainTokenOut' => $map['to_token'],
                    'prependOperatingExpenses' => 'false',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $out = $response->json('estimation.dstChainTokenOut.amount');

            if (! $out) {
                return null;
            }

            $formatted = $this->fromBaseUnits((string) $out, $map['to_decimals']);

            return new QuoteResult(
                provider: $this->name(),
                corridor: $corridorKey,
                tradeSize: $sizeKey,
                quotedOutput: $formatted,
                quotedOutputFormatted: $formatted,
                rawResponse: [
                    'amount' => $out,
                    'recommendedAmount' => $response->json('estimation.dstChainTokenOut.recommendedAmount'),
                    'srcAmountUsd' => $response->json('estimation.srcChainTokenIn.approximateUsdValue'),
                ],
            );
        } catch (\Throwable $e) {
            Log::error('deBridge quote exception', ['corridor' => $corridorKey, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
