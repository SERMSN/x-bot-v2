<?php

return [
    "handlers" => [
        "weather" => \App\Services\Telegram\Handlers\WeatherBotHandler::class,
        "vin" => \App\Services\Telegram\Handlers\VinBotHandler::class,
    ],
];
