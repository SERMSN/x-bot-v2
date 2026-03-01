<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSetting extends Model
{
    use HasFactory;

    public const KEY_OPENWEATHER_API_KEY = "OPENWEATHER_API_KEY";
    public const KEY_OPENWEATHER_API_URL = "OPENWEATHER_API_URL";
    public const KEY_VIN_API_DECODE_URL = "VIN_API_DECODE_URL";

    protected $table = "bot_settings";

    protected $fillable = ["key", "name", "telegraph_bot_id", "value"];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, "telegraph_bot_id");
    }
}
