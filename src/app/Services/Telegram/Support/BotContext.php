<?php

namespace App\Services\Telegram\Support;

use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;

class BotContext
{
    public function __construct(
        public readonly TelegraphBot $bot,
        public readonly ?TelegraphChat $chat,
    ) {}

    public function botId(): int
    {
        return (int) $this->bot->id;
    }

    public function chatModelId(): ?int
    {
        return isset($this->chat?->id) ? (int) $this->chat->id : null;
    }

    public function telegramChatId(): ?string
    {
        return isset($this->chat?->chat_id) ? (string) $this->chat->chat_id : null;
    }
}
