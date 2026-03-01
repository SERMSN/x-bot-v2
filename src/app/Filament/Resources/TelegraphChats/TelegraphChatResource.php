<?php

namespace App\Filament\Resources\TelegraphChats;

use App\Filament\Resources\TelegraphChats\Pages\ListTelegraphChats;
use App\Filament\Resources\TelegraphChats\Pages\ViewTelegraphChat;
use App\Filament\Resources\TelegraphChats\Schemas\TelegraphChatForm;
use App\Filament\Resources\TelegraphChats\Schemas\TelegraphChatInfolist;
use App\Filament\Resources\TelegraphChats\Tables\TelegraphChatsTable;
use App\Models\TelegraphChat;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelegraphChatResource extends Resource
{
    protected static ?string $model = TelegraphChat::class;

    protected static string|BackedEnum|null $navigationIcon = "heroicon-o-chat-bubble-left-right";

    protected static ?string $navigationLabel = "Чаты ботов";

    protected static ?int $navigationSort = 200;

    protected static ?string $modelLabel = "Чат";

    protected static ?string $pluralModelLabel = "Чаты";

    public static function form(Schema $schema): Schema
    {
        return TelegraphChatForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelegraphChatInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelegraphChatsTable::configure($table);
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
            "index" => ListTelegraphChats::route("/"),
            "view" => ViewTelegraphChat::route("/{record}"),
        ];
    }
}
