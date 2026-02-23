<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;

// Вебхук по токену бота
Route::post("/telegram/{bot_token}", [
    TelegramWebhookController::class,
    "handleWebhook",
])
    ->where("bot_token", "[A-Za-z0-9:_\-]+")
    ->name("webhook.telegram.token");
