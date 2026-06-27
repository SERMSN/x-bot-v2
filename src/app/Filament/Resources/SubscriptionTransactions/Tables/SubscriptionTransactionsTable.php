<?php

namespace App\Filament\Resources\SubscriptionTransactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionTransactionsTable
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
                TextColumn::make("transaction_type")
                    ->label("Тип")
                    ->badge()
                    ->sortable(),
                TextColumn::make("status")
                    ->label("Статус")
                    ->badge()
                    ->sortable(),
                TextColumn::make("bot.name")
                    ->label("Бот")
                    ->searchable()
                    ->sortable()
                    ->placeholder("-"),
                TextColumn::make("chat.name")
                    ->label("Чат")
                    ->searchable()
                    ->sortable()
                    ->placeholder("-"),
                TextColumn::make("plan.name")
                    ->label("План")
                    ->searchable()
                    ->sortable()
                    ->placeholder("-"),
                TextColumn::make("reports_before")
                    ->label("До")
                    ->numeric()
                    ->sortable(),
                TextColumn::make("reports_delta")
                    ->label("Изменение")
                    ->numeric()
                    ->sortable(),
                TextColumn::make("reports_after")
                    ->label("После")
                    ->numeric()
                    ->sortable(),
                TextColumn::make("amount_rub")
                    ->label("Сумма, ₽")
                    ->sortable()
                    ->placeholder("-"),
            ])
            ->filters([
                SelectFilter::make("telegraph_bot_id")
                    ->label("Бот")
                    ->relationship("bot", "name"),
                SelectFilter::make("telegraph_chat_id")
                    ->label("Чат")
                    ->relationship("chat", "name"),
                SelectFilter::make("subscription_plan_id")
                    ->label("План")
                    ->relationship("plan", "name"),
                SelectFilter::make("transaction_type")
                    ->label("Тип")
                    ->options([
                        "purchase_simulated" => "purchase_simulated",
                        "credit" => "credit",
                    ]),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
