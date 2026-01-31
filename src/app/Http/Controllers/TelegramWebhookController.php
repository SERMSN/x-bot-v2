<?php

namespace App\Http\Controllers;

use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Telegraph;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\Handlers\Handler;

class TelegramWebhookController extends Controller
{
    public function handleWebhook(Request $request, string $bot_token): JsonResponse
    {
        try {
            Log::info('📨 Входящий вебхук от Telegram', [
                'token' => substr($bot_token, 0, 10) . '...',
            ]);
            
            $bot = TelegraphBot::where('token', $bot_token)->first();
            
            if (!$bot) {
                Log::error('❌ Бот не найден', ['token' => $bot_token]);
                return response()->json(['error' => 'Bot not found'], 404);
            }
            
            Log::info('✅ Бот найден', [
                'id' => $bot->id,
                'name' => $bot->name,
                'handler_class' => $bot->handler_class,
            ]);
            
            $handler = app(Handler::class);
            $handler->handle($request, $bot);
            
            Log::info('✅ Вебхук успешно обработан');

            return response()->json(['ok' => true]);
            
        } catch (\Exception $e) {
            Log::error('💥 Ошибка обработки вебхука', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['ok' => true]);
        }
    }
}