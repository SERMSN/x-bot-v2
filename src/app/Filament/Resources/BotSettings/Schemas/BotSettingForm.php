<?php

namespace App\Filament\Resources\BotSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BotSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make("key")->label("Ключ")->required()->maxLength(255),
            TextInput::make("name")
                ->label("Название")
                ->required()
                ->maxLength(255),
            TextInput::make("value")
                ->label("Значение")
                ->required()
                ->maxLength(255),
            Select::make("telegraph_bot_id")
                ->label("Бот")
                ->relationship("bot", "name")
                ->searchable()
                ->preload()
                ->required(),
        ]);
    }
}
