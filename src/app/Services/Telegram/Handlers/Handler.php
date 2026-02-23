<?php

namespace App\Services\Telegram\Handlers;

use DefStudio\Telegraph\Handlers\EmptyWebhookHandler;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Http\Request;

class Handler extends WebhookHandler
{
    protected array $handlers = [
        "weather" => WeatherBotHandler::class,
        "vin" => VinBotHandler::class,
    ];

    public function handle(Request $request, TelegraphBot $bot): void
    {
        $handler = $this->getHandler($bot->handler_class);
        $handlerInstance = app()->make($handler);
        $handlerInstance->handle($request, $bot);
    }

    protected function getHandler(?string $botName): string
    {
        if (!$botName) {
            return EmptyWebhookHandler::class;
        }

        return $this->handlers[$botName] ?? EmptyWebhookHandler::class;
    }
}
