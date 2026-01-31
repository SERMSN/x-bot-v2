<?php

namespace App\Filament\Resources\TelegraphChats\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelegraphChatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('chat_id')
                    ->required(),
                TextInput::make('name'),
                TextInput::make('telegraph_bot_id')
                    ->tel()
                    ->required()
                    ->numeric(),
            ]);
    }
}
