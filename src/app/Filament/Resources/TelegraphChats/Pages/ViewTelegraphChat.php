<?php

namespace App\Filament\Resources\TelegraphChats\Pages;

use App\Filament\Resources\TelegraphChats\TelegraphChatResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegraphChat extends ViewRecord
{
    protected static string $resource = TelegraphChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
