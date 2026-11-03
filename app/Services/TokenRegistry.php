<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Everything NEAR can route, and which comparators can follow it there.
 *
 * The scanner polls a small curated set on a schedule; this registry is the
 * much larger set a person can ask about. Listing only the corridors with a
 * comparator would understate NEAR's reach, which is the opposite of what
 * the data says -- so every corridor is offered, and the ones nobody else
 * serves are simply quoted by NEAR alone.
 */
class TokenRegistry
{
    public const DIRECTORY = 'registry';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tokens(): array
    {
        return Cache::remember('registry:tokens', 300, fn () => $this->read('tokens.json'));
    }

    /**
     * Comparator chain ids, keyed by NEAR's chain code.
     *
     * @return array<string, array<string, int>>
     */
    public function chains(): array
    {
        return Cache::remember('registry:chains', 300, fn () => $this->read('chains.json'));
    }

    /**
     * Tokens grouped by chain, for a pair of select boxes.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function byChain(): array
    {
        $grouped = [];

        foreach ($this->tokens() as $token) {
            $grouped[$token['chain']][] = $token;
        }

        foreach ($grouped as &$list) {
            usort($list, fn ($a, $b) => strcmp($a['symbol'], $b['symbol']));
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $assetId): ?array
    {
        foreach ($this->tokens() as $token) {
            if ($token['asset_id'] === $assetId) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Which comparators can quote a corridor between these two chains.
     *
     * @return array<int, string>
     */
    public function comparatorsFor(string $fromChain, string $toChain): array
    {
        $chains = $this->chains();
        $from = array_keys($chains[$fromChain] ?? []);
        $to = array_keys($chains[$toChain] ?? []);

        // A comparator has to reach BOTH ends to quote the corridor at all.
        return array_values(array_intersect($from, $to));
    }

    public function isEmpty(): bool
    {
        return $this->tokens() === [];
    }

    /**
     * @return array<mixed>
     */
    private function read(string $file): array
    {
        $path = storage_path('app/'.self::DIRECTORY.'/'.$file);

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
