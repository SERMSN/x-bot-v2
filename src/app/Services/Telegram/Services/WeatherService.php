<?php

namespace App\Services\Telegram\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const MAX_HOURLY_INTERVALS_PER_DAY = 4;

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

    public function getByCoordinates(int $botId, float $lat, float $lon): array
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException("Invalid coordinates");
        }

        $cacheKey = "weather_bot_{$botId}_{$lat}_{$lon}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(
                (int) config("weather.cache.weather_ttl_minutes", 60),
            ),
            function () use ($botId, $lat, $lon) {
                $config = $this->ensureApiConfig($botId);
                $response = $this->httpClient()->get($config["weather_url"], [
                    "lat" => $lat,
                    "lon" => $lon,
                    "appid" => $config["api_key"],
                    "units" => "metric",
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
                );

                return $this->formatWeatherData(
                    $response->json(),
                    $forecastData,
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
            if (strtolower($cityData["name"]) === strtolower($city)) {
                return [
                    "name" => $this->resolveLocalizedCityName($cityData),
                    "country" => $cityData["country"],
                    "normalized" => "{$cityData["name"]}, {$cityData["country"]}",
                    "display_name" => $this->resolveLocalizedCityName(
                        $cityData,
                    ),
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
        ];
    }

    protected function formatWeatherData(
        array $data,
        ?array $forecastData = null,
    ): array {
        $cityName = $this->declineCityName($data["name"]);
        $timezone = $this->resolveTimezone($forecastData, $data);
        $localNow = $this->resolveLocalNow($data, $timezone);

        $text = sprintf(
            "🌦️ <b>Погода в %s</b>\n\n" .
                "🌡 Температура: <b>%s</b>\n" .
                "☁️ Состояние: <b>%s</b>\n" .
                "💧 Влажность: <b>%d%%</b>\n" .
                "🌬 Ветер: <b>%.1f м/с</b>\n\n" .
                "🕒 <b>Местное время</b>\n" .
                "%s",
            $cityName,
            $this->formatTemperature($data["main"]["temp"]),
            $data["weather"][0]["description"],
            $data["main"]["humidity"],
            $data["wind"]["speed"],
            $localNow->format("d.m.Y H:i"),
        );

        $forecastSections = $this->formatHourlyForecast($forecastData, $data);
        if ($forecastSections !== []) {
            $text .= "\n\n" . implode("\n\n", $forecastSections);
        }

        return [
            "text" => $text,
        ];
    }

    public function getByCityName(int $botId, string $cityName): array
    {
        $cityName = trim($cityName);
        if (
            mb_strlen($cityName) <
            (int) config("weather.validation.city_min_length", 2)
        ) {
            throw new \InvalidArgumentException("City name too short");
        }

        $cacheKey = "weather_city_bot_{$botId}_" . mb_strtolower($cityName);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(
                (int) config("weather.cache.weather_ttl_minutes", 60),
            ),
            function () use ($botId, $cityName) {
                $config = $this->ensureApiConfig($botId);
                $response = $this->httpClient()->get($config["weather_url"], [
                    "q" => $cityName,
                    "appid" => $config["api_key"],
                    "units" => "metric",
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
                );

                return $this->formatWeatherData(
                    $response->json(),
                    $forecastData,
                );
            },
        );
    }

    private function fetchForecastByCoordinates(
        array $config,
        float $lat,
        float $lon,
    ): ?array {
        if ($config["forecast_url"] === "") {
            return null;
        }

        $response = $this->httpClient()->get($config["forecast_url"], [
            "lat" => $lat,
            "lon" => $lon,
            "appid" => $config["api_key"],
            "units" => "metric",
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
    ): ?array {
        if ($config["forecast_url"] === "") {
            return null;
        }

        $response = $this->httpClient()->get($config["forecast_url"], [
            "q" => $cityName,
            "appid" => $config["api_key"],
            "units" => "metric",
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

    private function formatHourlyForecast(
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

            $row = sprintf(
                "%s - <b>%s</b>",
                $itemLocal->format("H:i"),
                $this->formatTemperature($item["main"]["temp"]),
            );

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
            $sections[] =
                "🕒 До конца дня:\n" .
                implode(
                    "\n",
                    array_slice(
                        $todayRows,
                        0,
                        self::MAX_HOURLY_INTERVALS_PER_DAY,
                    ),
                );
        }

        if ($tomorrowRows !== []) {
            $sections[] = sprintf(
                "📅 Завтра, %s:\n%s",
                $tomorrowStartLocal->format("d.m"),
                implode(
                    "\n",
                    array_slice(
                        $tomorrowRows,
                        0,
                        self::MAX_HOURLY_INTERVALS_PER_DAY,
                    ),
                ),
            );
        }

        return $sections;
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

    private function formatTemperature(float|int|string $temperature): string
    {
        $value = (float) $temperature;
        $formatted = number_format($value, 1, ".", "");

        if ($value > 0) {
            return '<span style="color:#d97706;">+' . $formatted . "°C</span>";
        }

        if ($value < 0) {
            return '<span style="color:#2563eb;">' . $formatted . "°C</span>";
        }

        return '<span style="color:#6b7280;">' . $formatted . "°C</span>";
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
}
