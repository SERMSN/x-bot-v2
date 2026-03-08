<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLog extends Model
{
    protected $table = "chat_logs";

    public const UPDATED_AT = null;

    protected $fillable = [
        "telegraph_bot_id",
        "telegraph_chat_id",
        "direction",
        "event_type",
        "update_id",
        "telegram_chat_id",
        "telegram_user_id",
        "command",
        "callback_action",
        "message_text",
        "meta",
    ];

    protected $casts = [
        "meta" => "array",
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, "telegraph_bot_id");
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegraphChat::class, "telegraph_chat_id");
    }
}
