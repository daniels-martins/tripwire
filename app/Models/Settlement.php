<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $fillable = [
        'tx_hash',
        'provider',
        'corridor',
        'quoted_amount',
        'delivered_amount',
        'slippage_bps',
        'block_height',
        'settled_at',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'quoted_amount' => 'decimal:8',
            'delivered_amount' => 'decimal:8',
            'slippage_bps' => 'decimal:4',
            'block_height' => 'integer',
            'settled_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function scopeForCorridor($query, string $corridor): void
    {
        $query->where('corridor', $corridor);
    }
}
