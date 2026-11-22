<?php

namespace App\Services\Psychologist;

use App\Models\PsychologistProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class PsychologistContextStore
{
    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function get(PsychologistProfile $profile): array
    {
        $payload = Cache::get($this->key($profile));
        if (!is_string($payload) || $payload === "") {
            return [];
        }

        try {
            $decoded = json_decode(
                Crypt::decryptString($payload),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable) {
            Cache::forget($this->key($profile));
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, function ($turn): bool {
            return is_array($turn)
                && in_array($turn["role"] ?? null, ["user", "assistant"], true)
                && is_string($turn["content"] ?? null)
                && trim($turn["content"]) !== "";
        }));
    }

    /**
     * @param array<int, array{role: string, content: string}> $context
     */
    public function put(PsychologistProfile $profile, array $context): void
    {
        $maxTurns = max(2, (int) config("psychologist.context.max_turns", 12));
        if (count($context) > $maxTurns) {
            $context = array_slice($context, -$maxTurns);
        }

        $payload = Crypt::encryptString(
            json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );

        Cache::put(
            $this->key($profile),
            $payload,
            now()->addMinutes((int) config(
                "psychologist.context.ttl_minutes",
                180,
            )),
        );
    }

    public function forget(PsychologistProfile $profile): void
    {
        Cache::forget($this->key($profile));
    }

    private function key(PsychologistProfile $profile): string
    {
        return "psychologist:context:{$profile->telegraph_bot_id}:"
            . sha1((string) $profile->telegraph_chat_id);
    }
}
