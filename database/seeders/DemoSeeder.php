<?php

namespace Database\Seeders;

use App\Models\RawQuote;
use App\Models\ReferencePrice;
use App\Models\Settlement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /** @var array<string, float> Approximate USD prices for origin tokens */
    private const BASE_PRICES_USD = [
        'BTC' => 97000.0,
        'ETH' => 3200.0,
        'NEAR' => 5.50,
        'SOL' => 180.0,
        'USDC' => 1.0,
    ];

    /** @var array<string, float> Cross-rate reference prices per corridor */
    private const REFERENCE_PRICES = [
        'BTC_USDC' => 97000.0,
        'ETH_USDC' => 3200.0,
        'NEAR_USDC' => 5.50,
        'SOL_USDC' => 180.0,
        'BTC_ETH' => 30.3125,
        'USDC_NEAR' => 0.18182,
        'ETH_BTC' => 0.03299,
        'SOL_ETH' => 0.05625,
    ];

    /** @var array<string, list<int>> Realistic spread ranges per corridor [min_bps, max_bps] */
    private const SPREAD_RANGES = [
        'BTC_USDC' => [12, 35],
        'ETH_USDC' => [10, 30],
        'NEAR_USDC' => [15, 28],
        'SOL_USDC' => [14, 32],
        'BTC_ETH' => [18, 38],
        'USDC_NEAR' => [20, 40],
        'ETH_BTC' => [16, 35],
        'SOL_ETH' => [20, 42],
    ];

    private const TRADE_SIZES = [
        'small' => 100,
        'medium' => 1000,
        'large' => 10000,
    ];

    public function run(): void
    {
        $this->command->info('Seeding demo data...');

        $this->seedReferencePrices();
        $this->seedRawQuotes();
        $this->seedSettlements();
        $this->refreshViews();
        $this->generateMemo();

        $this->command->info('Demo data seeded successfully.');
    }

    private function seedReferencePrices(): void
    {
        $start = now()->subDays(7);

        foreach (self::REFERENCE_PRICES as $corridor => $basePrice) {
            for ($i = 0; $i < 504; $i++) {
                $drift = $basePrice * $this->randomFloat(-0.002, 0.002);
                $price = $basePrice + $drift;

                ReferencePrice::create([
                    'pair' => $corridor,
                    'price' => number_format($price, 8, '.', ''),
                    'source' => 'binance',
                    'fetched_at' => $start->copy()->addMinutes($i * 20),
                ]);
            }
        }

        $this->command->info('  Reference prices: '.ReferencePrice::count().' rows');
    }

    private function seedRawQuotes(): void
    {
        $start = now()->subDays(7);
        $corridors = array_keys(self::REFERENCE_PRICES);
        $batchCount = 0;

        for ($i = 0; $i < 504; $i++) {
            $batchId = (string) Str::uuid();
            $batchTime = $start->copy()->addMinutes($i * 20);
            $batchCount++;

            foreach ($corridors as $corridor) {
                $basePrice = self::REFERENCE_PRICES[$corridor];
                $spreadRange = self::SPREAD_RANGES[$corridor];

                foreach (self::TRADE_SIZES as $sizeKey => $sizeUsd) {
                    $spreadBps = $this->randomFloat($spreadRange[0], $spreadRange[1]);
                    $sizeMultiplier = match ($sizeKey) {
                        'small' => 1.05,
                        'large' => 0.97,
                        default => 1.0,
                    };
                    $spreadBps *= $sizeMultiplier;

                    $priceDrift = $basePrice * $this->randomFloat(-0.001, 0.001);
                    $refPrice = $basePrice + $priceDrift;

                    $originPriceUsd = self::BASE_PRICES_USD[explode('_', $corridor)[0]];
                    $originAmount = $sizeUsd / $originPriceUsd;
                    $expectedOutput = $originAmount * $refPrice;
                    $quotedOutput = $expectedOutput * (1 - $spreadBps / 10000);

                    $ok = $this->randomFloat(0, 1) > 0.02;

                    RawQuote::create([
                        'batch_id' => $batchId,
                        'provider' => 'one-click',
                        'corridor' => $corridor,
                        'trade_size' => $sizeKey,
                        'size_usd' => $sizeUsd,
                        'quoted_output' => $ok ? number_format($quotedOutput, 8, '.', '') : 0,
                        'reference_price' => number_format($refPrice, 8, '.', ''),
                        'spread_bps' => $ok ? number_format($spreadBps, 4, '.', '') : 0,
                        'latency_ms' => rand(80, 450),
                        'ok' => $ok,
                        'error' => $ok ? null : 'Simulated timeout',
                        'quoted_at' => $batchTime,
                        // Marks the row as fabricated. Every page checks for this
                        // and says so; nothing here was measured.
                        'extra' => ['demo' => true],
                    ]);
                }
            }
        }

        $this->command->info('  Raw quotes: '.RawQuote::count()." rows ({$batchCount} batches)");
    }

    private function seedSettlements(): void
    {
        $start = now()->subDays(7);
        $settlementCorridors = ['BTC_USDC', 'ETH_USDC', 'NEAR_USDC', 'SOL_USDC'];
        $blockHeight = 222_500_000;

        foreach ($settlementCorridors as $corridor) {
            $count = rand(25, 45);
            $originToken = explode('_', $corridor)[0];
            $originPriceUsd = self::BASE_PRICES_USD[$originToken];

            for ($i = 0; $i < $count; $i++) {
                $settledAt = $start->copy()->addMinutes(rand(0, 10080));
                $depositUsd = $this->randomFloat(50, 15000);
                $depositAmount = $depositUsd / $originPriceUsd;

                Settlement::create([
                    'tx_hash' => 'demo-'.Str::random(39),
                    'provider' => 'one-click',
                    'corridor' => $corridor,
                    'quoted_amount' => number_format($depositAmount, 8, '.', ''),
                    'delivered_amount' => null,
                    'slippage_bps' => null,
                    'block_height' => $blockHeight + rand(0, 50000),
                    'settled_at' => $settledAt,
                    'raw_data' => ['demo' => true, 'seeded' => true],
                ]);
            }
        }

        $this->command->info('  Settlements: '.Settlement::count().' rows');
    }

    private function refreshViews(): void
    {
        $this->command->call('views:refresh');
    }

    private function generateMemo(): void
    {
        $this->command->call('memo:generate');
    }

    private function randomFloat(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
