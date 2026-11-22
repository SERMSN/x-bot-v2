<?php

namespace Tests\Unit;

use App\Services\Psychologist\DeepSeekChatService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekChatServiceTest extends TestCase
{
    public function test_it_calls_deepseek_with_thinking_disabled(): void
    {
        config([
            "psychologist.api.key" => "test-key",
            "psychologist.api.base_url" => "https://api.deepseek.com",
            "psychologist.api.model" => "deepseek-flash",
        ]);

        Http::fake([
            "api.deepseek.com/*" => Http::response([
                "choices" => [[
                    "message" => ["content" => "Я рядом. Что происходит?"],
                ]],
                "usage" => [
                    "prompt_tokens" => 120,
                    "completion_tokens" => 20,
                ],
            ]),
        ]);
        Http::preventStrayRequests();

        $result = (new DeepSeekChatService())->complete([
            ["role" => "user", "content" => "Мне тревожно"],
        ]);

        $this->assertSame("Я рядом. Что происходит?", $result["content"]);
        $this->assertSame(120, $result["input_tokens"]);
        $this->assertSame(20, $result["output_tokens"]);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $data["model"] === "deepseek-flash"
                && $data["thinking"] === ["type" => "disabled"]
                && $data["stream"] === false;
        });
    }

    public function test_it_requires_its_own_api_key(): void
    {
        config(["psychologist.api.key" => null]);

        $this->expectException(\RuntimeException::class);
        (new DeepSeekChatService())->complete([
            ["role" => "user", "content" => "Привет"],
        ]);
    }
}
