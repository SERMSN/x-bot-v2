<?php

namespace Tests\Unit;

use App\Services\Psychologist\PsychologistSafetyService;
use Tests\TestCase;

class PsychologistSafetyServiceTest extends TestCase
{
    public function test_it_detects_a_crisis_phrase(): void
    {
        $service = new PsychologistSafetyService();

        $this->assertTrue($service->detect("Иногда я думаю, что не хочу жить"));
    }

    public function test_it_does_not_flag_regular_sadness(): void
    {
        $service = new PsychologistSafetyService();

        $this->assertFalse($service->detect("Мне грустно и я устал"));
    }

    public function test_crisis_text_contains_emergency_numbers(): void
    {
        $text = (new PsychologistSafetyService())->crisisText();

        $this->assertStringContainsString("112", $text);
        $this->assertStringContainsString("103", $text);
        $this->assertStringContainsString("безопасности", $text);
    }
}
