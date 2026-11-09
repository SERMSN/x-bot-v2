<?php

namespace Tests\Unit;

use App\Services\Telegram\Services\VinImageOcrService;
use PHPUnit\Framework\TestCase;

class VinImageOcrServiceTest extends TestCase
{
    public function test_generates_a_random_vin_candidate_for_photo_stub(): void
    {
        $service = new VinImageOcrService();

        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPRSTUVWXYZ0-9]{17}$/', $service->recognize());
    }
}