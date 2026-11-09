<?php

return [
    "api" => [
        "timeout_seconds" => env("VIN_API_TIMEOUT_SECONDS", 8),
        "retries" => env("VIN_API_RETRIES", 2),
        "retry_sleep_ms" => env("VIN_API_RETRY_SLEEP_MS", 200),
    ],
    "cache" => [
        "ttl_minutes" => env("VIN_CACHE_TTL_MINUTES", 1440),
        "settings_ttl_minutes" => env("VIN_SETTINGS_CACHE_TTL_MINUTES", 10),
    ],
    "validation" => [
        "length" => env("VIN_LENGTH", 17),
    ],
    "photo" => [
        "confirmation_ttl_minutes" => env("VIN_PHOTO_CONFIRMATION_TTL_MINUTES", 5),
        "rate_limit_seconds" => env("VIN_PHOTO_RATE_LIMIT_SECONDS", 10),
    ],
    "web_app" => [
        "init_data_ttl_seconds" => env("VIN_WEB_APP_INIT_DATA_TTL_SECONDS", 86400),
    ],
];
