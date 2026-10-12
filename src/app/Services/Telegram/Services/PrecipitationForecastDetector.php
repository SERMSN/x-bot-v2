<?php

namespace App\Services\Telegram\Services;

class PrecipitationForecastDetector
{
    public function findUpcomingEvent(
        array $forecastItems,
        int $leadMinutes,
        int $nowTimestamp,
        ?int $lastNotifiedTimestamp = null,
    ): ?array {
        $leadMinutes = in_array($leadMinutes, [30, 60, 120, 180], true)
            ? $leadMinutes
            : 60;
        $latestStartTimestamp = $nowTimestamp + ($leadMinutes * 60);
        $previousTimestamp = null;
        $previousType = null;

        foreach ($forecastItems as $item) {
            if (!is_array($item) || !isset($item["dt"])) {
                continue;
            }

            $timestamp = (int) $item["dt"];
            $type = $this->precipitationType($item);
            $isNewEvent = $type !== null && (
                $previousType === null ||
                $previousTimestamp === null ||
                $timestamp - $previousTimestamp > 4 * 60 * 60
            );

            if (
                $isNewEvent &&
                $timestamp > $nowTimestamp &&
                ($lastNotifiedTimestamp === null || $timestamp > $lastNotifiedTimestamp) &&
                $timestamp <= $latestStartTimestamp
            ) {
                return [
                    "timestamp" => $timestamp,
                    "type" => $type,
                    "probability" => isset($item["pop"])
                        ? (int) round((float) $item["pop"] * 100)
                        : null,
                    "amount_mm" => $this->precipitationAmount($item),
                    "description" => (string) ($item["weather"][0]["description"] ?? ""),
                ];
            }

            $previousTimestamp = $timestamp;
            $previousType = $type;
        }

        return null;
    }

    private function precipitationType(array $item): ?string
    {
        $weather = $item["weather"][0] ?? [];
        $condition = mb_strtolower((string) ($weather["main"] ?? ""));
        $rainAmount = (float) ($item["rain"]["3h"] ?? 0);
        $snowAmount = (float) ($item["snow"]["3h"] ?? 0);

        if (
            $snowAmount > 0 ||
            str_contains($condition, "snow") ||
            str_contains($condition, "снег")
        ) {
            return "снег";
        }

        if (
            $rainAmount > 0 ||
            str_contains($condition, "rain") ||
            str_contains($condition, "drizzle") ||
            str_contains($condition, "thunderstorm") ||
            str_contains($condition, "дожд") ||
            str_contains($condition, "морос") ||
            str_contains($condition, "гроза")
        ) {
            return "дождь";
        }

        if ((float) ($item["pop"] ?? 0) >= 0.5) {
            return "осадки";
        }

        return null;
    }

    private function precipitationAmount(array $item): ?float
    {
        $amount = (float) ($item["rain"]["3h"] ?? 0) +
            (float) ($item["snow"]["3h"] ?? 0);

        return $amount > 0 ? $amount : null;
    }
}