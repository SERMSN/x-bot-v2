<?php

namespace App\Services\Telegram\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VinService
{
    public function decode(int $botId, string $vin): array
    {
        $cacheKey = "vin_decode_{$botId}_" . strtoupper($vin);
        $ttl = (int) config("vin.cache.ttl_minutes", 1440);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes($ttl),
            function () use ($botId, $vin) {
                $url = rtrim($this->getDecodeUrl($botId), "/");
                $timeout = (int) config("vin.api.timeout_seconds", 8);
                $retries = (int) config("vin.api.retries", 2);
                $retrySleep = (int) config("vin.api.retry_sleep_ms", 200);

                $response = Http::timeout($timeout)
                    ->retry($retries, $retrySleep)
                    ->get($url . "/" . urlencode($vin), [
                        "format" => "json",
                    ]);

                if (!$response->successful()) {
                    Log::warning("NHTSA decode VIN request failed", [
                        "status" => $response->status(),
                    ]);
                    throw new \RuntimeException("VIN API request failed");
                }

                $payload = $response->json();
                $row = $payload["Results"][0] ?? null;

                if (!is_array($row)) {
                    throw new \RuntimeException("VIN API invalid response");
                }

                return $this->mapResponse($vin, $row);
            },
        );
    }

    private function getDecodeUrl(int $botId): string
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
                    ->where("key", BotSetting::KEY_VIN_API_DECODE_URL)
                    ->value("value");
            },
        );

        $url = trim((string) $settings);
        if ($url === "") {
            throw new \RuntimeException(
                "VIN_API_DECODE_URL is not configured in bot_settings",
            );
        }

        return $url;
    }

    private function mapResponse(string $vin, array $row): array
    {
        return [
            "vin" => $vin,
            "make" => $this->value($row, "Make"),
            "model" => $this->value($row, "Model"),
            "model_year" => $this->value($row, "ModelYear"),
            "vehicle_type" => $this->value($row, "VehicleType"),
            "body_class" => $this->value($row, "BodyClass"),
            "engine_cylinders" => $this->value($row, "EngineCylinders"),
            "engine_liters" => $this->value($row, "DisplacementL"),
            "fuel_type" => $this->value($row, "FuelTypePrimary"),
            "plant_country" => $this->value($row, "PlantCountry"),
            "plant_company" => $this->value($row, "PlantCompanyName"),
            "error_code" => $this->value($row, "ErrorCode"),
            "error_text" => $this->value($row, "ErrorText"),
        ];
    }

    private function value(array $row, string $key): string
    {
        $value = trim((string) ($row[$key] ?? ""));
        return $value !== "" ? $value : "-";
    }
}
