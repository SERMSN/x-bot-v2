<?php

namespace App\Filament\Resources\BotSettings\Schemas;

use App\Models\BotSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BotSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make("key")
                ->label("Ключ")
                ->options([
                    BotSetting::KEY_OPENWEATHER_API_KEY =>
                        "OpenWeather API key",
                    BotSetting::KEY_OPENWEATHER_API_URL =>
                        "OpenWeather API URL",
                    BotSetting::KEY_WEATHER_NOTIFICATION_RUN_INTERVAL_MINUTES =>
                        "Проверка уведомлений: интервал в минутах",
                    BotSetting::KEY_VIN_API_DECODE_URL => "VIN API decode URL",
                ])
                ->searchable()
                ->native(false)
                ->required(),
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
