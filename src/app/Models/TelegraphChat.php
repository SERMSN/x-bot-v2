<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphChat as BaseTelegraphChat;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegraphChat extends BaseTelegraphChat
{
    // Используем таблицу от Telegraph
    protected $table = "telegraph_chats";

    protected $fillable = [
        "chat_id",
        "name",
        "telegraph_bot_id",
        "weather_city",
        "weather_city_lat",
        "weather_city_lon",
        "weather_response_mode",
        "weather_units",
        "weather_auto_save_location_city",
        "last_weather_query_type",
        "last_weather_query_city",
        "last_weather_query_lat",
        "last_weather_query_lon",
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, "telegraph_bot_id");
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ChatLog::class, "telegraph_chat_id");
    }
}
