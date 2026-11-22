<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologistSession extends Model
{
    protected $table = "psychologist_sessions";

    protected $fillable = [
        "psychologist_profile_id",
        "started_at",
        "ended_at",
        "message_count",
        "user_messages",
        "assistant_messages",
        "input_tokens",
        "output_tokens",
        "safety_flags",
        "status",
    ];

    protected $casts = [
        "started_at" => "datetime",
        "ended_at" => "datetime",
        "message_count" => "integer",
        "user_messages" => "integer",
        "assistant_messages" => "integer",
        "input_tokens" => "integer",
        "output_tokens" => "integer",
        "safety_flags" => "integer",
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PsychologistProfile::class, "psychologist_profile_id");
    }
}
