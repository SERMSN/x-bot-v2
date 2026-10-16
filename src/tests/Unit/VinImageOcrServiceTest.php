<?php

namespace Tests\Unit;

use App\Services\Telegram\Services\VinImageOcrService;
use PHPUnit\Framework\TestCase;

class VinImageOcrServiceTest extends TestCase
{
    public function test_extracts_a_single_vin_from_ocr_text(): void
    {
        $service = new VinImageOcrService();

        $this->assertSame(
            '1HGCM82633A004352',
            $service->extractVinCandidate("VIN: 1HGCM82633A004352\n"),
        );
    }

    public function test_joins_vin_characters_separated_by_spaces(): void
    {
        $service = new VinImageOcrService();

        $this->assertSame(
            '1HGCM82633A004352',
            $service->extractVinCandidate('VIN: 1HGCM 82633 A004352'),
        );
        $this->assertSame(
            '1HGCM82633A004352',
            $service->extractVinCandidate('VN 1HGCM82633A004352'),
        );
    }

    public function test_rejects_invalid_or_ambiguous_vin_candidates(): void
    {
        $service = new VinImageOcrService();

        $this->assertNull($service->extractVinCandidate('1HGCM82633A00435I'));
        $this->assertNull($service->extractVinCandidate('VIN 1HGCM82633A0043'));
        $this->assertNull($service->extractVinCandidate("1HGCM82633A004352\n1M8GDM9AXKP042788"));
    }
}