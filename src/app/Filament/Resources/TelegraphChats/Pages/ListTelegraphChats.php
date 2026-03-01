<?php

namespace App\Filament\Resources\TelegraphChats\Pages;

use App\Filament\Resources\TelegraphChats\TelegraphChatResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegraphChats extends ListRecords
{
    protected static string $resource = TelegraphChatResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
