<?php

namespace App\Filament\Resources\ChatLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ChatLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make("created_at")
                ->label("Время")
                ->dateTime(),
            TextEntry::make("bot.name")
                ->label("Бот")
                ->placeholder("-"),
            TextEntry::make("chat.chat_id")
                ->label("Чат")
                ->placeholder("-"),
            TextEntry::make("direction")
                ->label("Направление")
                ->badge(),
            TextEntry::make("event_type")
                ->label("Событие")
                ->badge(),
            TextEntry::make("update_id")
                ->label("Update ID")
                ->placeholder("-"),
            TextEntry::make("telegram_user_id")
                ->label("Telegram User ID")
                ->placeholder("-"),
            TextEntry::make("command")
                ->label("Команда")
                ->placeholder("-"),
            TextEntry::make("callback_action")
                ->label("Кнопка/Action")
                ->placeholder("-"),
            TextEntry::make("message_text")
                ->label("Текст")
                ->placeholder("-"),
            TextEntry::make("meta")
                ->label("Meta")
                ->formatStateUsing(
                    fn (mixed $state): string => is_array($state)
                        ? json_encode(
                            $state,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        ) ?: "-"
                        : (string) ($state ?? "-"),
                ),
        ]);
    }
}
