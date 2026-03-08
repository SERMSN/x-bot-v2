<?php

namespace App\Filament\Main\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BotsShowcaseWidget extends StatsOverviewWidget
{
    protected ?string $heading = "Сайт отобранных Telegram-ботов";
    protected ?string $description = "Три полезных бота в одном месте: погода, VIN и предсказания.";
    protected int|string|array $columnSpan = "full";

    protected function getStats(): array
    {
        return [
            Stat::make("", "🌤️ Погода")->description(
                "Погода и краткий прогноз по городу.",
            ),
            Stat::make("", "🚗 VIN проверка")->description(
                "Проверка VIN-кода и удобные ответы в Telegram.",
            ),
            Stat::make("", "🔮 Оракул")->description(
                "Короткие предсказания и подсказки для настроения.",
            ),
        ];
    }
}
