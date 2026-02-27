<?php

namespace App\Filament\Main\Widgets;

use App\Models\TelegramBot;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BotsCountWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make("Ботов в системе", (string) TelegramBot::query()->count())
                ->description("Зарегистрированные Telegram-боты")
                ->icon("heroicon-o-chat-bubble-left"),
        ];
    }
}
