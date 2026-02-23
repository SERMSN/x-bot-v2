<?php

namespace App\Services\Telegram\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    public function getByCoordinates(float $lat, float $lon): array
    {
        $cacheKey = "weather_{$lat}_{$lon}";

        return Cache::remember($cacheKey, now()->addHour(), function () use (
            $lat,
            $lon,
        ) {
            $response = Http::get(
                "https://api.openweathermap.org/data/2.5/weather",
                [
                    "lat" => $lat,
                    "lon" => $lon,
                    "appid" => config("services.openweather.key"),
                    "units" => "metric",
                    "lang" => "ru",
                ],
            );

            if (!$response->successful()) {
                throw new \Exception("API request failed");
            }

            return $this->formatWeatherData($response->json());
        });
    }

    public function validateCity(string $city): array
    {
        // Приводим к нормализованному виду (первая буква заглавная, остальные строчные)
        $normalizedCity = ucfirst(strtolower(trim($city)));

        $response = Http::get("http://api.openweathermap.org/geo/1.0/direct", [
            "q" => $normalizedCity,
            "limit" => 5, // Увеличиваем лимит для лучшего поиска
            "appid" => config("services.openweather.key"),
        ]);

        if (!$response->successful() || empty($response->json())) {
            throw new \Exception("Город не найден");
        }

        // Ищем точное совпадение (не учитываем регистр)
        $data = $response->json();
        foreach ($data as $cityData) {
            if (strtolower($cityData["name"]) === strtolower($city)) {
                return [
                    "name" => $cityData["name"],
                    "country" => $cityData["country"],
                    "normalized" => "{$cityData["name"]}, {$cityData["country"]}",
                ];
            }
        }

        // Если точного совпадения нет, берем первый результат
        $firstResult = $data[0];
        return [
            "name" => $firstResult["name"],
            "country" => $firstResult["country"],
            "normalized" => "{$firstResult["name"]}, {$firstResult["country"]}",
        ];
    }

    protected function formatWeatherData(array $data): array
    {
        $cityName = $this->declineCityName($data["name"]);

        return [
            "text" => sprintf(
                "🌦️ <b>Погода в %s</b>\n\n" .
                    "🌡 Температура: <b>%.1f°C</b>\n" .
                    "☁️ Состояние: <b>%s</b>\n" .
                    "💧 Влажность: <b>%d%%</b>\n" .
                    "🌬 Ветер: <b>%.1f м/с</b>",
                $cityName,
                $data["main"]["temp"],
                $data["weather"][0]["description"],
                $data["main"]["humidity"],
                $data["wind"]["speed"],
            ),
            "image" => "https://openweathermap.org/img/wn/{$data["weather"][0]["icon"]}@2x.png",
        ];
    }

    public function getByCityName(string $cityName): array
    {
        $cacheKey = "weather_city_" . strtolower($cityName);

        return Cache::remember($cacheKey, now()->addHour(), function () use (
            $cityName,
        ) {
            $response = Http::get(
                "https://api.openweathermap.org/data/2.5/weather",
                [
                    "q" => $cityName,
                    "appid" => config("services.openweather.key"),
                    "units" => "metric",
                    "lang" => "ru",
                ],
            );

            if (!$response->successful()) {
                throw new \Exception("API request failed");
            }

            return $this->formatWeatherData($response->json());
        });
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
