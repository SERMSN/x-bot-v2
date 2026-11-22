<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologistMemory extends Model
{
    protected $table = "psychologist_memories";

    protected $fillable = [
        "psychologist_profile_id",
        "summary",
        "goals",
        "topics",
        "last_summarized_at",
    ];

    protected $casts = [
        "summary" => "encrypted",
        "goals" => "encrypted:array",
        "topics" => "encrypted:array",
        "last_summarized_at" => "datetime",
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PsychologistProfile::class, "psychologist_profile_id");
    }
}
