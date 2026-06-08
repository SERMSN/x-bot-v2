<?php

namespace App\Services\Telegram\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VinService
{
    private const OPERATION_ID = "decode";
    private const RESPONSE_FORMAT = "json";

    public function decode(int $botId, string $vin): array
    {
        $vin = strtoupper($vin);
        $cacheKey = "vin_decode_{$botId}_{$vin}";
        $ttl = (int) config("vin.cache.ttl_minutes", 1440);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes($ttl),
            function () use ($botId, $vin) {
                $settings = $this->getApiSettings($botId);
                $url = $this->buildDecodeUrl($settings, $vin);
                $timeout = (int) config("vin.api.timeout_seconds", 8);
                $retries = (int) config("vin.api.retries", 2);
                $retrySleep = (int) config("vin.api.retry_sleep_ms", 200);

                $response = Http::timeout($timeout)
                    ->retry($retries, $retrySleep)
                    ->acceptJson()
                    ->get($url);

                if (!$response->successful()) {
                    Log::warning("Vincario decode VIN request failed", [
                        "status" => $response->status(),
                    ]);
                    throw new \RuntimeException("VIN API request failed");
                }

                $payload = $response->json();
                if (!is_array($payload)) {
                    throw new \RuntimeException("VIN API invalid response");
                }

                return $this->mapResponse($vin, $payload);
            },
        );
    }

    private function getApiSettings(int $botId): array
    {
        $cacheKey = "vin_settings_{$botId}";

        $settings = Cache::remember(
            $cacheKey,
            now()->addMinutes(
                (int) config("vin.cache.settings_ttl_minutes", 10),
            ),
            function () use ($botId) {
                return BotSetting::query()
                    ->where("telegraph_bot_id", $botId)
                    ->whereIn("key", [
                        BotSetting::KEY_VIN_API_BASE_URL,
                        BotSetting::KEY_VIN_API_KEY,
                        BotSetting::KEY_VIN_API_SECRET_KEY,
                        BotSetting::KEY_VIN_API_DECODE_URL,
                    ])
                    ->pluck("value", "key")
                    ->all();
            },
        );

        $baseUrl = trim(
            (string) ($settings[BotSetting::KEY_VIN_API_BASE_URL] ??
                $settings[BotSetting::KEY_VIN_API_DECODE_URL] ??
                ""),
        );
        $apiKey = trim((string) ($settings[BotSetting::KEY_VIN_API_KEY] ?? ""));
        $secretKey = trim(
            (string) ($settings[BotSetting::KEY_VIN_API_SECRET_KEY] ?? ""),
        );

        if ($baseUrl === "") {
            throw new \RuntimeException(
                "VIN_API_BASE_URL is not configured in bot_settings",
            );
        }

        if ($apiKey === "") {
            throw new \RuntimeException(
                "VIN_API_KEY is not configured in bot_settings",
            );
        }

        if ($secretKey === "") {
            throw new \RuntimeException(
                "VIN_API_SECRET_KEY is not configured in bot_settings",
            );
        }

        return [
            "base_url" => $baseUrl,
            "api_key" => $apiKey,
            "secret_key" => $secretKey,
        ];
    }

    private function buildDecodeUrl(array $settings, string $vin): string
    {
        $baseUrl = rtrim((string) $settings["base_url"], "/");
        $apiKey = (string) $settings["api_key"];
        $secretKey = (string) $settings["secret_key"];
        $controlSum = substr(
            sha1("{$vin}|" . self::OPERATION_ID . "|{$apiKey}|{$secretKey}"),
            0,
            10,
        );

        return sprintf(
            "%s/%s/%s/%s/%s.%s",
            $baseUrl,
            rawurlencode($apiKey),
            $controlSum,
            self::OPERATION_ID,
            rawurlencode($vin),
            self::RESPONSE_FORMAT,
        );
    }

    private function mapResponse(string $vin, array $payload): array
    {
        $values = $this->decodeValues($payload);

        return [
            "vin" => $this->value($values, "VIN", $vin),
            "make" => $this->value($values, "Make"),
            "model" => $this->value($values, "Model"),
            "model_year" => $this->value($values, "Model Year"),
            "vehicle_type" => $this->value($values, "Product Type"),
            "body_class" => $this->value($values, "Body"),
            "engine_cylinders" => $this->value($values, "Engine Type"),
            "engine_liters" => $this->engineLiters($values),
            "fuel_type" => $this->value($values, "Fuel Type - Primary"),
            "plant_country" => $this->value($values, "Plant Country"),
            "plant_company" => $this->value($values, "Manufacturer"),
            "error_code" => $this->errorCode($payload),
            "error_text" => $this->errorText($payload),
            "sections" => $this->sections($values),
        ];
    }

    private function sections(array $values): array
    {
        return [
            "Основное" => [
                "VIN" => $this->value($values, "VIN"),
                "Марка" => $this->value($values, "Make"),
                "Модель" => $this->value($values, "Model"),
                "Год модели" => $this->value($values, "Model Year"),
                "Тип ТС" => $this->value($values, "Product Type"),
                "Кузов" => $this->value($values, "Body"),
                "Привод" => $this->value($values, "Drive"),
            ],
            "Двигатель и трансмиссия" => [
                "Двигатель" => $this->value($values, "Engine Type"),
                "Объем двигателя" => $this->withValueUnit(
                    $this->engineLiters($values),
                    "л",
                ),
                "Объем двигателя, ccm" => $this->value(
                    $values,
                    "Engine Displacement (ccm)",
                ),
                "Производитель двигателя" => $this->value(
                    $values,
                    "Engine Manufacturer",
                ),
                "Крутящий момент" => $this->withUnit(
                    $values,
                    "Engine Torque (RPM)",
                    "RPM",
                ),
                "Топливо" => $this->value($values, "Fuel Type - Primary"),
                "Коробка" => $this->value($values, "Transmission"),
                "Передач" => $this->value($values, "Number of Gears"),
                "Экостандарт" => $this->value($values, "Emission Standard"),
                "Расход смешанный" => $this->withUnit(
                    $values,
                    "Fuel Consumption Combined (l/100km)",
                    "л/100 км",
                ),
                "CO2" => $this->withUnit($values, "CO2 Emission (g/km)", "г/км"),
            ],
            "Производитель" => [
                "Производитель" => $this->value($values, "Manufacturer"),
                "Адрес производителя" => $this->value(
                    $values,
                    "Manufacturer Address",
                ),
                "Страна сборки" => $this->value($values, "Plant Country"),
            ],
            "Кузов и размеры" => [
                "Дверей" => $this->value($values, "Number of Doors"),
                "Мест" => $this->value($values, "Number of Seats"),
                "Колес" => $this->value($values, "Number Wheels"),
                "Осей" => $this->value($values, "Number of Axles"),
                "Колесная база" => $this->withUnit($values, "Wheelbase (mm)", "мм"),
                "Высота" => $this->withUnit($values, "Height (mm)", "мм"),
                "Длина" => $this->withUnit($values, "Length (mm)", "мм"),
                "Ширина" => $this->withUnit($values, "Width (mm)", "мм"),
                "Задний свес" => $this->withUnit($values, "Rear Overhang (mm)", "мм"),
                "Колея передняя" => $this->withUnit($values, "Track Front (mm)", "мм"),
                "Колея задняя" => $this->withUnit($values, "Track Rear (mm)", "мм"),
            ],
            "Масса и эксплуатация" => [
                "Макс. скорость" => $this->withUnit($values, "Max Speed (km/h)", "км/ч"),
                "Масса пустого" => $this->withUnit($values, "Weight Empty (kg)", "кг"),
                "Макс. масса" => $this->withUnit($values, "Max Weight (kg)", "кг"),
                "Макс. нагрузка на крышу" => $this->withUnit(
                    $values,
                    "Max roof load (kg)",
                    "кг",
                ),
                "Прицеп без тормозов" => $this->withUnit(
                    $values,
                    "Permitted trailer load without brakes (kg)",
                    "кг",
                ),
            ],
            "Ходовая и оснащение" => [
                "ABS" => $this->boolValue($values, "ABS"),
                "Передние тормоза" => $this->value($values, "Front Brakes"),
                "Тормозная система" => $this->value($values, "Brake System"),
                "Подвеска" => $this->value($values, "Suspension"),
                "Рулевое управление" => $this->value($values, "Steering Type"),
                "Диски" => $this->value($values, "Wheel Rims Size"),
                "Шины" => $this->value($values, "Wheel Size"),
            ],
            "VIN служебные данные" => [
                "Vehicle ID" => $this->value($values, "Vehicle ID"),
                "Контрольная цифра" => $this->value($values, "Check Digit"),
                "Серийный номер" => $this->value($values, "Sequential Number"),
            ],
        ];
    }

    private function decodeValues(array $payload): array
    {
        $decode = $payload["decode"] ?? null;
        if (!is_array($decode)) {
            throw new \RuntimeException("VIN API decode section is missing");
        }

        $values = [];

        foreach ($decode as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item["label"] ?? ""));
            if ($label === "") {
                continue;
            }

            $values[$label] = $this->stringValue($item["value"] ?? "");
        }

        return $values;
    }

    private function engineLiters(array $values): string
    {
        $ccm = $values["Engine Displacement (ccm)"] ?? "";
        if (is_numeric($ccm)) {
            return rtrim(rtrim(number_format(((float) $ccm) / 1000, 1, ".", ""), "0"), ".");
        }

        return $this->value($values, "Engine Displacement (l)");
    }

    private function value(array $values, string $key, string $default = "-"): string
    {
        $value = trim((string) ($values[$key] ?? ""));
        return $value !== "" ? $value : $default;
    }

    private function withUnit(array $values, string $key, string $unit): string
    {
        return $this->withValueUnit($this->value($values, $key), $unit);
    }

    private function withValueUnit(string $value, string $unit): string
    {
        return $value !== "-" ? "{$value} {$unit}" : "-";
    }

    private function boolValue(array $values, string $key): string
    {
        $value = $this->value($values, $key);
        if ($value === "1") {
            return "Да";
        }

        if ($value === "0") {
            return "Нет";
        }

        return $value;
    }

    private function errorCode(array $payload): string
    {
        if (isset($payload["error"])) {
            return "1";
        }

        return "0";
    }

    private function errorText(array $payload): string
    {
        if (!isset($payload["error"])) {
            return "-";
        }

        return $this->stringValue($payload["error"]);
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(
                ", ",
                array_filter(
                    array_map(fn($item) => $this->stringValue($item), $value),
                ),
            );
        }

        $value = trim((string) $value);
        return $value !== "" ? $value : "-";
    }
}
