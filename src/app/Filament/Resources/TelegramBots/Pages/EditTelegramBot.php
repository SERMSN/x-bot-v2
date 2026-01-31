<?php

namespace App\Filament\Resources\TelegramBots\Pages;

use App\Filament\Resources\TelegramBots\TelegramBotResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelegramBot extends EditRecord
{
    protected static string $resource = TelegramBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
