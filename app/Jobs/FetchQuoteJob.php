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

    public function __construct(
        public readonly string $providerClass,
        public readonly string $corridorKey,
        public readonly array $corridorConfig,
        public readonly string $sizeKey,
        public readonly float $sizeUsd,
    ) {}

    public function handle(ReferencePriceFetcher $priceFetcher): void
    {
        $provider = app($this->providerClass);

        if (! $provider instanceof QuoteProvider) {
            Log::error('Invalid provider class', ['class' => $this->providerClass]);

            return;
        }

        $quote = $provider->getQuote(
            $this->corridorKey,
            $this->corridorConfig,
            $this->sizeKey,
            $this->sizeUsd,
        );

        if (! $quote) {
            Log::info('No quote returned', [
                'provider' => $provider->name(),
                'corridor' => $this->corridorKey,
                'size' => $this->sizeKey,
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
            'provider' => $quote->provider,
            'corridor' => $quote->corridor,
            'trade_size' => $quote->tradeSize,
            'quoted_output' => $quote->quotedOutput,
            'reference_price' => $referencePrice ?? 0,
            'spread_bps' => $spreadBps ?? 0,
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
        $originPriceUsd = $priceFetcher->fetchOriginPriceUsd($originToken);

        if (! $originPriceUsd || $originPriceUsd <= 0) {
            return 0;
        }

        $originAmount = $this->sizeUsd / $originPriceUsd;

        return $originAmount * $referencePrice;
    }
}
