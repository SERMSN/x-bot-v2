<?php

namespace App\Services\Psychologist;

class PsychologistSafetyService
{
    public function detect(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === "") {
            return false;
        }

        foreach ((array) config("psychologist.safety.crisis_keywords", []) as $keyword) {
            if (is_string($keyword) && $keyword !== "" && str_contains($normalized, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    public function crisisText(): string
    {
        return "Мне важно, чтобы ты был(а) в безопасности. Если сейчас есть мысли причинить себе вред или рядом есть опасность — пожалуйста, не оставайся один(а).\n\n"
            . "Позвони 112 — единый номер экстренных служб, или 103 — скорая помощь. Напиши близкому человеку, которому доверяешь, и скажи, что тебе нужна поддержка прямо сейчас.\n\n"
            . "Если можешь, ответь одним словом: ты сейчас в безопасности?";
    }
}
