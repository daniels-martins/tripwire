<?php

namespace App\Services\Providers;

use App\DTOs\QuoteResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneClickProvider implements QuoteProvider
{
    public function name(): string
    {
        return 'one-click';
    }

    public function isEnabled(): bool
    {
        return config('providers.one-click.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    public function getQuote(string $corridorKey, array $corridorConfig, string $sizeKey, float $sizeUsd): ?QuoteResult
    {
        try {
            $amount = $this->computeBaseAmount($sizeUsd, $corridorConfig);
            if (! $amount) {
                return null;
            }

            $refundAddress = $this->getPlaceholderAddress($corridorConfig['refund_type'], $corridorConfig['from']);
            $recipientAddress = $this->getPlaceholderAddress($corridorConfig['recipient_type'], $corridorConfig['to']);

            $payload = [
                'dry' => true,
                'swapType' => 'EXACT_INPUT',
                'slippageTolerance' => 100,
                'originAsset' => $corridorConfig['origin_asset'],
                'depositType' => $corridorConfig['deposit_type'],
                'destinationAsset' => $corridorConfig['destination_asset'],
                'amount' => $amount,
                'recipient' => $recipientAddress,
                'recipientType' => $corridorConfig['recipient_type'],
                'refundTo' => $refundAddress,
                'refundType' => $corridorConfig['refund_type'],
                'deadline' => now()->addHour()->toIso8601String(),
            ];

            $headers = [];
            $apiKey = config('providers.one-click.api_key');
            if ($apiKey) {
                $headers['Authorization'] = 'Bearer '.$apiKey;
            }

            $response = Http::baseUrl(config('providers.one-click.base_url'))
                ->withHeaders($headers)
                ->timeout(30)
                ->post('/v0/quote', $payload);

            if (! $response->successful()) {
                Log::warning('1-Click quote failed', [
                    'corridor' => $corridorKey,
                    'size' => $sizeKey,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $amountOut = $data['quote']['amountOut'] ?? null;
            $amountOutFormatted = $data['quote']['amountOutFormatted'] ?? null;

            if (! $amountOut) {
                return null;
            }

            return new QuoteResult(
                provider: $this->name(),
                corridor: $corridorKey,
                tradeSize: $sizeKey,
                quotedOutput: $amountOutFormatted ?? $this->formatAmount($amountOut, $corridorConfig['destination_decimals']),
                quotedOutputFormatted: $amountOutFormatted ?? $amountOut,
                rawResponse: $data,
            );
        } catch (\Throwable $e) {
            Log::error('1-Click quote exception', [
                'corridor' => $corridorKey,
                'size' => $sizeKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    private function computeBaseAmount(float $sizeUsd, array $corridorConfig): ?string
    {
        $symbol = $corridorConfig['binance_symbol'];
        $invert = $corridorConfig['binance_invert'] ?? false;

        try {
            $response = Http::get('https://api.binance.com/api/v3/ticker/price', [
                'symbol' => $symbol,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $price = (float) $response->json('price');
            if ($price <= 0) {
                return null;
            }

            if ($invert) {
                $originSymbol = $corridorConfig['from'].'USDC';
                $priceResponse = Http::get('https://api.binance.com/api/v3/ticker/price', [
                    'symbol' => $originSymbol,
                ]);

                if (! $priceResponse->successful()) {
                    $originSymbol = $corridorConfig['from'].'USDT';
                    $priceResponse = Http::get('https://api.binance.com/api/v3/ticker/price', [
                        'symbol' => $originSymbol,
                    ]);
                }

                if (! $priceResponse->successful()) {
                    return null;
                }

                $originPriceUsd = (float) $priceResponse->json('price');
                $tokenAmount = $sizeUsd / $originPriceUsd;
            } else {
                $originPriceUsd = $price;
                $tokenAmount = $sizeUsd / $originPriceUsd;
            }

            $decimals = (int) $corridorConfig['origin_decimals'];
            $baseAmount = bcmul((string) $tokenAmount, bcpow('10', (string) $decimals, 0), 0);

            return $baseAmount;
        } catch (\Throwable $e) {
            Log::error('Failed to compute base amount', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getPlaceholderAddress(string $type, string $token): string
    {
        if (in_array($type, ['INTENTS', 'CONFIDENTIAL_INTENTS'])) {
            return 'tripwire-scanner.near';
        }

        return match ($token) {
            'BTC' => 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4',
            'ETH', 'USDC', 'USDT' => '0x0000000000000000000000000000000000000001',
            'SOL' => 'BrEiQ58DFkBpoWBkoyq1tj7jMbqS95zmVL1hnR8tnBFR',
            'NEAR' => 'tripwire-scanner.near',
            default => 'tripwire-scanner.near',
        };
    }

    private function formatAmount(string $baseUnits, int $decimals): string
    {
        return bcdiv($baseUnits, bcpow('10', (string) $decimals, 0), $decimals);
    }
}
