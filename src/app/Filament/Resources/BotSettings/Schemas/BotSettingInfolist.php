<?php

namespace App\Filament\Resources\BotSettings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BotSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make("key")->label("Ключ"),
            TextEntry::make("name")->label("Название"),
            TextEntry::make("value")->label("Значение"),
            TextEntry::make("bot.name")->label("Бот")->placeholder("-"),
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
