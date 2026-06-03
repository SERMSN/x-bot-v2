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
