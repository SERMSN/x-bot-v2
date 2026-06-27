<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = "subscription_plans";

    protected $fillable = [
        "telegraph_bot_id",
        "name",
        "report_count",
        "price_rub",
        "description",
        "is_active",
        "sort_order",
    ];

    protected $casts = [
        "report_count" => "integer",
        "price_rub" => "decimal:2",
        "is_active" => "boolean",
        "sort_order" => "integer",
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, "telegraph_bot_id");
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where("is_active", true);
    }
}
