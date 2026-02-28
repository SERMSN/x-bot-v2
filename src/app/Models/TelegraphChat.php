<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegraphChat extends Model
{
    use HasFactory;

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
