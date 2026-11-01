<?php

namespace App\Services;

use App\Models\Settlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SettlementIngester
{
    /** @var array<string, list<string>> Token contract → possible corridors */
    private array $tokenCorridors;

    public function __construct()
    {
        $this->buildTokenMap();
    }

    /**
     * Fetch one page of ft_on_transfer transactions from NearBlocks and store settlements.
     *
     * @return array{stored: int, skipped: int, cursor: ?string, oldest_timestamp: ?string}
     */
    public function ingest(int $perPage = 25, ?string $cursor = null): array
    {
        $query = [
            'method' => 'ft_on_transfer',
            'per_page' => $perPage,
            'order' => 'desc',
        ];

        if ($cursor) {
            $query['cursor'] = $cursor;
        }

        $response = Http::baseUrl('https://api.nearblocks.io')
            ->timeout(30)
            ->get('/v1/account/intents.near/txns', $query);

        if (! $response->successful()) {
            Log::warning('NearBlocks API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['stored' => 0, 'skipped' => 0, 'cursor' => null, 'oldest_timestamp' => null];
        }

        $data = $response->json();
        $txns = $data['txns'] ?? [];
        $nextCursor = $data['cursor'] ?? null;

        $stored = 0;
        $skipped = 0;
        $oldestTimestamp = null;

        foreach ($txns as $txn) {
            $ts = $txn['block_timestamp'] ?? null;
            if ($ts) {
                $oldestTimestamp = $ts;
            }

            if ($this->processTransaction($txn)) {
                $stored++;
            } else {
                $skipped++;
            }
        }

        $oldestParsed = $oldestTimestamp
            ? Carbon::createFromTimestampMs((int) ($oldestTimestamp / 1_000_000))->toDateTimeString()
            : null;

        return ['stored' => $stored, 'skipped' => $skipped, 'cursor' => $nextCursor, 'oldest_timestamp' => $oldestParsed];
    }

    /**
     * Paginate through historical data using NearBlocks with cached cursor.
     */
    public function backfill(int $months = 3, int $perPage = 25): int
    {
        $cutoff = now()->subMonths($months);
        $cursor = Cache::get('settlements:backfill_cursor');
        $totalStored = 0;
        $pages = 0;
        $maxPages = 2000;
        $emptyApiPages = 0;

        do {
            $result = $this->ingest($perPage, $cursor);
            $totalStored += $result['stored'];
            $cursor = $result['cursor'];
            $pages++;

            if ($cursor) {
                Cache::put('settlements:backfill_cursor', $cursor, now()->addDay());
            }

            if ($result['stored'] === 0 && $result['skipped'] === 0) {
                $emptyApiPages++;
                if ($emptyApiPages >= 5) {
                    break;
                }
            } else {
                $emptyApiPages = 0;
            }

            if ($result['oldest_timestamp'] && Carbon::parse($result['oldest_timestamp'])->lt($cutoff)) {
                Cache::forget('settlements:backfill_cursor');

                break;
            }

            usleep(500_000);
        } while ($cursor && $pages < $maxPages);

        return $totalStored;
    }

    /**
     * Backfill using FastNEAR for tx hash discovery + NearBlocks for tx details.
     */
    public function backfillViaFastNear(int $months = 3, int $limit = 200): int
    {
        $cutoff = now()->subMonths($months);
        $resumeToken = Cache::get('settlements:fastnear_resume');
        $totalStored = 0;
        $fetched = 0;

        while ($fetched < $limit) {
            $batchSize = min(200, $limit - $fetched);
            $payload = [
                'account_id' => 'intents.near',
                'desc' => true,
                'is_receiver' => true,
                'is_success' => true,
                'limit' => $batchSize,
            ];

            if ($resumeToken) {
                $payload['resume_token'] = $resumeToken;
            }

            $response = Http::timeout(30)
                ->post('https://tx.main.fastnear.com/v0/account', $payload);

            if (! $response->successful()) {
                Log::warning('FastNEAR API failed', ['status' => $response->status()]);

                break;
            }

            $data = $response->json();
            $txs = $data['account_txs'] ?? [];
            $resumeToken = $data['resume_token'] ?? null;

            if (empty($txs)) {
                break;
            }

            if ($resumeToken) {
                Cache::put('settlements:fastnear_resume', $resumeToken, now()->addDay());
            }

            $oldestTimestamp = null;
            foreach ($txs as $tx) {
                $txHash = $tx['transaction_hash'];
                $oldestTimestamp = $tx['tx_block_timestamp'] ?? null;

                if (Settlement::where('tx_hash', $txHash)->exists()) {
                    continue;
                }

                $stored = $this->lookupAndStore($txHash, $tx['tx_block_height'] ?? 0);
                if ($stored) {
                    $totalStored++;
                }

                usleep(200_000);
            }

            $fetched += count($txs);

            if ($oldestTimestamp) {
                $ts = Carbon::createFromTimestampMs((int) ($oldestTimestamp / 1_000_000));
                if ($ts->lt($cutoff)) {
                    Cache::forget('settlements:fastnear_resume');

                    break;
                }
            }
        }

        return $totalStored;
    }

    /**
     * Look up a single transaction via NearBlocks and store if it matches our corridors.
     */
    private function lookupAndStore(string $txHash, int $blockHeight): bool
    {
        $response = Http::baseUrl('https://api.nearblocks.io')
            ->timeout(15)
            ->get("/v1/txns/{$txHash}");

        if (! $response->successful()) {
            return false;
        }

        $data = $response->json();
        $txns = $data['txns'] ?? [];

        foreach ($txns as $txn) {
            $method = null;
            $actions = $txn['actions'] ?? [];
            foreach ($actions as $action) {
                if (($action['method'] ?? null) === 'ft_on_transfer') {
                    $method = 'ft_on_transfer';

                    break;
                }
            }

            if ($method !== 'ft_on_transfer') {
                continue;
            }

            if (($txn['outcomes']['status'] ?? false) !== true) {
                continue;
            }

            $txn['transaction_hash'] = $txHash;

            return $this->processTransaction($txn);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $txn
     */
    private function processTransaction(array $txn): bool
    {
        $txHash = $txn['transaction_hash'] ?? null;
        if (! $txHash) {
            return false;
        }

        if (Settlement::where('tx_hash', $txHash)->exists()) {
            return false;
        }

        if (($txn['outcomes']['status'] ?? false) !== true) {
            return false;
        }

        $tokenContract = $txn['predecessor_account_id'] ?? null;
        if (! $tokenContract) {
            return false;
        }

        $args = $this->parseArgs($txn);
        if (! $args || ! isset($args['amount'])) {
            return false;
        }

        $msg = $args['msg'] ?? null;
        $corridor = $this->mapTokenToCorridor($tokenContract, $msg);
        if (! $corridor) {
            return false;
        }

        $corridorConfig = config("corridors.pairs.{$corridor}");
        $depositAmount = $this->normalizeAmount($args['amount'], $corridorConfig['origin_decimals']);

        $blockHeight = $txn['receipt_block']['block_height']
            ?? $txn['block']['block_height']
            ?? 0;

        $timestamp = $txn['block_timestamp']
            ?? $txn['receipt_block']['block_timestamp']
            ?? null;

        $settledAt = $timestamp
            ? Carbon::createFromTimestampMs((int) ($timestamp / 1_000_000))
            : now();

        Settlement::create([
            'tx_hash' => $txHash,
            'provider' => 'one-click',
            'corridor' => $corridor,
            'quoted_amount' => $depositAmount,
            'delivered_amount' => null,
            'slippage_bps' => null,
            'block_height' => $blockHeight,
            'settled_at' => $settledAt,
            'raw_data' => $txn,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $txn
     * @return array<string, mixed>|null
     */
    private function parseArgs(array $txn): ?array
    {
        $actions = $txn['actions'] ?? [];
        foreach ($actions as $action) {
            if (($action['method'] ?? null) === 'ft_on_transfer' && isset($action['args'])) {
                $args = $action['args'];
                if (is_string($args)) {
                    return json_decode($args, true);
                }

                return is_array($args) ? $args : null;
            }
        }

        return null;
    }

    /**
     * Map a NEP-141 token contract to a corridor key.
     */
    public function mapTokenToCorridor(string $tokenContract, ?string $msg): ?string
    {
        $candidates = $this->tokenCorridors[$tokenContract] ?? [];

        if (count($candidates) === 0) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if ($msg) {
            $msgData = json_decode($msg, true);
            if (is_array($msgData)) {
                $destAsset = $msgData['destination_asset'] ?? $msgData['destinationAsset'] ?? null;
                if ($destAsset) {
                    $destContract = str_replace('nep141:', '', $destAsset);
                    foreach ($candidates as $corridor) {
                        $config = config("corridors.pairs.{$corridor}");
                        $configDest = str_replace('nep141:', '', $config['destination_asset']);
                        if ($configDest === $destContract) {
                            return $corridor;
                        }
                    }
                }
            }
        }

        return $candidates[0];
    }

    public function normalizeAmount(string $baseUnits, int $decimals): string
    {
        if ($decimals === 0) {
            return $baseUnits;
        }

        return bcdiv($baseUnits, bcpow('10', (string) $decimals, 0), $decimals);
    }

    private function buildTokenMap(): void
    {
        $this->tokenCorridors = [];

        foreach (config('corridors.pairs', []) as $corridorKey => $config) {
            $originContract = str_replace('nep141:', '', $config['origin_asset']);

            if (! isset($this->tokenCorridors[$originContract])) {
                $this->tokenCorridors[$originContract] = [];
            }

            $this->tokenCorridors[$originContract][] = $corridorKey;
        }
    }
}
