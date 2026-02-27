<?php

return [
    "http" => [
        "timeout_seconds" => env("WEATHER_HTTP_TIMEOUT_SECONDS", 5),
        "retries" => env("WEATHER_HTTP_RETRIES", 2),
        "retry_sleep_ms" => env("WEATHER_HTTP_RETRY_SLEEP_MS", 200),
    ],
    "cache" => [
        "settings_ttl_minutes" => env("WEATHER_SETTINGS_CACHE_TTL_MINUTES", 10),
        "weather_ttl_minutes" => env("WEATHER_CACHE_TTL_MINUTES", 60),
    ],
    "validation" => [
        "city_min_length" => env("WEATHER_CITY_MIN_LENGTH", 2),
        "geo_search_limit" => env("WEATHER_GEO_SEARCH_LIMIT", 5),
    ],
];
