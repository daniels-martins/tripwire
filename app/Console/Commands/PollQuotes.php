<?php

namespace App\Console\Commands;

use App\Jobs\FetchQuoteJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PollQuotes extends Command
{
    protected $signature = 'quotes:poll';

    protected $description = 'Poll all bridge providers for quotes across all corridors and trade sizes';

    public function handle(): int
    {
        $corridors = config('corridors.pairs');
        $sizes = config('corridors.trade_sizes');
        $providers = config('providers');
        $batchId = (string) Str::uuid();

        $dispatched = 0;

        foreach ($providers as $providerKey => $providerConfig) {
            if (! ($providerConfig['enabled'] ?? false)) {
                continue;
            }

            $providerClass = $providerConfig['class'];

            foreach ($corridors as $corridorKey => $corridorConfig) {
                foreach ($sizes as $sizeKey => $sizeUsd) {
                    FetchQuoteJob::dispatch(
                        providerClass: $providerClass,
                        corridorKey: $corridorKey,
                        corridorConfig: $corridorConfig,
                        sizeKey: $sizeKey,
                        sizeUsd: (float) $sizeUsd,
                        batchId: $batchId,
                    );
                    $dispatched++;
                }
            }
        }

        $this->info("Dispatched {$dispatched} quote jobs (batch {$batchId}).");

        return self::SUCCESS;
    }
}
