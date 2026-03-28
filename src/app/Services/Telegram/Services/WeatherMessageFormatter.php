<?php

namespace App\Services\Telegram\Services;

class WeatherMessageFormatter
{
    public function format(array $payload): string
    {
        $text = sprintf(
            "🌦️ <b>Погода в %s</b>\n\n" .
                "🌡 Температура: <b>%s</b>\n" .
                "☁️ Состояние: <b>%s</b>\n" .
                "💧 Влажность: <b>%d%%</b>\n" .
                "🌬 Ветер: <b>%.1f м/с</b>",
            $this->escapeHtml((string) $payload["city_name"]),
            $this->formatTemperature($payload["current"]["temp"] ?? 0),
            $this->escapeHtml((string) ($payload["current"]["description"] ?? "")),
            (int) ($payload["current"]["humidity"] ?? 0),
            (float) ($payload["current"]["wind_speed"] ?? 0),
        );

        $forecastSections = $payload["forecast_sections"] ?? [];
        if (is_array($forecastSections) && $forecastSections !== []) {
            $text .= "\n\n" . $this->formatForecastSections($forecastSections);
        }

        $text .= sprintf(
            "\n\n🕒 <b>Местное время</b>\n%s",
            $this->escapeHtml((string) $payload["local_time"]),
        );

        return $text;
    }

    private function formatForecastSections(array $sections): string
    {
        $formattedSections = [];

        foreach ($sections as $section) {
            $title = $this->escapeHtml((string) ($section["title"] ?? ""));
            $rows = $section["rows"] ?? [];

            if ($title === "" || !is_array($rows) || $rows === []) {
                continue;
            }

            $formattedRows = [];
            foreach ($rows as $row) {
                $formattedRows[] = sprintf(
                    "%s - <b>%s</b>",
                    $this->escapeHtml((string) ($row["time"] ?? "")),
                    $this->formatTemperature($row["temp"] ?? 0),
                );
            }

            if ($formattedRows === []) {
                continue;
            }

            $formattedSections[] = $title . "\n" . implode("\n", $formattedRows);
        }

        return implode("\n\n", $formattedSections);
    }

    private function formatTemperature(float|int|string $temperature): string
    {
        $value = (float) $temperature;
        $formatted = number_format($value, 1, ".", "");

        if ($value > 0) {
            return "➕" . $formatted . "°C";
        }

        if ($value < 0) {
            return "➖" . number_format(abs($value), 1, ".", "") . "°C";
        }

        return $formatted . "°C";
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }
}
