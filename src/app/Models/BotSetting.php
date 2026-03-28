<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class BotSetting extends Model
{
    use HasFactory;

    public const KEY_OPENWEATHER_API_KEY = "OPENWEATHER_API_KEY";
    public const KEY_OPENWEATHER_API_URL = "OPENWEATHER_API_URL";
    public const KEY_WEATHER_NOTIFICATION_RUN_INTERVAL_MINUTES = "WEATHER_NOTIFICATION_RUN_INTERVAL_MINUTES";
    public const KEY_VIN_API_DECODE_URL = "VIN_API_DECODE_URL";

    protected $table = "bot_settings";

    protected $fillable = ["key", "name", "telegraph_bot_id", "value"];

    protected static function booted(): void
    {
        $flushCache = function (self $setting): void {
            $botId = $setting->telegraph_bot_id;
            if ($botId) {
                Cache::forget("bot_settings_openweather_{$botId}");
                Cache::forget("vin_settings_{$botId}");
                Cache::forget("weather_notification_settings_{$botId}");
            }
        };

        static::saved($flushCache);
        static::deleted($flushCache);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, "telegraph_bot_id");
    }
}
