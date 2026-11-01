<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReferencePrice extends Model
{
    protected $fillable = [
        'pair',
        'price',
        'source',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLatestForPair(Builder $query, string $pair): void
    {
        $query->where('pair', $pair)->latest('fetched_at');
    }
}
