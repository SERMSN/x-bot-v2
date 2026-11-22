<?php

namespace App\Services\Psychologist;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeepSeekChatService
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @return array{content: string, input_tokens: int, output_tokens: int}
     */
    public function complete(array $messages): array
    {
        $apiKey = trim((string) config("psychologist.api.key", ""));
        if ($apiKey === "") {
            throw new RuntimeException("PSYCHOLOGIST_DEEPSEEK_API_KEY is not configured");
        }

        $payload = [
            "model" => (string) config(
                "psychologist.api.model",
                "deepseek-flash",
            ),
            "messages" => $messages,
            "thinking" => ["type" => "disabled"],
            "stream" => false,
            "max_tokens" => (int) config(
                "psychologist.api.max_output_tokens",
                700,
            ),
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config("psychologist.api.timeout_seconds", 30))
                ->retry(
                    (int) config("psychologist.api.retries", 2),
                    (int) config("psychologist.api.retry_sleep_ms", 300),
                    null,
                    false,
                )
                ->post(
                    rtrim((string) config(
                        "psychologist.api.base_url",
                        "https://api.deepseek.com",
                    ), "/") . "/chat/completions",
                    $payload,
                );
        } catch (Throwable $e) {
            Log::warning("Psychologist DeepSeek request failed", [
                "error_class" => $e::class,
            ]);

            throw new RuntimeException("Psychologist AI request failed", 0, $e);
        }

        if (!$response->successful()) {
            Log::warning("Psychologist DeepSeek API error", [
                "status" => $response->status(),
                "error" => Str::limit(
                    (string) $response->json("error.message", ""),
                    200,
                ),
            ]);

            throw new RuntimeException("Psychologist AI API error");
        }

        $content = $response->json("choices.0.message.content");
        if (is_array($content)) {
            $content = collect($content)
                ->map(fn ($part) => is_array($part) ? ($part["text"] ?? "") : "")
                ->filter()
                ->implode("\n");
        }

        $content = is_string($content) ? trim($content) : "";
        if ($content === "") {
            Log::warning("Psychologist DeepSeek returned empty content");
            throw new RuntimeException("Psychologist AI returned empty content");
        }

        return [
            "content" => $content,
            "input_tokens" => (int) $response->json("usage.prompt_tokens", 0),
            "output_tokens" => (int) $response->json("usage.completion_tokens", 0),
        ];
    }
}
