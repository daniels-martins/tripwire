<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Shared plumbing for the non-NEAR routes: corridor lookup, sizing a trade in
 * USD, and converting base units. Each subclass only has to speak its own API.
 */
abstract class AbstractComparatorProvider implements QuoteProvider
{
    use ReportsFailureReason;

    /** Key under comparators.chain_ids for this provider. */
    abstract protected function chainKey(): string;

    public function isEnabled(): bool
    {
        return (bool) config('providers.'.$this->name().'.enabled', false);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapping(string $corridorKey): ?array
    {
        $map = config("comparators.corridors.{$corridorKey}");

        if (! $map) {
            return $this->fail('Corridor not configured');
        }

        $ids = config('comparators.chain_ids.'.$this->chainKey(), []);
        $fromChain = $ids[$map['from']['chain']] ?? null;
        $toChain = $ids[$map['to']['chain']] ?? null;

        // A chain this provider does not number is a chain it does not serve.
        if ($fromChain === null || $toChain === null) {
            return $this->fail('Chain not served by this provider');
        }

        $fromToken = config("comparators.tokens.{$map['from']['chain']}.{$map['from']['asset']}");
        $toToken = config("comparators.tokens.{$map['to']['chain']}.{$map['to']['asset']}");

        if (! $fromToken || ! $toToken) {
            return $this->fail('Token not configured for this chain');
        }

        return [
            'from_chain' => $fromChain,
            'to_chain' => $toChain,
            'from_token' => $fromToken,
            'to_token' => $toToken,
            'from_decimals' => (int) $map['from']['decimals'],
            'to_decimals' => (int) $map['to']['decimals'],
            'from_address' => $this->placeholder($map['from']['chain']),
            'to_address' => $this->placeholder($map['to']['chain']),
        ];
    }

    protected function placeholder(string $chain): string
    {
        return config('comparators.placeholder_address.'.(
            in_array($chain, ['solana', 'bitcoin'], true) ? $chain : 'evm'
        ));
    }

    protected function baseUnits(float $sizeUsd, string $fromAsset, int $decimals): ?string
    {
        $priceUsd = $this->originPriceUsd($fromAsset);

        if (! $priceUsd || $priceUsd <= 0) {
            return $this->fail('Reference price unavailable');
        }

        return bcmul(
            number_format($sizeUsd / $priceUsd, 18, '.', ''),
            bcpow('10', (string) $decimals, 0),
            0
        );
    }

    protected function fromBaseUnits(string $amount, int $decimals): string
    {
        $value = rtrim(rtrim(bcdiv($amount, bcpow('10', (string) $decimals, 0), $decimals), '0'), '.');

        return $value === '' ? '0' : $value;
    }

    /**
     * The same reference venue the rest of the scanner uses, so "$1,000"
     * means the same trade for every provider. Cached so one polling sweep
     * does not hit the ticker once per corridor per size per provider.
     */
    protected function originPriceUsd(string $asset): ?float
    {
        if (in_array($asset, ['USDC', 'USDT'], true)) {
            return 1.0;
        }

        return Cache::remember("origin-price:{$asset}", 60, function () use ($asset) {
            foreach ([$asset.'USDC', $asset.'USDT'] as $symbol) {
                try {
                    $response = Http::timeout(10)
                        ->get(config('reference.base_url').'/api/v3/ticker/price', ['symbol' => $symbol]);

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
