<?php

namespace App\Filament\Resources\BotSettings;

use App\Filament\Resources\BotSettings\Pages\CreateBotSetting;
use App\Filament\Resources\BotSettings\Pages\EditBotSetting;
use App\Filament\Resources\BotSettings\Pages\ListBotSettings;
use App\Filament\Resources\BotSettings\Pages\ViewBotSetting;
use App\Filament\Resources\BotSettings\Schemas\BotSettingForm;
use App\Filament\Resources\BotSettings\Schemas\BotSettingInfolist;
use App\Filament\Resources\BotSettings\Tables\BotSettingsTable;
use App\Models\BotSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BotSettingResource extends Resource
{
    protected static ?string $model = BotSetting::class;

    protected static string|BackedEnum|null $navigationIcon = "heroicon-o-cog-6-tooth";

    protected static ?string $navigationLabel = "Настройки";

    protected static ?int $navigationSort = 300;

    protected static ?string $modelLabel = "Настройка";

    protected static ?string $pluralModelLabel = "Настройки";

    public static function form(Schema $schema): Schema
    {
        return BotSettingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BotSettingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BotSettingsTable::configure($table);
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
            "index" => ListBotSettings::route("/"),
            "create" => CreateBotSetting::route("/create"),
            "view" => ViewBotSetting::route("/{record}"),
            "edit" => EditBotSetting::route("/{record}/edit"),
        ];
    }
}
