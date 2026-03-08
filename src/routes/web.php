<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;

// Вебхук по токену бота
Route::post("/telegram/{token}", [
    TelegramWebhookController::class,
    "handleWebhook",
])
    ->where("token", "[A-Za-z0-9:_\\-]+")
    ->name("webhook.telegram.token");
