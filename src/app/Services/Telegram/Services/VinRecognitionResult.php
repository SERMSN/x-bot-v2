<?php

namespace App\Services\Telegram\Services;

class VinRecognitionResult
{
    /**
     * @param array<int, string> $alternatives
     */
    public function __construct(
        public readonly string $vin,
        public readonly float $confidence,
        public readonly array $alternatives = [],
        public readonly ?string $notes = null,
    ) {}
}
