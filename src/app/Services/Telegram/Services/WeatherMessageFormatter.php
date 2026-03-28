<?php

namespace App\Services\Telegram\Services;

class WeatherMessageFormatter
{
    public function format(array $payload): string
    {
        $mode = (string) ($payload["response_mode"] ?? "detailed");
        $text =
            $mode === "brief"
                ? $this->formatBrief($payload)
                : $this->formatDetailed($payload);

        $dailyForecast = $payload["daily_forecast"] ?? [];
        if (is_array($dailyForecast) && $dailyForecast !== []) {
            $text .=
                "\n\n" . $this->formatDailyForecast($dailyForecast, $payload);
        }

        if ($mode === "detailed") {
            $forecastSections = $payload["forecast_sections"] ?? [];
            if (is_array($forecastSections) && $forecastSections !== []) {
                $text .=
                    "\n\n" .
                    $this->formatForecastSections($forecastSections, $payload);
            }

            $text .= sprintf(
                "\n\n🕒 <b>Местное время</b>\n%s",
                $this->escapeHtml((string) $payload["local_time"]),
            );
        }

        return $text;
    }

    private function formatDetailed(array $payload): string
    {
        return sprintf(
            "🌦️ <b>Погода в %s</b>\n\n" .
                "🌡 Температура: <b>%s</b>\n" .
                "☁️ Состояние: <b>%s</b>\n" .
                "💧 Влажность: <b>%d%%</b>\n" .
                "🌬 Ветер: <b>%s</b>",
            $this->escapeHtml((string) $payload["city_name"]),
            $this->formatTemperature(
                $payload["current"]["temp"] ?? 0,
                (string) ($payload["temperature_unit"] ?? "C"),
            ),
            $this->escapeHtml(
                (string) ($payload["current"]["description"] ?? ""),
            ),
            (int) ($payload["current"]["humidity"] ?? 0),
            $this->formatWind(
                $payload["current"]["wind_speed"] ?? 0,
                (string) ($payload["wind_speed_unit"] ?? "м/с"),
            ),
        );
    }

    private function formatBrief(array $payload): string
    {
        return sprintf(
            "🌦️ <b>%s</b>\n🌡 <b>%s</b>, %s\n🌬 %s",
            $this->escapeHtml((string) $payload["city_name"]),
            $this->formatTemperature(
                $payload["current"]["temp"] ?? 0,
                (string) ($payload["temperature_unit"] ?? "C"),
            ),
            $this->escapeHtml(
                (string) ($payload["current"]["description"] ?? ""),
            ),
            $this->formatWind(
                $payload["current"]["wind_speed"] ?? 0,
                (string) ($payload["wind_speed_unit"] ?? "м/с"),
            ),
        );
    }

    private function formatForecastSections(
        array $sections,
        array $payload,
    ): string {
        $formattedSections = [];

        foreach ($sections as $section) {
            $title = $this->escapeHtml((string) ($section["title"] ?? ""));
            $rows = $section["rows"] ?? [];

            if ($title === "" || !is_array($rows) || $rows === []) {
                continue;
            }

            $formattedRows = [];
            foreach ($rows as $row) {
                $formattedRows[] = $this->formatMetricLine(
                    (string) ($row["time"] ?? ""),
                    $row["temp"] ?? 0,
                    (string) ($payload["temperature_unit"] ?? "C"),
                );
            }

            if ($formattedRows === []) {
                continue;
            }

            $formattedSections[] =
                $title . "\n<pre>" . implode("\n", $formattedRows) . "</pre>";
        }

        return implode("\n\n", $formattedSections);
    }

    private function formatDailyForecast(
        array $dailyForecast,
        array $payload,
    ): string {
        $rows = [];

        foreach ($dailyForecast as $day) {
            $rows[] = sprintf(
                "%s  %s / %s  %s",
                $this->padLabel((string) ($day["label"] ?? ""), 10),
                $this->padLabel(
                    $this->formatTemperature(
                        $day["temp_min"] ?? 0,
                        (string) ($payload["temperature_unit"] ?? "C"),
                    ),
                    7,
                ),
                $this->padLabel(
                    $this->formatTemperature(
                        $day["temp_max"] ?? 0,
                        (string) ($payload["temperature_unit"] ?? "C"),
                    ),
                    7,
                ),
                (string) ($day["description"] ?? ""),
            );
        }

        return "📅 <b>Прогноз на 3 дня</b>\n<pre>" .
            $this->escapeHtml(implode("\n", $rows)) .
            "</pre>";
    }

    private function formatTemperature(
        float|int|string $temperature,
        string $temperatureUnit,
    ): string {
        $value = (float) $temperature;
        $formatted = number_format($value, 1, ".", "");
        $suffix = "°" . $temperatureUnit;

        if ($value > 0) {
            return "+" . $formatted . $suffix;
        }

        if ($value < 0) {
            return "-" . number_format(abs($value), 1, ".", "") . $suffix;
        }

        return $formatted . $suffix;
    }

    private function formatMetricLine(
        string $label,
        float|int|string $temperature,
        string $temperatureUnit,
    ): string {
        return $this->padLabel($label, 8) .
            " " .
            $this->formatTemperature($temperature, $temperatureUnit);
    }

    private function padLabel(string $label, int $length): string
    {
        $label = trim($label);
        $width = mb_strwidth($label, "UTF-8");

        if ($width >= $length) {
            return $label;
        }

        return $label . str_repeat(" ", $length - $width);
    }

    private function formatWind(float|int|string $speed, string $unit): string
    {
        return number_format((float) $speed, 1, ".", "") . " " . $unit;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }
}
