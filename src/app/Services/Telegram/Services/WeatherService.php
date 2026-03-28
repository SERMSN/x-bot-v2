<?php

namespace App\Services\Telegram\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const MAX_HOURLY_INTERVALS_PER_DAY = 4;
    private const DAILY_FORECAST_DAYS = 3;
    private const WEATHER_CACHE_SCHEMA_VERSION = "v3";

    public function __construct(
        private readonly WeatherMessageFormatter $messageFormatter,
    ) {}

    private function httpClient()
    {
        return Http::timeout(
            (int) config("weather.http.timeout_seconds", 5),
        )->retry(
            (int) config("weather.http.retries", 2),
            (int) config("weather.http.retry_sleep_ms", 200),
        );
    }

    private function ensureApiConfig(int $botId): array
    {
        $cacheKey = "bot_settings_openweather_{$botId}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(
                (int) config("weather.cache.settings_ttl_minutes", 10),
            ),
            function () use ($botId) {
                $settings = BotSetting::query()
                    ->where("telegraph_bot_id", $botId)
                    ->whereIn("key", [
                        BotSetting::KEY_OPENWEATHER_API_KEY,
                        BotSetting::KEY_OPENWEATHER_API_URL,
                    ])
                    ->pluck("value", "key");

                $apiKey =
                    (string) ($settings[BotSetting::KEY_OPENWEATHER_API_KEY] ??
                        "");
                $weatherUrl =
                    (string) ($settings[BotSetting::KEY_OPENWEATHER_API_URL] ??
                        "");

                if ($apiKey === "") {
                    throw new \Exception(
                        "OPENWEATHER_API_KEY is not configured in bot_settings",
                    );
                }

                if ($weatherUrl === "") {
                    throw new \Exception(
                        "OPENWEATHER_API_URL is not configured in bot_settings",
                    );
                }

                return [
                    "api_key" => $apiKey,
                    "weather_url" => $weatherUrl,
                    "geo_url" => $this->resolveGeoUrl($weatherUrl),
                    "forecast_url" => $this->resolveForecastUrl($weatherUrl),
                ];
            },
        );
    }

    private function resolveGeoUrl(string $weatherUrl): string
    {
        $normalized = rtrim($weatherUrl, "/");

        if (str_ends_with($normalized, "/data/2.5/weather")) {
            return str_replace(
                "/data/2.5/weather",
                "/geo/1.0/direct",
                $normalized,
            );
        }

        $parts = parse_url($normalized);
        $scheme = $parts["scheme"] ?? "https";
        $host = $parts["host"] ?? "api.openweathermap.org";
        return "{$scheme}://{$host}/geo/1.0/direct";
    }

    private function resolveForecastUrl(string $weatherUrl): string
    {
        $normalized = rtrim($weatherUrl, "/");

        if (str_ends_with($normalized, "/data/2.5/weather")) {
            return str_replace(
                "/data/2.5/weather",
                "/data/2.5/forecast",
                $normalized,
            );
        }

        return "";
    }

    public function getByCoordinates(
        int $botId,
        float $lat,
        float $lon,
        array $options = [],
    ): array {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException("Invalid coordinates");
        }

        $settings = $this->resolveWeatherOptions($options);

        $cacheKey = sprintf(
            "weather_bot_%s_%d_%s_%s_%s_%s",
            self::WEATHER_CACHE_SCHEMA_VERSION,
            $botId,
            $lat,
            $lon,
            $settings["units"],
            $settings["response_mode"],
        );

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(
                (int) config("weather.cache.weather_ttl_minutes", 60),
            ),
            function () use ($botId, $lat, $lon, $settings) {
                $config = $this->ensureApiConfig($botId);
                $response = $this->httpClient()->get($config["weather_url"], [
                    "lat" => $lat,
                    "lon" => $lon,
                    "appid" => $config["api_key"],
                    "units" => $settings["units"],
                    "lang" => "ru",
                ]);

                if (!$response->successful()) {
                    Log::warning("OpenWeather API error", [
                        "status" => $response->status(),
                    ]);
                    throw new \Exception("API request failed");
                }

                $forecastData = $this->fetchForecastByCoordinates(
                    $config,
                    $lat,
                    $lon,
                    $settings["units"],
                );

                return $this->formatWeatherData(
                    $response->json(),
                    $forecastData,
                    $settings,
                );
            },
        );
    }

    public function validateCity(int $botId, string $city): array
    {
        $city = trim($city);
        if (
            mb_strlen($city) <
            (int) config("weather.validation.city_min_length", 2)
        ) {
            throw new \InvalidArgumentException("City name too short");
        }

        // Приводим к нормализованному виду (первая буква заглавная, остальные строчные)
        $normalizedCity = ucfirst(mb_strtolower($city));

        $config = $this->ensureApiConfig($botId);
        $response = $this->httpClient()->get($config["geo_url"], [
            "q" => $normalizedCity,
            "limit" => (int) config("weather.validation.geo_search_limit", 5),
            "appid" => $config["api_key"],
        ]);

        if (!$response->successful() || empty($response->json())) {
            throw new \Exception("Город не найден");
        }

        // Ищем точное совпадение (не учитываем регистр)
        $data = $response->json();
        foreach ($data as $cityData) {
            if ($this->isExactCityMatch($cityData, $city)) {
                return [
                    "name" => $this->resolveLocalizedCityName($cityData),
                    "country" => $cityData["country"],
                    "normalized" => "{$cityData["name"]}, {$cityData["country"]}",
                    "display_name" => $this->resolveLocalizedCityName(
                        $cityData,
                    ),
                    "lat" => (float) ($cityData["lat"] ?? 0),
                    "lon" => (float) ($cityData["lon"] ?? 0),
                ];
            }
        }

        // Если точного совпадения нет, берем первый результат
        $firstResult = $data[0];
        return [
            "name" => $this->resolveLocalizedCityName($firstResult),
            "country" => $firstResult["country"],
            "normalized" => "{$firstResult["name"]}, {$firstResult["country"]}",
            "display_name" => $this->resolveLocalizedCityName($firstResult),
            "lat" => (float) ($firstResult["lat"] ?? 0),
            "lon" => (float) ($firstResult["lon"] ?? 0),
        ];
    }

    protected function formatWeatherData(
        array $data,
        ?array $forecastData = null,
        array $settings = [],
    ): array {
        $payload = $this->buildWeatherPayload($data, $forecastData, $settings);

        return [
            "text" => $this->messageFormatter->format($payload),
            "city" => [
                "name" => (string) ($data["name"] ?? ""),
                "lat" => isset($data["coord"]["lat"])
                    ? (float) $data["coord"]["lat"]
                    : null,
                "lon" => isset($data["coord"]["lon"])
                    ? (float) $data["coord"]["lon"]
                    : null,
                "timezone" => $this->resolveTimezoneIdentifier(
                    $forecastData,
                    $data,
                ),
            ],
        ];
    }

    public function resolveNotificationTimezoneByCoordinates(
        int $botId,
        float $lat,
        float $lon,
    ): string {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException("Invalid coordinates");
        }

        $config = $this->ensureApiConfig($botId);
        $response = $this->httpClient()->get($config["weather_url"], [
            "lat" => $lat,
            "lon" => $lon,
            "appid" => $config["api_key"],
            "lang" => "ru",
        ]);

        if (!$response->successful()) {
            Log::warning("OpenWeather timezone resolution API error", [
                "status" => $response->status(),
                "lat" => $lat,
                "lon" => $lon,
            ]);
            throw new \Exception("Timezone resolution request failed");
        }

        return $this->resolveTimezoneIdentifier(null, $response->json());
    }

    private function buildWeatherPayload(
        array $data,
        ?array $forecastData = null,
        array $settings = [],
    ): array {
        $cityName = $this->declineCityName($data["name"]);
        $timezone = $this->resolveTimezone($forecastData, $data);
        $localNow = $this->resolveLocalNow($data, $timezone);
        $normalizedSettings = $this->resolveWeatherOptions($settings);

        return [
            "city_name" => $cityName,
            "response_mode" => $normalizedSettings["response_mode"],
            "temperature_unit" => $this->temperatureUnitLabel(
                $normalizedSettings["units"],
            ),
            "wind_speed_unit" => $this->windSpeedUnitLabel(
                $normalizedSettings["units"],
            ),
            "current" => [
                "temp" => (float) ($data["main"]["temp"] ?? 0),
                "description" =>
                    (string) ($data["weather"][0]["description"] ?? ""),
                "humidity" => (int) ($data["main"]["humidity"] ?? 0),
                "wind_speed" => (float) ($data["wind"]["speed"] ?? 0),
            ],
            "forecast_sections" => $this->buildHourlyForecastSections(
                $forecastData,
                $data,
            ),
            "daily_forecast" => $this->buildDailyForecast($forecastData, $data),
            "local_time" => $localNow->format("d.m.Y H:i"),
        ];
    }

    public function getByCityName(
        int $botId,
        string $cityName,
        array $options = [],
    ): array {
        $cityName = trim($cityName);
        if (
            mb_strlen($cityName) <
            (int) config("weather.validation.city_min_length", 2)
        ) {
            throw new \InvalidArgumentException("City name too short");
        }
        $settings = $this->resolveWeatherOptions($options);

        $cacheKey =
            "weather_city_bot_" .
            self::WEATHER_CACHE_SCHEMA_VERSION .
            "_{$botId}_" .
            mb_strtolower($cityName) .
            "_" .
            $settings["units"] .
            "_" .
            $settings["response_mode"];

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(
                (int) config("weather.cache.weather_ttl_minutes", 60),
            ),
            function () use ($botId, $cityName, $settings) {
                $config = $this->ensureApiConfig($botId);
                $response = $this->httpClient()->get($config["weather_url"], [
                    "q" => $cityName,
                    "appid" => $config["api_key"],
                    "units" => $settings["units"],
                    "lang" => "ru",
                ]);

                if (!$response->successful()) {
                    Log::warning("OpenWeather API error", [
                        "status" => $response->status(),
                    ]);
                    throw new \Exception("API request failed");
                }

                $forecastData = $this->fetchForecastByCityName(
                    $config,
                    $cityName,
                    $settings["units"],
                );

                return $this->formatWeatherData(
                    $response->json(),
                    $forecastData,
                    $settings,
                );
            },
        );
    }

    private function fetchForecastByCoordinates(
        array $config,
        float $lat,
        float $lon,
        string $units,
    ): ?array {
        if ($config["forecast_url"] === "") {
            return null;
        }

        $response = $this->httpClient()->get($config["forecast_url"], [
            "lat" => $lat,
            "lon" => $lon,
            "appid" => $config["api_key"],
            "units" => $units,
            "lang" => "ru",
        ]);

        if (!$response->successful()) {
            Log::warning("OpenWeather forecast API error", [
                "status" => $response->status(),
            ]);
            return null;
        }

        return $response->json();
    }

    private function fetchForecastByCityName(
        array $config,
        string $cityName,
        string $units,
    ): ?array {
        if ($config["forecast_url"] === "") {
            return null;
        }

        $response = $this->httpClient()->get($config["forecast_url"], [
            "q" => $cityName,
            "appid" => $config["api_key"],
            "units" => $units,
            "lang" => "ru",
        ]);

        if (!$response->successful()) {
            Log::warning("OpenWeather forecast API error", [
                "status" => $response->status(),
            ]);
            return null;
        }

        return $response->json();
    }

    private function buildHourlyForecastSections(
        ?array $forecastData,
        array $currentData,
    ): array {
        if (
            $forecastData === null ||
            !isset($forecastData["list"]) ||
            !is_array($forecastData["list"])
        ) {
            return [];
        }

        $timezone = $this->resolveTimezone($forecastData, $currentData);
        $nowLocal = $this->resolveLocalNow($currentData, $timezone);
        $endOfDayLocal = $nowLocal->setTime(23, 59, 59);
        $tomorrowStartLocal = $nowLocal->modify("+1 day")->setTime(0, 0, 0);
        $tomorrowEndLocal = $tomorrowStartLocal->setTime(23, 59, 59);

        $todayRows = [];
        $tomorrowRows = [];
        foreach ($forecastData["list"] as $item) {
            if (!isset($item["dt"], $item["main"]["temp"])) {
                continue;
            }

            $itemLocal = new \DateTimeImmutable("@" . $item["dt"])->setTimezone(
                $timezone,
            );

            if ($itemLocal <= $nowLocal) {
                continue;
            }

            $row = [
                "time" => $itemLocal->format("H:i"),
                "temp" => (float) $item["main"]["temp"],
            ];

            if ($itemLocal <= $endOfDayLocal) {
                $todayRows[] = $row;
                continue;
            }

            if (
                $itemLocal >= $tomorrowStartLocal &&
                $itemLocal <= $tomorrowEndLocal
            ) {
                $tomorrowRows[] = $row;
            }
        }

        $sections = [];

        if ($todayRows !== []) {
            $sections[] = [
                "title" => "🕒 До конца дня:",
                "rows" => array_slice(
                    $todayRows,
                    0,
                    self::MAX_HOURLY_INTERVALS_PER_DAY,
                ),
            ];
        }

        if ($tomorrowRows !== []) {
            $sections[] = [
                "title" => sprintf(
                    "📅 Завтра, %s:",
                    $tomorrowStartLocal->format("d.m"),
                ),
                "rows" => array_slice(
                    $tomorrowRows,
                    0,
                    self::MAX_HOURLY_INTERVALS_PER_DAY,
                ),
            ];
        }

        return $sections;
    }

    private function buildDailyForecast(
        ?array $forecastData,
        array $currentData,
    ): array {
        if (
            $forecastData === null ||
            !isset($forecastData["list"]) ||
            !is_array($forecastData["list"])
        ) {
            return [];
        }

        $timezone = $this->resolveTimezone($forecastData, $currentData);
        $nowLocal = $this->resolveLocalNow($currentData, $timezone);
        $todayKey = $nowLocal->format("Y-m-d");
        $days = [];

        foreach ($forecastData["list"] as $item) {
            if (
                !isset(
                    $item["dt"],
                    $item["main"]["temp"],
                    $item["weather"][0]["description"],
                )
            ) {
                continue;
            }

            $itemLocal = new \DateTimeImmutable("@" . $item["dt"]);
            $itemLocal = $itemLocal->setTimezone($timezone);
            $dateKey = $itemLocal->format("Y-m-d");

            if ($dateKey <= $todayKey) {
                continue;
            }

            if (!isset($days[$dateKey])) {
                $days[$dateKey] = [
                    "label" => $this->dailyForecastLabel($itemLocal, $nowLocal),
                    "temp_min" => (float) $item["main"]["temp"],
                    "temp_max" => (float) $item["main"]["temp"],
                    "description" =>
                        (string) $item["weather"][0]["description"],
                    "description_hour_distance" => abs(
                        ((int) $itemLocal->format("H")) - 12,
                    ),
                ];
            } else {
                $days[$dateKey]["temp_min"] = min(
                    $days[$dateKey]["temp_min"],
                    (float) $item["main"]["temp"],
                );
                $days[$dateKey]["temp_max"] = max(
                    $days[$dateKey]["temp_max"],
                    (float) $item["main"]["temp"],
                );

                $distanceToNoon = abs(((int) $itemLocal->format("H")) - 12);
                if (
                    $distanceToNoon <
                    $days[$dateKey]["description_hour_distance"]
                ) {
                    $days[$dateKey]["description"] =
                        (string) $item["weather"][0]["description"];
                    $days[$dateKey][
                        "description_hour_distance"
                    ] = $distanceToNoon;
                }
            }
        }

        $result = [];
        foreach (
            array_slice($days, 0, self::DAILY_FORECAST_DAYS, true)
            as $day
        ) {
            unset($day["description_hour_distance"]);
            $result[] = $day;
        }

        return $result;
    }

    private function resolveTimezone(
        ?array $forecastData,
        array $currentData,
    ): \DateTimeZone {
        $timezoneOffset =
            (int) ($forecastData["city"]["timezone"] ??
                ($currentData["timezone"] ?? 0));

        return $this->timezoneFromOffset($timezoneOffset);
    }

    private function resolveTimezoneIdentifier(
        ?array $forecastData,
        array $currentData,
    ): string {
        return $this->resolveTimezone($forecastData, $currentData)->getName();
    }

    private function resolveLocalNow(
        array $currentData,
        \DateTimeZone $timezone,
    ): \DateTimeImmutable {
        $nowUtc = (int) ($currentData["dt"] ?? time());

        return new \DateTimeImmutable("@{$nowUtc}")->setTimezone($timezone);
    }

    private function resolveLocalizedCityName(array $cityData): string
    {
        $localizedName =
            $cityData["local_names"]["ru"] ?? ($cityData["name"] ?? "");

        return is_string($localizedName) ? trim($localizedName) : "";
    }

    private function isExactCityMatch(array $cityData, string $city): bool
    {
        $needle = mb_strtolower(trim($city));
        if ($needle === "") {
            return false;
        }

        $variants = array_filter([
            $cityData["name"] ?? null,
            $cityData["local_names"]["ru"] ?? null,
        ]);

        foreach ($variants as $variant) {
            if (!is_string($variant)) {
                continue;
            }

            if (mb_strtolower(trim($variant)) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function timezoneFromOffset(int $offsetSeconds): \DateTimeZone
    {
        $sign = $offsetSeconds >= 0 ? "+" : "-";
        $abs = abs($offsetSeconds);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);

        return new \DateTimeZone(
            sprintf("%s%02d:%02d", $sign, $hours, $minutes),
        );
    }

    protected function declineCityName(string $cityName): string
    {
        // Правила склонения русских названий городов
        $rules = [
            // Города на -ск (Красноярск → Красноярске)
            '/ск$/i' => "ске",
            // Города на -цк (Новосибирск → Новосибирске, но это покрывается предыдущим правилом)
            '/цк$/i' => "цке",
            // Города на -к (Москва → Москве, Омск → Омске)
            '/к$/i' => "ке",
            // Города на -г (Волгоград → Волгограде)
            '/г$/i' => "ге",
            // Города на -д (Ленинград → Ленинграде)
            '/д$/i' => "де",
            // Города на -н (Ростов-на-Дону → Ростове-на-Дону)
            '/н$/i' => "не",
            // Города на -т (Владивосток → Владивостоке)
            '/т$/i' => "те",
            // Города на -в (Ярославль → Ярославле)
            '/в$/i' => "ве",
            // Города на -ль (Усть-Илимск → Усть-Илимске, но это покрывается предыдущими)
            '/ль$/i' => "ле",
            // Города на -рь (Тверь → Твери)
            '/рь$/i' => "ри",
            // Города на -й (Санкт-Петербург → Санкт-Петербурге)
            '/й$/i' => "е",
            // Города на -я (Кемерово → Кемерово, не меняется)
            '/я$/i' => "е",
            // Города на -а (Казань → Казани)
            '/а$/i' => "е",
            // Города на -о (Мурманско → Мурманске)
            '/о$/i' => "е",
        ];

        // Исключения из правил
        $exceptions = [
            "Москва" => "Москве",
            "Санкт-Петербург" => "Санкт-Петербурге",
            "Ростов-на-Дону" => "Ростове-на-Дону",
            "Нижний Новгород" => "Нижнем Новгороде",
            "Великий Новгород" => "Великом Новгороде",
            "Улан-Удэ" => "Улан-Удэ",
            "Йошкар-Ола" => "Йошкар-Оле",
            "Кострома" => "Костроме",
            "Вологда" => "Вологде",
            "Тверь" => "Твери",
            "Казань" => "Казани",
            "Пермь" => "Перми",
            "Ярославль" => "Ярославле",
            "Владимир" => "Владимире",
            "Тула" => "Туле",
            "Рязань" => "Рязани",
            "Курск" => "Курске",
            "Белгород" => "Белгороде",
            "Воронеж" => "Воронеже",
            "Тамбов" => "Тамбове",
            "Пенза" => "Пензе",
            "Самара" => "Самаре",
            "Саратов" => "Саратове",
            "Волгоград" => "Волгограде",
            "Астрахань" => "Астрахани",
            "Краснодар" => "Краснодаре",
            "Сочи" => "Сочи",
            "Ставрополь" => "Ставрополе",
            "Ростов" => "Ростове",
            "Уфа" => "Уфе",
            "Челябинск" => "Челябинске",
            "Екатеринбург" => "Екатеринбурге",
            "Тюмень" => "Тюмени",
            "Омск" => "Омске",
            "Новосибирск" => "Новосибирске",
            "Красноярск" => "Красноярске",
            "Иркутск" => "Иркутске",
            "Хабаровск" => "Хабаровске",
            "Владивосток" => "Владивостоке",
        ];

        // Проверяем исключения first
        if (isset($exceptions[$cityName])) {
            return $exceptions[$cityName];
        }

        // Применяем правила склонения
        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $cityName)) {
                return preg_replace($pattern, $replacement, $cityName);
            }
        }

        // Если не подошло ни одно правило, возвращаем исходное название
        return $cityName;
    }

    private function resolveWeatherOptions(array $options): array
    {
        $units = (string) ($options["units"] ?? "metric");
        if (!in_array($units, ["metric", "imperial"], true)) {
            $units = "metric";
        }

        $responseMode = (string) ($options["response_mode"] ?? "detailed");
        if (!in_array($responseMode, ["brief", "detailed"], true)) {
            $responseMode = "detailed";
        }

        return [
            "units" => $units,
            "response_mode" => $responseMode,
        ];
    }

    private function temperatureUnitLabel(string $units): string
    {
        return $units === "imperial" ? "F" : "C";
    }

    private function windSpeedUnitLabel(string $units): string
    {
        return $units === "imperial" ? "mph" : "м/с";
    }

    private function dailyForecastLabel(
        \DateTimeImmutable $day,
        \DateTimeImmutable $nowLocal,
    ): string {
        $diffDays = (int) $nowLocal
            ->setTime(0, 0, 0)
            ->diff($day->setTime(0, 0, 0))
            ->format("%a");

        if ($diffDays === 1) {
            return "Завтра";
        }

        return $day->format("d.m");
    }
}
