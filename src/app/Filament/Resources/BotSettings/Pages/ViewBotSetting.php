<?php

namespace App\Filament\Resources\BotSettings\Pages;

use App\Filament\Resources\BotSettings\BotSettingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBotSetting extends ViewRecord
{
    protected static string $resource = BotSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
