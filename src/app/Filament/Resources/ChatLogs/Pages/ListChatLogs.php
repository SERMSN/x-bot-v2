<?php

namespace App\Filament\Resources\ChatLogs\Pages;

use App\Filament\Resources\ChatLogs\ChatLogResource;
use Filament\Resources\Pages\ListRecords;

class ListChatLogs extends ListRecords
{
    protected static string $resource = ChatLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
