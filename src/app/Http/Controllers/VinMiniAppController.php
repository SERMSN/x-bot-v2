<?php

namespace App\Http\Controllers;

use App\Models\TelegramBot;
use App\Models\TelegraphChat;
use App\Services\Telegram\Services\VinSubscriptionService;
use Illuminate\Http\Request;

class VinMiniAppController extends Controller
{
    public function index(Request $request)
    {
        $botId = $request->integer("bot_id");
        $plans = $botId > 0
            ? app(VinSubscriptionService::class)->getSubscriptionPlans($botId)
            : collect();

        $selectedPlanId = $request->integer("plan_id");

        return view("vin-mini-app", [
            "plans" => $plans,
            "reportsRemaining" => 0,
            "selectedPlanId" => $selectedPlanId > 0 ? $selectedPlanId : null,
        ]);
    }

    public function context(Request $request)
    {
        $data = $request->validate([
            "bot_id" => ["required", "integer", "min:1"],
            "chat_id" => ["required", "string"],
            "init_data" => ["required", "string"],
        ]);
        [$botId, $chatId] = $this->authorizeMiniAppRequest($data);
        $chat = TelegraphChat::query()
            ->where("telegraph_bot_id", $botId)
            ->where("chat_id", $chatId)
            ->first();

        return response()->json([
            "success" => true,
            "reports_remaining" => max(0, (int) ($chat?->full_reports_remaining ?? 0)),
        ]);
    }

    public function purchase(Request $request)
    {
        $data = $request->validate([
            "plan_id" => ["required", "integer"],
            "bot_id" => ["required", "integer", "min:1"],
            "chat_id" => ["required", "string"],
            "init_data" => ["required", "string"],
        ]);

        [$botId, $chatId] = $this->authorizeMiniAppRequest($data);

        $plan = app(VinSubscriptionService::class)->findSubscriptionPlan(
            $botId,
            (int) $data["plan_id"],
        );
        if ($plan === null) {
            abort(404, "Пакет отчетов не найден или недоступен.");
        }

        $message = "Пакет отчетов #{$plan->id} пока доступен только в тестовом режиме.";

        if ($botId > 0 && $chatId !== "") {
            $chat = TelegraphChat::query()
                ->where("telegraph_bot_id", $botId)
                ->where("chat_id", $chatId)
                ->first();

            if ($chat) {
                $purchase = app(VinSubscriptionService::class)->simulatePurchase($chat, $botId, $plan);
                $message = "Тариф #{$plan->id} принят. Остаток: {$purchase['before']} → {$purchase['after']}";

                if ($request->expectsJson() || $request->ajax() || $request->header("X-Requested-With") === "XMLHttpRequest") {
                    return response()->json([
                        "success" => true,
                        "plan_id" => $plan->id,
                        "report_count" => (int) $plan->report_count,
                        "before" => (int) $purchase["before"],
                        "after" => (int) $purchase["after"],
                        "message" => $message,
                    ]);
                }
            }
        }

        return redirect()
            ->route("vin-mini-app", [
                "plan_id" => $data["plan_id"],
                "bot_id" => $botId > 0 ? $botId : null,
                "chat_id" => $chatId !== "" ? $chatId : null,
            ])
            ->with("vin-mini-app-action", $message);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function authorizeMiniAppRequest(array $data): array
    {
        $botId = (int) $data["bot_id"];
        $chatId = trim((string) $data["chat_id"]);
        $bot = TelegramBot::query()->find($botId);
        $initData = (string) $data["init_data"];

        if ($bot === null || !$this->isValidTelegramInitData($initData, (string) $bot->token)) {
            abort(403, "Не удалось подтвердить пользователя Telegram.");
        }

        parse_str($initData, $parsedInitData);
        $user = json_decode((string) ($parsedInitData["user"] ?? ""), true);
        $telegramChat = json_decode((string) ($parsedInitData["chat"] ?? ""), true);
        $verifiedChatId = $telegramChat["id"] ?? $user["id"] ?? null;

        if ($verifiedChatId === null || (string) $verifiedChatId !== $chatId) {
            abort(403, "Чат Telegram не подтвержден.");
        }

        return [$botId, $chatId];
    }

    private function isValidTelegramInitData(string $initData, string $botToken): bool
    {
        if ($initData === "" || $botToken === "") {
            return false;
        }

        parse_str($initData, $data);
        $receivedHash = (string) ($data["hash"] ?? "");
        $authDate = (int) ($data["auth_date"] ?? 0);
        unset($data["hash"]);

        if ($receivedHash === "" || $authDate <= 0) {
            return false;
        }

        $now = time();
        $ttl = (int) config("vin.web_app.init_data_ttl_seconds", 86400);
        if ($authDate > $now + 60 || $now - $authDate > $ttl) {
            return false;
        }

        ksort($data);
        $checkString = collect($data)
            ->map(fn (mixed $value, string $key): string => "{$key}={$value}")
            ->implode("\n");
        $secretKey = hash_hmac("sha256", "WebAppData", $botToken, true);
        $calculatedHash = hash_hmac("sha256", $checkString, $secretKey);

        return hash_equals($calculatedHash, $receivedHash);
    }
}
