<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphChat as BaseTelegraphChat;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegraphChat extends BaseTelegraphChat
{
    // Используем таблицу от Telegraph
    protected $table = "telegraph_chats";

    protected $fillable = ["chat_id", "name", "telegraph_bot_id"];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, "telegraph_bot_id");
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ChatLog::class, "telegraph_chat_id");
    }
}
