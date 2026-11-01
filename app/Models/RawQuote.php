<?php

namespace App\Models;

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

    public function scopeForCorridor($query, string $corridor): void
    {
        $query->where('corridor', $corridor);
    }

    public function scopeForProvider($query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    public function scopeForSize($query, string $size): void
    {
        $query->where('trade_size', $size);
    }
}
