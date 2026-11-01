<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RawQuote extends Model
{
    protected $fillable = [
        'batch_id',
        'provider',
        'corridor',
        'trade_size',
        'size_usd',
        'quoted_output',
        'reference_price',
        'spread_bps',
        'latency_ms',
        'ok',
        'error',
        'raw_response',
        'quoted_at',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'quoted_output' => 'decimal:8',
            'reference_price' => 'decimal:8',
            'spread_bps' => 'decimal:4',
            'latency_ms' => 'integer',
            'ok' => 'boolean',
            'raw_response' => 'array',
            'quoted_at' => 'datetime',
            'extra' => 'array',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForCorridor(Builder $query, string $corridor): void
    {
        $query->where('corridor', $corridor);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForSize(Builder $query, string $size): void
    {
        $query->where('trade_size', $size);
    }
}
