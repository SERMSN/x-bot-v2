<?php

namespace App\Services\Telegram\Conversations;

use App\Models\TelegraphChat;
use App\Services\Telegram\Handlers\WeatherBotHandler;
use App\Services\Telegram\Support\BotContext;
use Illuminate\Support\Facades\Cache;

class WeatherConversationService
{
    public function resolveChatModel(BotContext $context): ?TelegraphChat
    {
        $telegramChatId = $context->telegramChatId();

        if ($telegramChatId === null) {
            return null;
        }

        return TelegraphChat::query()->firstOrCreate(
            [
                "telegraph_bot_id" => $context->botId(),
                "chat_id" => $telegramChatId,
            ],
            [
                "name" => $context->chat?->name ?? null,
            ],
        );
    }

    public function getSavedCity(BotContext $context): ?array
    {
        $chat = $this->resolveChatModel($context);
        if ($chat === null) {
            return null;
        }

        $savedCity = $chat->weather_city;
        if (
            !is_string($savedCity) ||
            $savedCity === "" ||
            !is_numeric($chat->weather_city_lat) ||
            !is_numeric($chat->weather_city_lon)
        ) {
            return null;
        }

        return [
            "name" => $savedCity,
            "lat" => (float) $chat->weather_city_lat,
            "lon" => (float) $chat->weather_city_lon,
            "timezone" => $this->normalizeNotificationTimezone($chat->weather_notification_timezone),
        ];
    }

    public function getSavedCityName(BotContext $context): ?string
    {
        $savedCity = $this->getSavedCity($context);
        return $savedCity["name"] ?? null;
    }

    public function saveCity(BotContext $context, string $city, float $lat, float $lon, ?string $timezone = null): void
    {
        $chat = $this->resolveChatModel($context);
        if ($chat === null) {
            return;
        }

        $chat->weather_city = $city;
        $chat->weather_city_lat = $lat;
        $chat->weather_city_lon = $lon;

        $normalizedTimezone = $this->normalizeNotificationTimezone($timezone);
        if ($normalizedTimezone !== null) {
            $chat->weather_notification_timezone = $normalizedTimezone;
        }

        $chat->save();
    }

    public function getPreferences(BotContext $context): array
    {
        $chat = $this->resolveChatModel($context);

        return [
            "response_mode" => $this->normalizeWeatherMode($chat?->weather_response_mode),
            "units" => $this->normalizeWeatherUnits($chat?->weather_units),
            "notifications_enabled" => $chat === null ? false : (bool) ($chat->weather_notifications_enabled ?? false),
            "notification_time" => is_string($chat?->weather_notification_time) ? $chat->weather_notification_time : "08:00",
            "notification_timezone" => is_string($chat?->weather_notification_timezone) ? $chat->weather_notification_timezone : "не определен",
            "notification_mode" => $this->normalizeNotificationMode($chat?->weather_notification_mode),
        ];
    }

    public function isAutoSaveLocationCityEnabled(BotContext $context): bool
    {
        $chat = $this->resolveChatModel($context);

        return $chat === null ? true : (bool) ($chat->weather_auto_save_location_city ?? true);
    }

    public function isWeatherNotificationsEnabled(BotContext $context): bool
    {
        $chat = $this->resolveChatModel($context);

        return $chat === null ? false : (bool) ($chat->weather_notifications_enabled ?? false);
    }

    public function setAwaitingSavedCity(BotContext $context): void
    {
        $key = $this->awaitingSavedCityCacheKey($context);
        if ($key !== null) {
            Cache::put($key, true, now()->addMinutes(10));
        }
    }

    public function clearAwaitingSavedCity(BotContext $context): void
    {
        $key = $this->awaitingSavedCityCacheKey($context);
        if ($key !== null) {
            Cache::forget($key);
        }

        $this->clearAwaitingNotificationTime($context);
    }

    public function isAwaitingSavedCity(BotContext $context): bool
    {
        $key = $this->awaitingSavedCityCacheKey($context);
        return $key !== null && (bool) Cache::get($key, false);
    }

    public function setAwaitingNotificationTime(BotContext $context): void
    {
        $key = $this->awaitingNotificationTimeCacheKey($context);
        if ($key !== null) {
            Cache::put($key, true, now()->addMinutes(10));
        }
    }

    public function clearAwaitingNotificationTime(BotContext $context): void
    {
        $key = $this->awaitingNotificationTimeCacheKey($context);
        if ($key !== null) {
            Cache::forget($key);
        }
    }

    public function isAwaitingNotificationTime(BotContext $context): bool
    {
        $key = $this->awaitingNotificationTimeCacheKey($context);
        return $key !== null && (bool) Cache::get($key, false);
    }

    public function rememberLastWeatherQuery(
        BotContext $context,
        string $type,
        float $lat,
        float $lon,
        ?string $city = null,
    ): void {
        $chat = $this->resolveChatModel($context);
        if ($chat === null) {
            return;
        }

        $chat->last_weather_query_type = $type;
        $chat->last_weather_query_city = $city;
        $chat->last_weather_query_lat = $lat;
        $chat->last_weather_query_lon = $lon;
        $chat->save();
    }

    public function getLastWeatherQuery(BotContext $context): ?array
    {
        $chat = $this->resolveChatModel($context);
        if (
            $chat === null ||
            $chat->last_weather_query_type !== "coordinates" ||
            !is_numeric($chat->last_weather_query_lat) ||
            !is_numeric($chat->last_weather_query_lon)
        ) {
            return null;
        }

        return [
            "type" => (string) $chat->last_weather_query_type,
            "city" => is_string($chat->last_weather_query_city) ? $chat->last_weather_query_city : null,
            "lat" => (float) $chat->last_weather_query_lat,
            "lon" => (float) $chat->last_weather_query_lon,
        ];
    }

    public function normalizeWeatherMode(mixed $mode): string
    {
        return in_array($mode, ["brief", "detailed"], true) ? (string) $mode : "detailed";
    }

    public function normalizeWeatherUnits(mixed $units): string
    {
        return in_array($units, ["metric", "imperial"], true) ? (string) $units : "metric";
    }

    public function normalizeNotificationMode(mixed $mode): string
    {
        return in_array($mode, ["brief", "detailed"], true) ? (string) $mode : "brief";
    }

    public function normalizeNotificationTimezone(mixed $timezone): ?string
    {
        if (!is_string($timezone) || trim($timezone) === "") {
            return null;
        }

        try {
            $normalized = trim($timezone);
            new \DateTimeZone($normalized);
            return $normalized;
        } catch (\Throwable) {
            return null;
        }
    }

    private function awaitingSavedCityCacheKey(BotContext $context): ?string
    {
        $telegramChatId = $context->telegramChatId();
        return $telegramChatId === null ? null : "weather_bot_saved_city_pending_{$context->botId()}_{$telegramChatId}";
    }

    private function awaitingNotificationTimeCacheKey(BotContext $context): ?string
    {
        $telegramChatId = $context->telegramChatId();
        return $telegramChatId === null ? null : "weather_bot_notification_time_pending_{$context->botId()}_{$telegramChatId}";
    }
}
