<?php

namespace App\Filament\Resources\SubscriptionTransactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SubscriptionTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make("transaction_type")->label("Тип"),
            TextEntry::make("status")->label("Статус"),
            TextEntry::make("bot.name")->label("Бот")->placeholder("-"),
            TextEntry::make("chat.name")->label("Чат")->placeholder("-"),
            TextEntry::make("plan.name")->label("План")->placeholder("-"),
            TextEntry::make("reports_before")->label("До"),
            TextEntry::make("reports_delta")->label("Изменение"),
            TextEntry::make("reports_after")->label("После"),
            TextEntry::make("amount_rub")
                ->label("Сумма, ₽")
                ->placeholder("-"),
            TextEntry::make("comment")->label("Комментарий")->placeholder("-"),
            TextEntry::make("created_at")
                ->label("Создано")
                ->dateTime()
                ->placeholder("-"),
        ]);
    }
}
