<?php

namespace App\Services;

use App\Services\Providers\QuoteProvider;
use Illuminate\Support\Facades\Config;

/**
 * Quotes a corridor nobody is polling, on request.
 *
 * The scheduled sweep covers a curated handful of corridors so the charts
 * have history. This covers everything else NEAR can route -- 215 assets
 * across 38 chains -- without adding a single scheduled call, because it
 * only runs when somebody asks for a specific pair.
 */
class LiveQuoteService
{
    /** Native assets carry no contract; each chain family names its own. */
    private const NATIVE = [
        'sol' => '11111111111111111111111111111111',
        'btc' => 'bc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqmql8k8',
        'default' => '0x0000000000000000000000000000000000000000',
    ];

    public function __construct(
        private readonly TokenRegistry $registry,
        private readonly ReferencePriceFetcher $prices,
    ) {}

    /**
     * @return array{corridor: string, from: array<string,mixed>, to: array<string,mixed>, rows: array<int, array<string, mixed>>}|null
     */
    public function quote(string $fromAssetId, string $toAssetId, float $amountUsd): ?array
    {
        $from = $this->registry->find($fromAssetId);
        $to = $this->registry->find($toAssetId);

        if (! $from || ! $to || $from['asset_id'] === $to['asset_id']) {
            return null;
        }

        $key = 'LIVE_'.strtoupper($from['symbol'].'_'.$to['symbol']).'_'.substr(md5($fromAssetId.$toAssetId), 0, 6);

        $corridorConfig = [
            'from' => $from['symbol'],
            'to' => $to['symbol'],
            'label' => $from['symbol'].' on '.$from['chain'].' → '.$to['symbol'].' on '.$to['chain'],
            'origin_asset' => $from['asset_id'],
            'origin_decimals' => $from['decimals'],
            'destination_asset' => $to['asset_id'],
            'destination_decimals' => $to['decimals'],
            // Mirrors the polled corridors exactly, so an on-demand number is
            // comparable with the ones on the charts.
            'deposit_type' => 'ORIGIN_CHAIN',
            'recipient_type' => 'INTENTS',
            'refund_type' => 'ORIGIN_CHAIN',
        ];

        $this->registerComparatorMapping($key, $from, $to);

        $reference = $this->prices->fetchForCorridor($key, $corridorConfig);
        $eligible = $this->registry->comparatorsFor($from['chain'], $to['chain']);

        $rows = [];

        foreach ((array) config('providers') as $slug => $definition) {
            if (! ($definition['enabled'] ?? false) || ! ($definition['class'] ?? null)) {
                continue;
            }

            // A comparator that cannot reach both chains is a coverage gap,
            // not a failure. Say so rather than calling it and logging noise.
            if ($slug !== 'one-click' && ! in_array($slug, $eligible, true)) {
                $rows[] = $this->row($slug, $definition, null, null, null, 'Does not serve this corridor');

                continue;
            }

            /** @var QuoteProvider $provider */
            $provider = app($definition['class']);

            $started = hrtime(true);
            $quote = $provider->getQuote($key, $corridorConfig, 'custom', $amountUsd);
            $latency = (int) ((hrtime(true) - $started) / 1_000_000);

            if (! $quote) {
                $rows[] = $this->row($slug, $definition, null, null, $latency, $provider->failureReason() ?? 'No quote returned');

                continue;
            }

            $rows[] = $this->row(
                $slug,
                $definition,
                (float) $quote->quotedOutput,
                $this->spreadBps($reference, (float) $quote->quotedOutput, $amountUsd, $from['symbol']),
                $latency,
                null,
            );
        }

        usort($rows, fn ($a, $b) => [$a['spread_bps'] === null, $a['spread_bps']] <=> [$b['spread_bps'] === null, $b['spread_bps']]);

        return ['corridor' => $corridorConfig['label'], 'from' => $from, 'to' => $to, 'rows' => $rows];
    }

    /**
     * Teach the comparators this corridor for the life of the request.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     */
    private function registerComparatorMapping(string $key, array $from, array $to): void
    {
        Config::set("comparators.corridors.{$key}", [
            'from' => ['chain' => $from['chain'], 'asset' => $from['symbol'], 'decimals' => $from['decimals']],
            'to' => ['chain' => $to['chain'], 'asset' => $to['symbol'], 'decimals' => $to['decimals']],
        ]);

        foreach ([$from, $to] as $token) {
            Config::set(
                "comparators.tokens.{$token['chain']}.{$token['symbol']}",
                $token['contract'] ?: (self::NATIVE[$token['chain']] ?? self::NATIVE['default'])
            );
        }

        // The comparators index chain ids by their own names for the polled
        // corridors; the registry holds the same ids for every other chain.
        foreach ($this->registry->chains() as $chain => $ids) {
            foreach ($ids as $provider => $id) {
                Config::set("comparators.chain_ids.{$provider}.{$chain}", $id);
            }
        }
    }

    private function spreadBps(?float $reference, float $quoted, float $sizeUsd, string $originSymbol): ?float
    {
        if (! $reference || $reference <= 0 || $quoted <= 0) {
            return null;
        }

        $originUsd = $this->prices->usdPrice($originSymbol);

        if (! $originUsd || $originUsd <= 0) {
            return null;
        }

        $expected = ($sizeUsd / $originUsd) * $reference;

        return $expected > 0 ? (($expected - $quoted) / $expected) * 10000 : null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function row(string $slug, array $definition, ?float $output, ?float $spread, ?int $latency, ?string $error): array
    {
        return [
            'provider' => $slug,
            'name' => $definition['name'] ?? $slug,
            'slot' => $definition['slot'] ?? 1,
            'platform_url' => $definition['platform_url'] ?? null,
            'quoted_output' => $output,
            'spread_bps' => $spread,
            'latency_ms' => $latency,
            'error' => $error,
        ];
    }
}
