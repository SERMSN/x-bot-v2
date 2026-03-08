<?php

namespace App\Filament\Main\Widgets;

use App\Models\TelegraphChat;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChatsCountWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make(
                "Чатов в системе",
                (string) TelegraphChat::query()->count(),
            )
                ->description("Чаты, подключенные к ботам")
                ->icon("heroicon-o-chat-bubble-left-right"),
        ];
    }
}
