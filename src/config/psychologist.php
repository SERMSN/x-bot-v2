<?php

return [
    "enabled" => env("PSYCHOLOGIST_ENABLED", true),

    "api" => [
        "key" => env("PSYCHOLOGIST_DEEPSEEK_API_KEY"),
        "base_url" => env(
            "PSYCHOLOGIST_DEEPSEEK_BASE_URL",
            "https://api.deepseek.com",
        ),
        "model" => env("PSYCHOLOGIST_DEEPSEEK_MODEL", "deepseek-flash"),
        "timeout_seconds" => env("PSYCHOLOGIST_TIMEOUT_SECONDS", 30),
        "retries" => env("PSYCHOLOGIST_RETRIES", 2),
        "retry_sleep_ms" => env("PSYCHOLOGIST_RETRY_SLEEP_MS", 300),
        "max_output_tokens" => env("PSYCHOLOGIST_MAX_OUTPUT_TOKENS", 700),
    ],

    "context" => [
        "max_turns" => env("PSYCHOLOGIST_CONTEXT_MAX_TURNS", 12),
        "ttl_minutes" => env("PSYCHOLOGIST_CONTEXT_TTL_MINUTES", 180),
        "summary_after_messages" => env(
            "PSYCHOLOGIST_SUMMARY_AFTER_MESSAGES",
            8,
        ),
        "max_summary_chars" => env("PSYCHOLOGIST_MAX_SUMMARY_CHARS", 1800),
    ],

    "safety" => [
        "crisis_keywords" => [
            "не хочу жить",
            "не хочу больше жить",
            "хочу умереть",
            "покончить с собой",
            "суицид",
            "самоубийство",
            "навредить себе",
            "причинить себе вред",
            "порезать себя",
            "вскрыть вены",
            "убить себя",
            "меня избивают",
            "он меня бьёт",
            "она меня бьёт",
            "насилие",
            "мне угрожают убийством",
        ],
    ],
];
