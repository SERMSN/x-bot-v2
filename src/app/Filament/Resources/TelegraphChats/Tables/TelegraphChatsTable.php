<?php

namespace App\Filament\Resources\TelegraphChats\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelegraphChatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                return $query->withCount([
                    "logs as inbound_messages_count" => function ($logQuery) {
                        $logQuery->where("direction", "in");
                    },
                ]);
            })
            ->columns([
                TextColumn::make("chat_id")->label("Chat ID")->searchable(),
                TextColumn::make("name")->label("Название")->searchable(),
                TextColumn::make("weather_city")
                    ->label("Город")
                    ->searchable()
                    ->placeholder("-"),
                TextColumn::make("bot.name")
                    ->label("Бот")
                    ->searchable()
                    ->sortable(),
                TextColumn::make("inbound_messages_count")
                    ->label("Входящих сообщений")
                    ->numeric()
                    ->sortable(),
                TextColumn::make("created_at")
                    ->label("Создан")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make("updated_at")
                    ->label("Обновлен")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
