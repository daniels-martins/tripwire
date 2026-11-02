<?php

namespace App\Services\Providers;

use App\DTOs\QuoteResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Relay quotes cross-chain swaps through its own relayer network. It returns a
 * ready-formatted output amount, so no base-unit conversion is needed.
 */
class RelayProvider extends AbstractComparatorProvider
{
    public function name(): string
    {
        return 'relay';
    }

    protected function chainKey(): string
    {
        return 'relay';
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
                ->post(config('providers.relay.base_url').'/quote', [
                    'user' => $map['from_address'],
                    'recipient' => $map['to_address'],
                    'originChainId' => $map['from_chain'],
                    'destinationChainId' => $map['to_chain'],
                    'originCurrency' => $map['from_token'],
                    'destinationCurrency' => $map['to_token'],
                    'amount' => $amount,
                    'tradeType' => 'EXACT_INPUT',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $formatted = $response->json('details.currencyOut.amountFormatted');

            if (! $formatted || (float) $formatted <= 0) {
                return null;
            }

            return new QuoteResult(
                provider: $this->name(),
                corridor: $corridorKey,
                tradeSize: $sizeKey,
                quotedOutput: (string) $formatted,
                quotedOutputFormatted: (string) $formatted,
                rawResponse: [
                    'amount' => $response->json('details.currencyOut.amount'),
                    'amountUsd' => $response->json('details.currencyOut.amountUsd'),
                    'inUsd' => $response->json('details.currencyIn.amountUsd'),
                    'rate' => $response->json('details.rate'),
                ],
            );
        } catch (\Throwable $e) {
            Log::error('Relay quote exception', ['corridor' => $corridorKey, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
