<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologistEvent extends Model
{
    protected $table = "psychologist_events";

    protected $fillable = [
        "psychologist_profile_id",
        "event_type",
        "meta",
    ];

    protected $casts = [
        "meta" => "array",
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PsychologistProfile::class, "psychologist_profile_id");
    }
}
