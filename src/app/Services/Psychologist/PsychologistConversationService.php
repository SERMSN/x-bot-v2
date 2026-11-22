<?php

namespace App\Services\Psychologist;

use App\Models\PsychologistProfile;
use App\Models\PsychologistSession;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Cache;

class PsychologistConversationService
{
    public function __construct(
        private readonly DeepSeekChatService $client,
        private readonly PsychologistPromptBuilder $promptBuilder,
        private readonly PsychologistContextStore $contextStore,
        private readonly PsychologistMemoryService $memoryService,
    ) {}

    public function ensureProfile(
        TelegraphBot $bot,
        TelegraphChat $chat,
    ): PsychologistProfile {
        return PsychologistProfile::query()->firstOrCreate(
            [
                "telegraph_bot_id" => (int) $bot->id,
                "telegraph_chat_id" => (int) $chat->id,
            ],
            [
                "style" => "warm",
            ],
        );
    }

    public function activeSession(PsychologistProfile $profile): PsychologistSession
    {
        return PsychologistSession::query()->firstOrCreate(
            [
                "psychologist_profile_id" => $profile->id,
                "status" => "active",
            ],
            [
                "started_at" => now(),
                "message_count" => 0,
                "user_messages" => 0,
                "assistant_messages" => 0,
                "input_tokens" => 0,
                "output_tokens" => 0,
                "safety_flags" => 0,
            ],
        );
    }

    public function resetSession(PsychologistProfile $profile): void
    {
        PsychologistSession::query()
            ->where("psychologist_profile_id", $profile->id)
            ->where("status", "active")
            ->update([
                "status" => "ended",
                "ended_at" => now(),
            ]);

        $this->contextStore->forget($profile);
    }

    /**
     * @return array{content: string, input_tokens: int, output_tokens: int}
     */
    public function reply(PsychologistProfile $profile, string $userText): array
    {
        $context = $this->contextStore->get($profile);
        $messages = $this->promptBuilder->messages(
            $profile,
            $this->memoryService->getSummary($profile),
            $context,
            $userText,
        );

        $result = $this->client->complete($messages);

        $context[] = ["role" => "user", "content" => $userText];
        $context[] = ["role" => "assistant", "content" => $result["content"]];
        $this->contextStore->put($profile, $context);

        $session = $this->activeSession($profile);
        $session->message_count += 2;
        $session->user_messages += 1;
        $session->assistant_messages += 1;
        $session->input_tokens += (int) $result["input_tokens"];
        $session->output_tokens += (int) $result["output_tokens"];
        $session->save();

        $profile->last_active_at = now();
        $profile->save();

        $this->memoryService->maybeSummarize($profile, $session->refresh());

        return $result;
    }

    public function summarizeNow(PsychologistProfile $profile): ?string
    {
        return $this->memoryService->summarize($profile);
    }

    public function hasMemory(PsychologistProfile $profile): bool
    {
        return $this->memoryService->getSummary($profile) !== null
            || $this->contextStore->get($profile) !== [];
    }

    public function memorySummary(PsychologistProfile $profile): ?string
    {
        return $this->memoryService->getSummary($profile);
    }

    public function forget(PsychologistProfile $profile): void
    {
        $this->memoryService->forget($profile);
        $this->clearCrisisPending($profile);
    }

    public function recordCrisis(PsychologistProfile $profile): void
    {
        $session = $this->activeSession($profile);
        $session->safety_flags += 1;
        $session->save();
    }

    public function setStyle(PsychologistProfile $profile, string $style): void
    {
        if (!in_array($style, ["warm", "direct", "brief"], true)) {
            return;
        }

        $profile->style = $style;
        $profile->save();
    }

    public function markCrisisPending(PsychologistProfile $profile): void
    {
        Cache::put(
            $this->crisisKey($profile),
            true,
            now()->addHour(),
        );
    }

    public function crisisPending(PsychologistProfile $profile): bool
    {
        return (bool) Cache::get($this->crisisKey($profile), false);
    }

    public function clearCrisisPending(PsychologistProfile $profile): void
    {
        Cache::forget($this->crisisKey($profile));
    }

    private function crisisKey(PsychologistProfile $profile): string
    {
        return "psychologist:crisis:{$profile->id}";
    }
}
