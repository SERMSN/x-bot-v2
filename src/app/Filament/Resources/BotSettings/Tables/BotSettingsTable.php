<?php

namespace App\Filament\Resources\BotSettings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BotSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("key")
                    ->label("Ключ")
                    ->searchable(),
                TextColumn::make("name")
                    ->label("Название")
                    ->searchable(),
                TextColumn::make("volue")
                    ->label("Значение")
                    ->searchable(),
                TextColumn::make("bot.name")
                    ->label("Бот")
                    ->searchable()
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
