<?php

namespace App\Services\Providers;

use App\DTOs\QuoteResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneClickProvider implements QuoteProvider
{
    use ReportsFailureReason;

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
                return $this->fail('Reference price unavailable');
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

                return $this->fail('Upstream HTTP '.$response->status());
            }

            $data = $response->json();
            $amountOut = $data['quote']['amountOut'] ?? null;
            $amountOutFormatted = $data['quote']['amountOutFormatted'] ?? null;

            if (! $amountOut) {
                return $this->fail('No amount in response');
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

            return $this->fail('Request failed ('.class_basename($e).')');
        }
    }

    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    private function computeBaseAmount(float $sizeUsd, array $corridorConfig): ?string
    {
        $from = $corridorConfig['from'];

        try {
            $originPriceUsd = $this->fetchOriginPriceUsd($from);

            if (! $originPriceUsd || $originPriceUsd <= 0) {
                return null;
            }

            $tokenAmount = $sizeUsd / $originPriceUsd;
            $decimals = (int) $corridorConfig['origin_decimals'];
            $baseAmount = bcmul((string) $tokenAmount, bcpow('10', (string) $decimals, 0), 0);

            return $baseAmount;
        } catch (\Throwable $e) {
            Log::error('Failed to compute base amount', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function fetchOriginPriceUsd(string $token): ?float
    {
        if (in_array($token, ['USDC', 'USDT'])) {
            return 1.0;
        }

        foreach ([$token.'USDC', $token.'USDT'] as $symbol) {
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
