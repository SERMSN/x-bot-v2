<?php

namespace App\Filament\Resources\TelegraphChats\Pages;

use App\Filament\Resources\TelegraphChats\TelegraphChatResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelegraphChat extends EditRecord
{
    protected static string $resource = TelegraphChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
