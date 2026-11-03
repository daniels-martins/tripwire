<?php

namespace App\Console\Commands;

use App\Services\TokenRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Pulls the asset and chain tables from every provider's own endpoint.
 *
 * None of this is hand-written. A mistyped chain id does not throw -- it
 * quotes a different chain and returns a number that looks fine, which is
 * how a -7,514,685 bps reading got recorded earlier in this project.
 */
class SyncRegistry extends Command
{
    protected $signature = 'registry:sync {--dry : Report what would change without writing}';

    protected $description = 'Refresh the token and chain registry from the providers';

    public function handle(): int
    {
        $tokens = $this->fetchTokens();

        if ($tokens === null) {
            $this->error('Could not read the NEAR token list; registry left as it was.');

            return self::FAILURE;
        }

        $chains = $this->fetchChainMap();

        if ($chains === null) {
            $this->error('Could not build the chain map; registry left as it was.');

            return self::FAILURE;
        }

        if (! $this->assertKnownIds($chains)) {
            return self::FAILURE;
        }

        $this->report($tokens, $chains);

        if ($this->option('dry')) {
            $this->comment('Dry run: nothing written.');

            return self::SUCCESS;
        }

        $dir = storage_path('app/'.TokenRegistry::DIRECTORY);
        File::ensureDirectoryExists($dir);
        File::put($dir.'/tokens.json', (string) json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($dir.'/chains.json', (string) json_encode($chains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Wrote {$dir}/tokens.json and chains.json");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchTokens(): ?array
    {
        $response = Http::timeout(30)->get(config('providers.one-click.base_url').'/v0/tokens');

        if (! $response->successful()) {
            $this->warn('1-Click tokens: HTTP '.$response->status());

            return null;
        }

        $tokens = [];

        foreach ((array) $response->json() as $token) {
            if (! isset($token['assetId'], $token['symbol'], $token['blockchain'], $token['decimals'])) {
                continue;
            }

            $tokens[] = [
                'asset_id' => $token['assetId'],
                'symbol' => $token['symbol'],
                'chain' => $token['blockchain'],
                'decimals' => (int) $token['decimals'],
                'contract' => $token['contractAddress'] ?? null,
            ];
        }

        return $tokens ?: null;
    }

    /**
     * @return array<string, array<string, int>>|null
     */
    private function fetchChainMap(): ?array
    {
        // LiFi lists only EVM chains unless the other types are asked for,
        // which silently dropped Solana the first time this was built.
        $lifi = $this->keyed(
            Http::timeout(30)->get(config('providers.lifi.base_url').'/v1/chains', [
                'chainTypes' => 'EVM,SVM,UTXO,MVM',
            ]),
            'chains', 'key', 'id'
        );

        $relay = $this->keyed(
            Http::timeout(30)->get(config('providers.relay.base_url').'/chains'),
            'chains', 'name', 'id'
        );

        $debridge = $this->keyed(
            Http::timeout(30)->get(config('providers.debridge.base_url').'/v1.0/supported-chains-info'),
            'chains', 'chainName', 'chainId'
        );

        if (! $lifi || ! $relay || ! $debridge) {
            return null;
        }

        $ambiguous = (array) config('chains.ambiguous', []);
        $map = [];

        foreach ((array) config('chains.aliases', []) as $chain => [$l, $r, $d]) {
            if (in_array($chain, $ambiguous, true)) {
                continue;
            }

            $ids = array_filter([
                'lifi' => $lifi[$l] ?? null,
                'relay' => $relay[strtolower($r)] ?? null,
                'debridge' => $debridge[strtolower($d)] ?? null,
            ], fn ($id) => $id !== null);

            if ($ids !== []) {
                $map[$chain] = $ids;
            }
        }

        return $map ?: null;
    }

    /**
     * @return array<string, int>
     */
    private function keyed(Response $response, string $wrapper, string $keyField, string $idField): array
    {
        if (! $response->successful()) {
            $this->warn("Chain list {$wrapper}: HTTP ".$response->status());

            return [];
        }

        $rows = $response->json($wrapper) ?? $response->json() ?? [];
        $out = [];

        foreach ((array) $rows as $row) {
            if (isset($row[$keyField], $row[$idField])) {
                $out[strtolower((string) $row[$keyField])] = (int) $row[$idField];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, int>>  $chains
     */
    private function assertKnownIds(array $chains): bool
    {
        $ok = true;

        foreach ((array) config('chains.known_ids', []) as $chain => $expected) {
            foreach ($expected as $provider => $id) {
                $got = $chains[$chain][$provider] ?? null;

                if ($got !== $id) {
                    $this->error("{$chain}.{$provider} resolved to ".var_export($got, true)." but should be {$id}.");
                    $ok = false;
                }
            }
        }

        if (! $ok) {
            $this->error('Refusing to write a registry that disagrees with a known id.');
        }

        return $ok;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<string, array<string, int>>  $chains
     */
    private function report(array $tokens, array $chains): void
    {
        $chainsWithTokens = count(array_unique(array_column($tokens, 'chain')));
        $contested = count($chains);
        $full = count(array_filter($chains, fn ($ids) => count($ids) === 3));

        $this->table(['', 'count'], [
            ['tokens NEAR serves', count($tokens)],
            ['chains NEAR serves', $chainsWithTokens],
            ['chains with a comparator', $contested],
            ['chains with all three', $full],
            ['NEAR-only chains', $chainsWithTokens - $contested],
        ]);
    }
}
