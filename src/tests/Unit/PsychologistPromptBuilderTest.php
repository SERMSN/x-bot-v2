<?php

namespace Tests\Unit;

use App\Models\PsychologistProfile;
use App\Services\Psychologist\PsychologistPromptBuilder;
use PHPUnit\Framework\TestCase;

class PsychologistPromptBuilderTest extends TestCase
{
    public function test_it_builds_a_system_prompt_without_placeholders(): void
    {
        $profile = new PsychologistProfile([
            "style" => "brief",
            "preferred_name" => "Аня",
        ]);

        $prompt = (new PsychologistPromptBuilder())->systemPrompt(
            $profile,
            "Клиентка много работает и боится выгорания.",
        );

        $this->assertStringContainsString("Тихо", $prompt);
        $this->assertStringContainsString("не врач", $prompt);
        $this->assertStringContainsString("Клиентка много работает", $prompt);
        $this->assertStringContainsString("очень коротко", $prompt);
        $this->assertStringNotContainsString("{{", $prompt);
    }

    public function test_it_builds_messages_with_context_and_user_text(): void
    {
        $profile = new PsychologistProfile(["style" => "warm"]);

        $messages = (new PsychologistPromptBuilder())->messages(
            $profile,
            null,
            [
                ["role" => "user", "content" => "Привет"],
                ["role" => "assistant", "content" => "Здравствуй"],
            ],
            "Мне тревожно",
        );

        $this->assertSame("system", $messages[0]["role"]);
        $this->assertSame("Мне тревожно", $messages[3]["content"]);
    }
}
