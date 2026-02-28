<?php

namespace App\Filament\Resources\ChatLogs;

use App\Filament\Resources\ChatLogs\Pages\ListChatLogs;
use App\Filament\Resources\ChatLogs\Pages\ViewChatLog;
use App\Filament\Resources\ChatLogs\Schemas\ChatLogInfolist;
use App\Filament\Resources\ChatLogs\Tables\ChatLogsTable;
use App\Models\ChatLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ChatLogResource extends Resource
{
    protected static ?string $model = ChatLog::class;

    protected static string|BackedEnum|null $navigationIcon = "heroicon-o-clipboard-document-list";

    protected static ?string $navigationLabel = "Логи чатов";

    protected static ?int $navigationSort = 350;

    protected static ?string $modelLabel = "Лог чата";

    protected static ?string $pluralModelLabel = "Логи чатов";

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ChatLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChatLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            "index" => ListChatLogs::route("/"),
            "view" => ViewChatLog::route("/{record}"),
        ];
    }
}
