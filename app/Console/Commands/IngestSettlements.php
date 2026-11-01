<?php

namespace App\Console\Commands;

use App\Services\SettlementIngester;
use Illuminate\Console\Command;

class IngestSettlements extends Command
{
    protected $signature = 'settlements:ingest
        {--backfill : Pull historical data via NearBlocks pagination}
        {--deep : Pull historical data via FastNEAR bulk discovery}
        {--months=3 : Number of months to backfill}
        {--limit=100 : Max records per run}';

    protected $description = 'Ingest bridge settlement data from intents.near via NearBlocks';

    public function handle(SettlementIngester $ingester): int
    {
        if ($this->option('deep')) {
            return $this->runDeepBackfill($ingester);
        }

        if ($this->option('backfill')) {
            return $this->runBackfill($ingester);
        }

        return $this->runIncremental($ingester);
    }

    private function runDeepBackfill(SettlementIngester $ingester): int
    {
        $months = (int) $this->option('months');
        $limit = (int) $this->option('limit');
        $this->info("Deep backfilling {$months} months via FastNEAR (limit: {$limit} txs)...");

        $stored = $ingester->backfillViaFastNear($months, $limit);

        $this->info("Deep backfill complete. Stored {$stored} settlements.");

        return self::SUCCESS;
    }

    private function runBackfill(SettlementIngester $ingester): int
    {
        $months = (int) $this->option('months');
        $this->info("Backfilling {$months} months of settlement data from intents.near...");

        $stored = $ingester->backfill($months);

        $this->info("Backfill complete. Stored {$stored} settlements.");

        return self::SUCCESS;
    }

    private function runIncremental(SettlementIngester $ingester): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Ingesting up to {$limit} recent settlements...");

        $totalStored = 0;
        $totalSkipped = 0;
        $cursor = null;
        $remaining = $limit;

        while ($remaining > 0) {
            $perPage = min($remaining, 25);
            $result = $ingester->ingest($perPage, $cursor);

            $totalStored += $result['stored'];
            $totalSkipped += $result['skipped'];
            $remaining -= $perPage;
            $cursor = $result['cursor'];

            if (! $cursor || ($result['stored'] === 0 && $result['skipped'] === 0)) {
                break;
            }

            usleep(500_000);
        }

        $this->info("Done. Stored: {$totalStored}, Skipped: {$totalSkipped}");

        return self::SUCCESS;
    }
}
