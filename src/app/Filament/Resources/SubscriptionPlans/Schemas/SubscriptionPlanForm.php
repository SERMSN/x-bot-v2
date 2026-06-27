<?php

namespace App\Filament\Resources\SubscriptionPlans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make("telegraph_bot_id")
                ->label("Бот")
                ->relationship("bot", "name")
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText("Пусто = общий план для всех ботов"),
            TextInput::make("name")
                ->label("Название")
                ->required()
                ->maxLength(255),
            TextInput::make("report_count")
                ->label("Количество отчетов")
                ->numeric()
                ->required()
                ->minValue(1),
            TextInput::make("price_rub")
                ->label("Цена, ₽")
                ->numeric()
                ->required()
                ->minValue(0),
            Textarea::make("description")
                ->label("Описание")
                ->rows(4)
                ->maxLength(65535)
                ->nullable(),
            Select::make("is_active")
                ->label("Активен")
                ->options([
                    1 => "Да",
                    0 => "Нет",
                ])
                ->default(true)
                ->required(),
            TextInput::make("sort_order")
                ->label("Сортировка")
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }
}
