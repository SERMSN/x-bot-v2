<?php

namespace App\Filament\Resources\ChatLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChatLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort("created_at", "desc")
            ->columns([
                TextColumn::make("created_at")
                    ->label("Время")
                    ->dateTime()
                    ->sortable(),
                TextColumn::make("bot.name")
                    ->label("Бот")
                    ->searchable()
                    ->sortable()
                    ->placeholder("-"),
                TextColumn::make("chat.name")
                    ->label("Чат")
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        $name = trim((string) ($state ?? ""));
                        if ($name !== "") {
                            return $name;
                        }

                        return trim((string) ($record?->chat?->chat_id ?? "-")) ?: "-";
                    })
                    ->placeholder("-"),
                TextColumn::make("direction")
                    ->label("Направление")
                    ->badge()
                    ->sortable(),
                TextColumn::make("event_type")
                    ->label("Событие")
                    ->badge()
                    ->sortable(),
                TextColumn::make("command")
                    ->label("Команда")
                    ->searchable()
                    ->placeholder("-"),
                TextColumn::make("callback_action")
                    ->label("Кнопка/Action")
                    ->searchable()
                    ->placeholder("-"),
                TextColumn::make("message_text")
                    ->label("Текст")
                    ->searchable()
                    ->limit(90)
                    ->placeholder("-"),
            ])
            ->filters([
                SelectFilter::make("telegraph_bot_id")
                    ->label("Бот")
                    ->relationship("bot", "name"),
                SelectFilter::make("telegraph_chat_id")
                    ->label("Чат")
                    ->relationship("chat", "name"),
                SelectFilter::make("direction")
                    ->label("Направление")
                    ->options([
                        "in" => "in",
                        "out" => "out",
                    ]),
                SelectFilter::make("event_type")
                    ->label("Событие")
                    ->options([
                        "update" => "update",
                        "message" => "message",
                        "command" => "command",
                        "callback" => "callback",
                        "response" => "response",
                    ]),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
