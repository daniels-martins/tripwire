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

    /**
     * Why the last getQuote() returned null. Only meaningful directly
     * after a null return.
     */
    public function failureReason(): ?string;

    public function isEnabled(): bool;
}
