<?php

return [
    "handlers" => [
        "weather" => \App\Services\Telegram\Handlers\WeatherBotHandler::class,
        "vin" => \App\Services\Telegram\Handlers\VinBotHandler::class,
        "divination" =>
            \App\Services\Telegram\Handlers\DivinationBotHandler::class,
    ],
];
