<?php

namespace App\Filament\Resources\TelegramBots;

use App\Filament\Resources\TelegramBots\Pages\CreateTelegramBot;
use App\Filament\Resources\TelegramBots\Pages\EditTelegramBot;
use App\Filament\Resources\TelegramBots\Pages\ListTelegramBots;
use App\Filament\Resources\TelegramBots\Pages\ViewTelegramBot;
use App\Filament\Resources\TelegramBots\Schemas\TelegramBotForm;
use App\Filament\Resources\TelegramBots\Schemas\TelegramBotInfolist;
use App\Filament\Resources\TelegramBots\Tables\TelegramBotsTable;
use App\Models\TelegramBot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelegramBotResource extends Resource
{
    protected static ?string $model = TelegramBot::class;

    protected static string|BackedEnum|null $navigationIcon = "heroicon-o-chat-bubble-left";

    protected static ?string $navigationLabel = "Боты";

    protected static ?int $navigationSort = 100;

    protected static ?string $modelLabel = "Бот";

    protected static ?string $pluralModelLabel = "Боты";

    public static function form(Schema $schema): Schema
    {
        return TelegramBotForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelegramBotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelegramBotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
                //
            ];
    }

    public static function getPages(): array
    {
        return [
            "index" => ListTelegramBots::route("/"),
            "create" => CreateTelegramBot::route("/create"),
            "view" => ViewTelegramBot::route("/{record}"),
            "edit" => EditTelegramBot::route("/{record}/edit"),
        ];
    }
}
