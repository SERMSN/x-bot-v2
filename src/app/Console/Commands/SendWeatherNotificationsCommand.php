<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use App\Models\TelegraphChat;
use App\Services\Telegram\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendWeatherNotificationsCommand extends Command
{
    protected $signature = "weather:send-notifications";

    protected $description = "Send scheduled weather notifications";

    public function handle(WeatherService $weatherService): int
    {
        $chats = TelegraphChat::query()
            ->where("weather_notifications_enabled", true)
            ->whereNotNull("weather_notification_time")
            ->whereNotNull("weather_city_lat")
            ->whereNotNull("weather_city_lon")
            ->get()
            ->groupBy("telegraph_bot_id");

        /** @var Collection<int, Collection<int, TelegraphChat>> $chats */
        foreach ($chats as $botId => $botChats) {
            if (!$this->shouldProcessBot((int) $botId)) {
                Log::info("Weather notifications skipped by bot interval", [
                    "telegraph_bot_id" => (int) $botId,
                ]);
                continue;
            }

            foreach ($botChats as $chat) {
                $this->processChat($chat, $weatherService);
            }
        }

        return self::SUCCESS;
    }

    private function processChat(
        TelegraphChat $chat,
        WeatherService $weatherService,
    ): void {
        try {
            $timezone = $this->resolveTimezone($chat);
            $now = Carbon::now($timezone);
            $notificationTime = (string) $chat->weather_notification_time;
            $scheduledAt = Carbon::createFromFormat(
                "Y-m-d H:i",
                $now->format("Y-m-d") . " " . $notificationTime,
                $timezone,
            );

            if ($now->lt($scheduledAt)) {
                Log::info(
                    "Weather notification skipped before scheduled time",
                    [
                        "chat_id" => $chat->id,
                        "telegraph_bot_id" => $chat->telegraph_bot_id,
                        "timezone" => $timezone,
                        "now" => $now->format("Y-m-d H:i"),
                        "notification_time" => $notificationTime,
                    ],
                );
                return;
            }

            if (
                $chat->weather_last_notification_date !== null &&
                Carbon::parse($chat->weather_last_notification_date)->format(
                    "Y-m-d",
                ) === $now->format("Y-m-d")
            ) {
                Log::info("Weather notification skipped because already sent", [
                    "chat_id" => $chat->id,
                    "telegraph_bot_id" => $chat->telegraph_bot_id,
                    "date" => $now->format("Y-m-d"),
                ]);
                return;
            }

            $weather = $weatherService->getByCoordinates(
                (int) $chat->telegraph_bot_id,
                (float) $chat->weather_city_lat,
                (float) $chat->weather_city_lon,
                [
                    "units" => $this->normalizeUnits($chat->weather_units),
                    "response_mode" => $this->normalizeNotificationMode(
                        $chat->weather_notification_mode,
                    ),
                ],
            );

            $chat
                ->html((string) ($weather["text"] ?? "Погода недоступна."))
                ->send();

            $chat->weather_last_notification_date = $now->toDateString();
            $chat->save();

            Log::info("Weather notification sent", [
                "chat_id" => $chat->id,
                "telegraph_bot_id" => $chat->telegraph_bot_id,
                "timezone" => $timezone,
                "sent_at" => $now->format("Y-m-d H:i"),
                "notification_time" => $notificationTime,
            ]);
        } catch (\Throwable $e) {
            Log::error("Weather notification send failed", [
                "chat_id" => $chat->id,
                "telegraph_bot_id" => $chat->telegraph_bot_id,
                "exception" => $e,
            ]);
        }
    }

    private function shouldProcessBot(int $botId): bool
    {
        $intervalMinutes = $this->notificationRunIntervalMinutes($botId);
        $cacheKey = "weather_notification_last_run_bot_{$botId}";
        $lastRunAt = Cache::get($cacheKey);
        $now = now();

        if ($lastRunAt !== null) {
            $lastRun = Carbon::parse($lastRunAt);
            if ($lastRun->diffInRealMinutes($now) < $intervalMinutes) {
                return false;
            }
        }

        Cache::put($cacheKey, $now->toIso8601String(), now()->addDay());

        Log::info("Weather notifications bot processing allowed", [
            "telegraph_bot_id" => $botId,
            "interval_minutes" => $intervalMinutes,
            "processed_at" => $now->toDateTimeString(),
        ]);

        return true;
    }

    private function notificationRunIntervalMinutes(int $botId): int
    {
        $cacheKey = "weather_notification_settings_{$botId}";

        $value = Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($botId) {
                return BotSetting::query()
                    ->where("telegraph_bot_id", $botId)
                    ->where(
                        "key",
                        BotSetting::KEY_WEATHER_NOTIFICATION_RUN_INTERVAL_MINUTES,
                    )
                    ->value("value");
            },
        );

        $minutes = is_numeric($value) ? (int) $value : 1;

        return $minutes > 0 ? $minutes : 1;
    }

    private function resolveTimezone(TelegraphChat $chat): string
    {
        $timezone = $chat->weather_notification_timezone;

        if (!is_string($timezone) || $timezone === "") {
            return (string) config("app.timezone", "UTC");
        }

        try {
            new \DateTimeZone($timezone);
            return $timezone;
        } catch (\Throwable $e) {
            return (string) config("app.timezone", "UTC");
        }
    }

    private function normalizeUnits(mixed $units): string
    {
        return in_array($units, ["metric", "imperial"], true)
            ? (string) $units
            : "metric";
    }

    private function normalizeNotificationMode(mixed $mode): string
    {
        return in_array($mode, ["brief", "detailed"], true)
            ? (string) $mode
            : "brief";
    }
}
