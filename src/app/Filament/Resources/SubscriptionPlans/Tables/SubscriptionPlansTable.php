<?php

namespace App\Filament\Resources\SubscriptionPlans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort("sort_order")
            ->columns([
                TextColumn::make("name")
                    ->label("Название")
                    ->searchable()
                    ->sortable(),
                TextColumn::make("bot.name")
                    ->label("Бот")
                    ->searchable()
                    ->sortable()
                    ->placeholder("Общий"),
                TextColumn::make("report_count")
                    ->label("Отчетов")
                    ->numeric()
                    ->sortable(),
                TextColumn::make("price_rub")
                    ->label("Цена, ₽")
                    ->sortable()
                    ->formatStateUsing(
                        fn ($state) => number_format((float) $state, 0, ",", " ") . " ₽",
                    ),
                TextColumn::make("is_active")
                    ->label("Активен")
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? "Да" : "Нет")
                    ->sortable(),
                TextColumn::make("sort_order")
                    ->label("Сортировка")
                    ->sortable(),
                TextColumn::make("created_at")
                    ->label("Создано")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make("updated_at")
                    ->label("Обновлено")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make("telegraph_bot_id")
                    ->label("Бот")
                    ->relationship("bot", "name"),
                SelectFilter::make("is_active")
                    ->label("Статус")
                    ->options([
                        1 => "Да",
                        0 => "Нет",
                    ]),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
