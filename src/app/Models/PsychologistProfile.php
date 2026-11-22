<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PsychologistProfile extends Model
{
    protected $table = "psychologist_profiles";

    protected $fillable = [
        "telegraph_bot_id",
        "telegraph_chat_id",
        "preferred_name",
        "style",
        "consent_at",
        "last_active_at",
    ];

    protected $casts = [
        "consent_at" => "datetime",
        "last_active_at" => "datetime",
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, "telegraph_bot_id");
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegraphChat::class, "telegraph_chat_id");
    }

    public function memory(): HasOne
    {
        return $this->hasOne(PsychologistMemory::class, "psychologist_profile_id");
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PsychologistSession::class, "psychologist_profile_id");
    }

    public function moodEntries(): HasMany
    {
        return $this->hasMany(PsychologistMoodEntry::class, "psychologist_profile_id");
    }
}
