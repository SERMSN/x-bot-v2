<?php

namespace App\Filament\Resources\TelegraphChats\Tables;

use App\Filament\Resources\ChatLogs\ChatLogResource;
use App\Filament\Resources\SubscriptionTransactions\SubscriptionTransactionResource;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TelegraphChatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                return $query
                    ->withCount([
                        "logs as inbound_messages_count" => function ($logQuery) {
                            $logQuery->where("direction", "in");
                        },
                    ])
                    ->withMax(
                        [
                            "logs as last_activity_at" => function ($logQuery) {
                                $logQuery->where("direction", "in");
                            },
                        ],
                        "created_at",
                    );
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
                    ->sortable()
                    ->url(function ($record) {
                        return ChatLogResource::getUrl("index", [
                            "filters" => [
                                "telegraph_bot_id" => [
                                    "value" => $record->telegraph_bot_id,
                                ],
                                "telegraph_chat_id" => [
                                    "value" => $record->id,
                                ],
                                "direction" => [
                                    "value" => "in",
                                ],
                            ],
                        ]);
                    }),
                TextColumn::make("last_activity_at")
                    ->label("Последняя активность")
                    ->dateTime()
                    ->sortable()
                    ->placeholder("-"),
                TextColumn::make("full_reports_remaining")
                    ->label("VIN отчеты")
                    ->numeric()
                    ->sortable()
                    ->placeholder("-"),
                TextColumn::make("id")
                    ->label("История подписок")
                    ->formatStateUsing(fn ($state) => "Открыть")
                    ->url(function ($record) {
                        $baseUrl = SubscriptionTransactionResource::getUrl("index");

                        return $baseUrl .
                            "?" .
                            http_build_query([
                                "filters" => [
                                    "telegraph_bot_id" => [
                                        "value" => $record->telegraph_bot_id,
                                    ],
                                    "telegraph_chat_id" => [
                                        "value" => $record->id,
                                    ],
                                ],
                            ]);
                    }),
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
                SelectFilter::make("telegraph_bot_id")
                    ->label("Бот")
                    ->relationship("bot", "name"),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
