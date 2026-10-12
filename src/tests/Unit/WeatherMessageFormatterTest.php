<?php

namespace Tests\Unit;

use App\Services\Telegram\Services\WeatherMessageFormatter;
use PHPUnit\Framework\TestCase;

class WeatherMessageFormatterTest extends TestCase
{
    public function test_today_card_contains_current_weather_and_compact_hourly_rows(): void
    {
        $text = (new WeatherMessageFormatter())->formatToday($this->payload());

        $this->assertStringContainsString("Погода в Красноярске", $text);
        $this->assertStringContainsString("Сегодня, по часам", $text);
        $this->assertStringContainsString("18:00", $text);
        $this->assertStringContainsString("+5.0°C", $text);
    }

    public function test_three_day_card_contains_daily_forecast_and_handles_missing_data(): void
    {
        $formatter = new WeatherMessageFormatter();
        $text = $formatter->formatThreeDays($this->payload());

        $this->assertStringContainsString("Прогноз на 3 дня", $text);
        $this->assertStringContainsString("Завтра", $text);
        $this->assertStringContainsString("дождь", $text);

        $missingForecast = $formatter->formatThreeDays([
            ...$this->payload(),
            "daily_forecast" => [],
        ]);

        $this->assertStringContainsString("Прогноз временно недоступен", $missingForecast);
    }

    private function payload(): array
    {
        return [
            "city_name" => "Красноярске",
            "temperature_unit" => "C",
            "wind_speed_unit" => "м/с",
            "current" => [
                "temp" => 4.5,
                "description" => "облачно",
                "humidity" => 70,
                "wind_speed" => 2.1,
            ],
            "forecast_sections" => [
                [
                    "period" => "today",
                    "rows" => [
                        ["time" => "18:00", "temp" => 5],
                    ],
                ],
            ],
            "daily_forecast" => [
                [
                    "label" => "Завтра",
                    "temp_min" => 1,
                    "temp_max" => 8,
                    "description" => "дождь",
                ],
            ],
        ];
    }
}