<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\VinMiniAppController;

// Вебхук по токену бота
Route::post("/telegram/{token}", [
    TelegramWebhookController::class,
    "handleWebhook",
])
    ->where("token", "[A-Za-z0-9:_\\-]+")
    ->name("webhook.telegram.token");

Route::get("/vin-mini-app", [VinMiniAppController::class, "index"])
    ->name("vin-mini-app");

Route::post("/vin-mini-app", [VinMiniAppController::class, "purchase"])
    ->name("vin-mini-app.purchase");
