<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Whether the database currently holds fabricated demo rows.
 *
 * DemoSeeder exists so a clean clone has something to show, but every figure
 * it writes is invented. Presenting those as findings would be the whole
 * problem this project set out to avoid, so each page asks this and says so.
 */
class DemoData
{
    public static function present(): bool
    {
        return Cache::remember('demo-data-present', 60, function () {
            return DB::table('raw_quotes')->whereRaw("extra->>'demo' = 'true'")->exists()
                || DB::table('settlements')->whereRaw("raw_data->>'demo' = 'true'")->exists();
        });
    }

    /** True when the row itself is fabricated, so it must not be linked out. */
    public static function isDemoHash(?string $txHash): bool
    {
        return $txHash !== null && str_starts_with($txHash, 'demo-');
    }
}
