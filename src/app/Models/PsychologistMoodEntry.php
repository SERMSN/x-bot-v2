<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologistMoodEntry extends Model
{
    protected $table = "psychologist_mood_entries";

    protected $fillable = [
        "psychologist_profile_id",
        "score",
        "tags",
    ];

    protected $casts = [
        "score" => "integer",
        "tags" => "array",
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PsychologistProfile::class, "psychologist_profile_id");
    }
}
