<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SYNTHETIC data, for layout work only — every row is stamped
 * raw->>'synthetic' = true so it can never be mistaken for a finding.
 * Provider spreads sit in a deliberately tight band in mixed order, so the
 * seeded leaderboard asserts nothing about who actually wins.
 */
class QuoteSeeder extends Seeder
{
    private const PROVIDERS = [
        'across' => 9.0, 'jupiter' => 11.0, 'lifi' => 10.0,
        'near_intents' => 12.0, 'relay' => 10.5, 'thorchain' => 13.0,
    ];

    private const CORRIDORS = [
        'BTC->USDC.SOL' => ['across', 'lifi', 'near_intents', 'relay', 'thorchain'],
        'ETH->USDC.ARB' => ['across', 'jupiter', 'lifi', 'near_intents', 'relay'],
        'SOL->USDC.ETH' => ['jupiter', 'lifi', 'near_intents', 'relay'],
        'USDT.TRX->USDC.SOL' => ['near_intents', 'thorchain'],
    ];

    private const SIZES = [100, 10_000, 100_000];

    public function run(): void
    {
        mt_srand(20261101);
        $rows = [];
        $now = Carbon::now();

        for ($batch = 47; $batch >= 0; $batch--) {
            $batchId = (string) Str::uuid();
            $polledAt = $now->copy()->subMinutes($batch * 5);

            foreach (self::CORRIDORS as $corridor => $providers) {
                foreach (self::SIZES as $size) {
                    foreach ($providers as $provider) {
                        $sizeMult = match ($size) {
                            100 => 1.0, 10_000 => 1.15, default => 1.9,
                        };
                        $noise = 1 + (mt_rand(-35, 35) / 100);
                        $spread = round(self::PROVIDERS[$provider] * $sizeMult * $noise, 4);
                        $failed = mt_rand(1, 100) <= 3;

                        $rows[] = [
                            'batch_id' => $batchId,
                            'polled_at' => $polledAt,
                            'provider' => $provider,
                            'corridor' => $corridor,
                            'size_usd' => $size,
                            'amount_in' => $size,
                            'amount_out' => $failed ? null : round($size * (1 - $spread / 10_000), 8),
                            'reference_price' => 1,
                            'spread_bps' => $failed ? null : $spread,
                            'latency_ms' => mt_rand(180, 2400),
                            'ok' => ! $failed,
                            'error' => $failed ? 'no route' : null,
                            'raw' => json_encode(['synthetic' => true]),
                            'created_at' => $polledAt,
                            'updated_at' => $polledAt,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('quotes')->insert($chunk);
        }

        DB::statement('REFRESH MATERIALIZED VIEW v_leaderboard');

        $this->command->info(count($rows).' synthetic quotes seeded; v_leaderboard refreshed.');
    }
}
