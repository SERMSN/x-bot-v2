<?php

return [
    "webhook" => [
        "dedup_ttl_minutes" => env("TELEGRAM_WEBHOOK_DEDUP_TTL_MINUTES", 10),
    ],
];
