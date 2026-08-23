<?php

namespace App\Filament\Resources\TelegramBots\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TelegramBotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('token')
                    ->formatStateUsing(fn ($state) => self::maskToken((string) $state)),
                TextEntry::make('name')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    private static function maskToken(string $token): string
    {
        if ($token === "") {
            return "";
        }

        return substr($token, 0, 6) . "..." . substr($token, -4);
    }
}
