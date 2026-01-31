<?php

namespace App\Filament\Resources\TelegramBots\Pages;

use App\Filament\Resources\TelegramBots\TelegramBotResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramBot extends ViewRecord
{
    protected static string $resource = TelegramBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
