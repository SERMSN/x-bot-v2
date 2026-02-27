<?php

namespace App\Services\Telegram\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VinService
{
    public function decode(string $vin): array
    {
        $cacheKey = "vin_decode_" . strtoupper($vin);
        $ttl = (int) config("vin.cache.ttl_minutes", 1440);

        return Cache::remember($cacheKey, now()->addMinutes($ttl), function () use (
            $vin,
        ) {
            $url = rtrim((string) config("vin.api.decode_url"), "/");
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
        });
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
