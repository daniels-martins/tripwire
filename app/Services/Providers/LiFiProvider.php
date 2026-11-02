<?php

namespace App\Services\Providers;

use App\DTOs\QuoteResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LiFi is itself a cross-chain aggregator, routing through whichever bridge is
 * cheapest, which makes it the closest like-for-like comparator to NEAR Intents.
 */
class LiFiProvider extends AbstractComparatorProvider
{
    public function name(): string
    {
        return 'lifi';
    }

    protected function chainKey(): string
    {
        return 'lifi';
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

            $response = Http::timeout(30)->get(config('providers.lifi.base_url').'/v1/quote', [
                'fromChain' => $map['from_chain'],
                'toChain' => $map['to_chain'],
                'fromToken' => $map['from_token'],
                'toToken' => $map['to_token'],
                'fromAmount' => $amount,
                'fromAddress' => $map['from_address'],
                'toAddress' => $map['to_address'],
            ]);

            if (! $response->successful()) {
                return $this->fail('Upstream HTTP '.$response->status());
            }

            $toAmount = $response->json('estimate.toAmount');

            if (! $toAmount) {
                return $this->fail('No amount in response');
            }

            $formatted = $this->fromBaseUnits((string) $toAmount, $map['to_decimals']);

            return new QuoteResult(
                provider: $this->name(),
                corridor: $corridorKey,
                tradeSize: $sizeKey,
                quotedOutput: $formatted,
                quotedOutputFormatted: $formatted,
                rawResponse: [
                    'tool' => $response->json('tool'),
                    'toAmount' => $toAmount,
                    'toAmountUSD' => $response->json('estimate.toAmountUSD'),
                    'fromAmountUSD' => $response->json('estimate.fromAmountUSD'),
                ],
            );
        } catch (\Throwable $e) {
            Log::error('LiFi quote exception', ['corridor' => $corridorKey, 'error' => $e->getMessage()]);

            return $this->fail('Request failed ('.class_basename($e).')');
        }
    }
}
