<?php

namespace App\Filament\Resources\TelegraphChats\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TelegraphChatInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make("chat_id")->label("Chat ID"),
            TextEntry::make("name")->label("Название")->placeholder("-"),
            TextEntry::make("weather_city")->label("Город")->placeholder("-"),
            TextEntry::make("bot.name")->label("Бот")->placeholder("-"),
            TextEntry::make("inbound_events_count")
                ->label("Входящих событий")
                ->state(function ($record): int {
                    return (int) $record
                        ->logs()
                        ->where("direction", "in")
                        ->count();
                }),
            TextEntry::make("created_at")
                ->label("Создан")
                ->dateTime()
                ->placeholder("-"),
            TextEntry::make("updated_at")
                ->label("Обновлен")
                ->dateTime()
                ->placeholder("-"),
        ]);
    }
}
