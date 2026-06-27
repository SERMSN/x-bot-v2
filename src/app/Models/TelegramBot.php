<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramBot extends TelegraphBot
{
    protected $table = "telegraph_bots";

    protected $fillable = ["name", "token", "handler_class"];

    protected $casts = [
        "settings" => "array",
    ];

    public function botSettings(): HasMany
    {
        return $this->hasMany(BotSetting::class, "telegraph_bot_id");
    }

    public function chatLogs(): HasMany
    {
        return $this->hasMany(ChatLog::class, "telegraph_bot_id");
    }

    public function subscriptionPlans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class, "telegraph_bot_id");
    }

    public function subscriptionTransactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class, "telegraph_bot_id");
    }

    /**
     * Получить класс хендлера для этого бота
     */
    /*  public function getHandlerClass(): ?string
    {
        // Если класс явно указан в БД - используем его
        if ($this->handler_class && class_exists($this->handler_class)) {
            return $this->handler_class;
        }
    }*/
}
