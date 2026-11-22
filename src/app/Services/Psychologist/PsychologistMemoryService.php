<?php

namespace App\Services\Psychologist;

use App\Models\PsychologistEvent;
use App\Models\PsychologistMemory;
use App\Models\PsychologistMoodEntry;
use App\Models\PsychologistProfile;
use App\Models\PsychologistSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PsychologistMemoryService
{
    private const SUMMARY_PROMPT = <<<'PROMPT'
Ты — модуль долговременной памяти психологического бота.
Сделай краткое резюме разговора для будущих сессий.

Правила:
- 3–6 предложений.
- Только темы, чувства, цели, повторяющиеся паттерны, что помогает и какой следующий шаг обсуждался.
- Не цитируй сообщения дословно.
- Не сохраняй имена, телефоны, адреса, работодателей, номера документов и другие персональные данные.
- Не ставь диагнозы.
- Если информации мало, так и напиши.

Верни строго JSON:
{"summary":"...", "topics":["..."], "goals":["..."]}
PROMPT;

    public function __construct(
        private readonly DeepSeekChatService $client,
        private readonly PsychologistContextStore $contextStore,
    ) {}

    public function getSummary(PsychologistProfile $profile): ?string
    {
        $memory = $this->memory($profile);
        $summary = $memory->summary;

        return is_string($summary) && trim($summary) !== ""
            ? trim($summary)
            : null;
    }

    public function maybeSummarize(
        PsychologistProfile $profile,
        PsychologistSession $session,
    ): void {
        $threshold = max(2, (int) config(
            "psychologist.context.summary_after_messages",
            8,
        ));

        if ($session->user_messages === 0 || $session->user_messages % $threshold !== 0) {
            return;
        }

        try {
            $this->summarize($profile);
        } catch (Throwable $e) {
            Log::warning("Psychologist memory summary failed", [
                "profile_id" => $profile->id,
                "error_class" => $e::class,
            ]);
        }
    }

    public function summarize(PsychologistProfile $profile): ?string
    {
        $context = $this->contextStore->get($profile);
        if (count($context) < 2) {
            return null;
        }

        $transcript = collect($context)
            ->map(fn (array $turn) => ($turn["role"] === "user" ? "Клиент: " : "Бот: ") . $turn["content"])
            ->implode("\n\n");

        $response = $this->client->complete([
            ["role" => "system", "content" => self::SUMMARY_PROMPT],
            [
                "role" => "user",
                "content" => "История разговора:\n\n" . Str::limit($transcript, 12000),
            ],
        ]);

        $data = $this->decodeSummary($response["content"]);
        $summary = trim((string) ($data["summary"] ?? $response["content"]));
        $summary = Str::limit(
            $summary,
            max(300, (int) config("psychologist.context.max_summary_chars", 1800)),
        );

        if ($summary === "") {
            return null;
        }

        $topics = $this->stringList($data["topics"] ?? []);
        $goals = $this->stringList($data["goals"] ?? []);

        $memory = $this->memory($profile);
        $memory->summary = $summary;
        $memory->topics = $topics;
        $memory->goals = $goals;
        $memory->last_summarized_at = now();
        $memory->save();

        return $summary;
    }

    public function forget(PsychologistProfile $profile): void
    {
        $this->memory($profile)->delete();
        PsychologistMoodEntry::query()
            ->where("psychologist_profile_id", $profile->id)
            ->delete();
        PsychologistEvent::query()
            ->where("psychologist_profile_id", $profile->id)
            ->delete();
        $this->contextStore->forget($profile);

        PsychologistSession::query()
            ->where("psychologist_profile_id", $profile->id)
            ->where("status", "active")
            ->update([
                "status" => "forgotten",
                "ended_at" => now(),
            ]);
    }

    private function memory(PsychologistProfile $profile): PsychologistMemory
    {
        return PsychologistMemory::query()->firstOrCreate(
            ["psychologist_profile_id" => $profile->id],
            [
                "summary" => null,
                "topics" => [],
                "goals" => [],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSummary(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', "", $content) ?? $content;

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ["summary" => $content];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? trim($item) : "",
            $value,
        )));
    }
}
