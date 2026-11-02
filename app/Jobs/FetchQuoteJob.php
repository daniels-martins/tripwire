<?php

namespace App\Jobs;

use App\Models\RawQuote;
use App\Services\Providers\QuoteProvider;
use App\Services\ReferencePriceFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    public function __construct(
        public readonly string $providerClass,
        public readonly string $corridorKey,
        public readonly array $corridorConfig,
        public readonly string $sizeKey,
        public readonly float $sizeUsd,
        public readonly string $batchId,
    ) {}

    public function handle(ReferencePriceFetcher $priceFetcher): void
    {
        $provider = app($this->providerClass);

        if (! $provider instanceof QuoteProvider) {
            Log::error('Invalid provider class', ['class' => $this->providerClass]);

            return;
        }

        $start = hrtime(true);

        $quote = $provider->getQuote(
            $this->corridorKey,
            $this->corridorConfig,
            $this->sizeKey,
            $this->sizeUsd,
        );

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        if (! $quote) {
            RawQuote::create([
                'batch_id' => $this->batchId,
                'provider' => $provider->name(),
                'corridor' => $this->corridorKey,
                'trade_size' => $this->sizeKey,
                'size_usd' => (int) $this->sizeUsd,
                'quoted_output' => 0,
                'reference_price' => 0,
                'spread_bps' => 0,
                'latency_ms' => $latencyMs,
                'ok' => false,
                'error' => 'No quote returned',
                'quoted_at' => now(),
            ]);

            return;
        }

        $referencePrice = $priceFetcher->fetchForCorridor($this->corridorKey, $this->corridorConfig);

        $spreadBps = null;
        if ($referencePrice && $referencePrice > 0 && (float) $quote->quotedOutput > 0) {
            $quotedOutput = (float) $quote->quotedOutput;
            $expectedOutput = $this->computeExpectedOutput($referencePrice, $priceFetcher);
            if ($expectedOutput > 0) {
                $spreadBps = (($expectedOutput - $quotedOutput) / $expectedOutput) * 10000;
            }
        }

        RawQuote::create([
            'batch_id' => $this->batchId,
            'provider' => $quote->provider,
            'corridor' => $quote->corridor,
            'trade_size' => $quote->tradeSize,
            'size_usd' => (int) $this->sizeUsd,
            'quoted_output' => $quote->quotedOutput,
            'reference_price' => $referencePrice ?? 0,
            'spread_bps' => $spreadBps ?? 0,
            'latency_ms' => $latencyMs,
            'ok' => true,
            'raw_response' => $quote->rawResponse,
            'quoted_at' => now(),
        ]);
    }

    private function computeExpectedOutput(float $referencePrice, ReferencePriceFetcher $priceFetcher): float
    {
        $destToken = $this->corridorConfig['to'] ?? '';
        if (in_array($destToken, ['USDC', 'USDT'])) {
            return $this->sizeUsd;
        }

        $originToken = $this->corridorConfig['from'] ?? '';
        $originPriceUsd = $priceFetcher->usdPrice($originToken);

        if (! $originPriceUsd || $originPriceUsd <= 0) {
            return 0;
        }

        $originAmount = $this->sizeUsd / $originPriceUsd;

        return $originAmount * $referencePrice;
    }
}
