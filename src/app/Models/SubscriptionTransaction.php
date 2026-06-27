<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionTransaction extends Model
{
    use HasFactory;

    protected $table = "subscription_transactions";

    protected $fillable = [
        "telegraph_bot_id",
        "telegraph_chat_id",
        "subscription_plan_id",
        "transaction_type",
        "reports_before",
        "reports_delta",
        "reports_after",
        "amount_rub",
        "status",
        "comment",
        "meta",
    ];

    protected $casts = [
        "reports_before" => "integer",
        "reports_delta" => "integer",
        "reports_after" => "integer",
        "amount_rub" => "decimal:2",
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, "subscription_plan_id");
    }
}
