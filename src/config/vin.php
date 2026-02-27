<?php

return [
    "api" => [
        "decode_url" => env(
            "VIN_API_DECODE_URL",
            "https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues",
        ),
        "timeout_seconds" => env("VIN_API_TIMEOUT_SECONDS", 8),
        "retries" => env("VIN_API_RETRIES", 2),
        "retry_sleep_ms" => env("VIN_API_RETRY_SLEEP_MS", 200),
    ],
    "cache" => [
        "ttl_minutes" => env("VIN_CACHE_TTL_MINUTES", 1440),
    ],
    "validation" => [
        "length" => env("VIN_LENGTH", 17),
    ],
];
