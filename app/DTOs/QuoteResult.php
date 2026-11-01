<?php

namespace App\DTOs;

class QuoteResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $corridor,
        public readonly string $tradeSize,
        public readonly string $quotedOutput,
        public readonly string $quotedOutputFormatted,
        public readonly array $rawResponse,
    ) {}
}
