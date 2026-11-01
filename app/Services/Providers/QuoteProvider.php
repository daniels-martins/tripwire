<?php

namespace App\Services\Providers;

use App\DTOs\QuoteResult;

interface QuoteProvider
{
    /**
     * @param  array<string, mixed>  $corridorConfig
     */
    public function getQuote(string $corridorKey, array $corridorConfig, string $sizeKey, float $sizeUsd): ?QuoteResult;

    public function name(): string;

    public function isEnabled(): bool;
}
