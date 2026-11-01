<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tripwire extends Model
{
    protected $fillable = [
        'name',
        'description',
        'threshold_value',
        'current_value',
        'status',
        'last_evaluated_at',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:8',
            'current_value' => 'decimal:8',
            'last_evaluated_at' => 'datetime',
            'config' => 'array',
        ];
    }

    public function isHolding(): bool
    {
        return $this->status === 'HOLDING';
    }

    public function isTripped(): bool
    {
        return $this->status === 'TRIPPED';
    }
}
