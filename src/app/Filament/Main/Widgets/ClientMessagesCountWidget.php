<?php

namespace App\Filament\Main\Widgets;

use App\Models\ChatLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientMessagesCountWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $count = ChatLog::query()
            ->where("direction", "in")
            ->whereIn("event_type", ["message", "command", "callback"])
            ->count();

        return [
            Stat::make("Сообщений от клиентов", (string) $count)
                ->description("Входящие сообщения, команды и нажатия кнопок")
                ->icon("heroicon-o-paper-airplane"),
        ];
    }
}
