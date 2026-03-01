<?php

namespace App\Services\Telegram\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;

class DivinationBotHandler extends WebhookHandler
{
    public function start(): void
    {
        $message = "🔮 *Divination бот*\n\n";
        $message .= "Привет! Я бот-оракул. Напишите сообщение, и я поприветствую вас.";

        $this->chat->markdown($message)->send();
    }

    protected function handleChatMessage(Stringable $text): void
    {
        if ($text->startsWith("/")) {
            parent::handleChatMessage($text);
            return;
        }

        $this->start();
    }
}
