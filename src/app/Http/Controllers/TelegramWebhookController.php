<?php

namespace App\Http\Controllers;

use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Telegraph;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\Handlers\Handler;

class TelegramWebhookController extends Controller
{
    public function handleWebhook(Request $request, string $token): JsonResponse
    {
        try {
            $expectedSecret = config("telegraph.webhook.secret");
            if ($expectedSecret) {
                $providedSecret = $request->header(
                    "X-Telegram-Bot-Api-Secret-Token",
                );
                if (!$providedSecret || $providedSecret !== $expectedSecret) {
                    Log::warning("🚫 Неверный секрет вебхука Telegram", [
                        "token_hash" => substr(sha1($token), 0, 12),
                    ]);
                    return response()->json(["error" => "Forbidden"], 403);
                }
            }

            $updateId = $request->input("update_id");
            $chatId =
                $request->input("message.chat.id") ??
                $request->input("callback_query.message.chat.id");
            $fromId =
                $request->input("message.from.id") ??
                $request->input("callback_query.from.id");

            Log::info("📨 Входящий вебхук от Telegram", [
                "token" => substr($token, 0, 10) . "...",
                "update_id" => $updateId,
                "chat_id" => $chatId,
                "from_id" => $fromId,
            ]);

            $bot = TelegraphBot::where("token", $token)->first();

            if (!$bot) {
                Log::error("❌ Бот не найден", ["token" => $token]);
                return response()->json(["error" => "Bot not found"], 404);
            }

            if ($updateId !== null) {
                $cacheKey = "telegram:webhook:bot:{$bot->id}:update:{$updateId}";
                $isNew = Cache::add(
                    $cacheKey,
                    true,
                    now()->addMinutes(
                        (int) config("telegram.webhook.dedup_ttl_minutes", 10),
                    ),
                );

                if (!$isNew) {
                    Log::info("🔁 Дубликат update_id, пропуск", [
                        "bot_id" => $bot->id,
                        "update_id" => $updateId,
                    ]);
                    return response()->json(["ok" => true]);
                }
            }

            Log::info("✅ Бот найден", [
                "id" => $bot->id,
                "name" => $bot->name,
                "handler_class" => $bot->handler_class,
            ]);

            $handler = app(Handler::class);
            $handler->handle($request, $bot);

            Log::info("✅ Вебхук успешно обработан");

            return response()->json(["ok" => true]);
        } catch (\Throwable $e) {
            // Логируем ошибку, но не раскрываем полный токен бота
            Log::error("💥 Ошибка обработки вебхука", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
                "token_hash" => isset($token)
                    ? substr(sha1($token), 0, 12)
                    : null,
            ]);

            // Возвращаем 500, чтобы внешние сервисы (Telegram) знали, что произошла ошибка
            return response()->json(["error" => "Internal server error"], 500);
        }
    }
}
