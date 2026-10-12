<?php

namespace Tests\Unit;

use App\Services\Telegram\Services\PrecipitationForecastDetector;
use PHPUnit\Framework\TestCase;

class PrecipitationForecastDetectorTest extends TestCase
{
    public function test_it_finds_rain_start_inside_the_selected_lead_window(): void
    {
        $now = 1_800_000_000;
        $detector = new PrecipitationForecastDetector();
        $event = $detector->findUpcomingEvent([
            ["dt" => $now - 3600, "weather" => [["main" => "Clouds"]]],
            [
                "dt" => $now + 1500,
                "pop" => 0.8,
                "rain" => ["3h" => 1.2],
                "weather" => [["main" => "Rain", "description" => "небольшой дождь"]],
            ],
        ], 30, $now);

        $this->assertSame($now + 1500, $event["timestamp"]);
        $this->assertSame("дождь", $event["type"]);
        $this->assertSame(80, $event["probability"]);
        $this->assertSame(1.2, $event["amount_mm"]);
    }

    public function test_it_ignores_events_outside_the_lead_window(): void
    {
        $now = 1_800_000_000;
        $detector = new PrecipitationForecastDetector();

        $event = $detector->findUpcomingEvent([
            [
                "dt" => $now + 31 * 60,
                "weather" => [["main" => "Snow"]],
            ],
        ], 30, $now);

        $this->assertNull($event);
    }

    public function test_it_detects_snow(): void
    {
        $now = 1_800_000_000;
        $detector = new PrecipitationForecastDetector();

        $event = $detector->findUpcomingEvent([
            [
                "dt" => $now + 60 * 60,
                "snow" => ["3h" => 0.4],
                "weather" => [["main" => "Snow", "description" => "снег"]],
            ],
        ], 60, $now);

        $this->assertSame("снег", $event["type"]);
        $this->assertSame(0.4, $event["amount_mm"]);
    }

    public function test_it_detects_a_high_probability_interval_without_a_precipitation_category(): void
    {
        $now = 1_800_000_000;
        $detector = new PrecipitationForecastDetector();

        $event = $detector->findUpcomingEvent([
            [
                "dt" => $now + 30 * 60,
                "pop" => 0.6,
                "weather" => [["main" => "Clouds"]],
            ],
        ], 30, $now);

        $this->assertSame("осадки", $event["type"]);
        $this->assertSame(60, $event["probability"]);
    }

    public function test_it_skips_an_alerted_event_and_returns_the_next_new_event(): void
    {
        $now = 1_800_000_000;
        $detector = new PrecipitationForecastDetector();

        $event = $detector->findUpcomingEvent([
            ["dt" => $now + 30 * 60, "weather" => [["main" => "Rain"]]],
            ["dt" => $now + 90 * 60, "weather" => [["main" => "Clouds"]]],
            ["dt" => $now + 120 * 60, "weather" => [["main" => "Snow"]]],
        ], 180, $now, $now + 30 * 60);

        $this->assertSame($now + 120 * 60, $event["timestamp"]);
        $this->assertSame("снег", $event["type"]);
    }

    public function test_it_does_not_alert_again_for_continuing_precipitation(): void
    {
        $now = 1_800_000_000;
        $detector = new PrecipitationForecastDetector();
        $rain = ["weather" => [["main" => "Rain"]]];

        $event = $detector->findUpcomingEvent([
            ["dt" => $now - 3600, ...$rain],
            ["dt" => $now + 2 * 3600, ...$rain],
        ], 180, $now);

        $this->assertNull($event);
    }
}