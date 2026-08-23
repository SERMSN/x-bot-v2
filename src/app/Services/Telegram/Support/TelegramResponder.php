<?php

namespace App\Services\Telegram\Support;

use App\Services\Telegram\ChatLogger;

class TelegramResponder
{
    public function __construct(private readonly ChatLogger $logger) {}

    public function logOutgoing(BotContext $context, ?string $message, array $meta = []): void
    {
        $this->logger->logOutbound(
            $context->botId(),
            $context->chatModelId(),
            $context->telegramChatId(),
            $message,
            $meta,
        );
    }

    public function ackCallback(BotContext $context, ?string $callbackQueryId, string $message = ""): void
    {
        if ($callbackQueryId === null || $callbackQueryId === "") {
            return;
        }

        $context->bot->replyWebhook($callbackQueryId, $message)->send();
    }
}
