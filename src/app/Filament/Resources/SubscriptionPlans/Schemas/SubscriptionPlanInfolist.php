<?php

namespace App\Filament\Resources\SubscriptionPlans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SubscriptionPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make("name")->label("Название"),
            TextEntry::make("bot.name")->label("Бот")->placeholder("Общий"),
            TextEntry::make("report_count")->label("Количество отчетов"),
            TextEntry::make("price_rub")
                ->label("Цена, ₽")
                ->formatStateUsing(
                    fn ($state) => number_format((float) $state, 0, ",", " ") . " ₽",
                ),
            TextEntry::make("description")->label("Описание")->placeholder("-"),
            TextEntry::make("is_active")
                ->label("Активен")
                ->formatStateUsing(fn ($state) => $state ? "Да" : "Нет"),
            TextEntry::make("sort_order")->label("Сортировка"),
            TextEntry::make("created_at")
                ->label("Создано")
                ->dateTime()
                ->placeholder("-"),
            TextEntry::make("updated_at")
                ->label("Обновлено")
                ->dateTime()
                ->placeholder("-"),
        ]);
    }
}
