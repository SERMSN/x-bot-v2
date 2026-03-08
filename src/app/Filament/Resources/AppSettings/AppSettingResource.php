<?php

namespace App\Filament\Resources\AppSettings;

use App\Filament\Resources\AppSettings\Pages\ManageAppSettings;
use App\Models\AppSetting;
use BackedEnum;
use Filament\Resources\Resource;
use UnitEnum;

class AppSettingResource extends Resource
{
    protected static ?string $model = AppSetting::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = "Настройки";

    protected static ?string $navigationLabel = "Настройка приложения";

    protected static ?int $navigationSort = 350;

    protected static ?string $modelLabel = "Настройка приложения";

    protected static ?string $pluralModelLabel = "Настройки приложения";

    public static function getPages(): array
    {
        return [
            "index" => ManageAppSettings::route("/"),
        ];
    }
}
