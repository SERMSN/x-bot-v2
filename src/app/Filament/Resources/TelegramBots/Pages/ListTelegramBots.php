<?php

namespace App\Filament\Resources\TelegramBots\Pages;

use App\Filament\Resources\TelegramBots\TelegramBotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelegramBots extends ListRecords
{
    protected static string $resource = TelegramBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
