<?php

namespace App\Http\Controllers;

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
        $chatId = trim((string) $request->query("chat_id", ""));
        $reportsRemaining = 0;

        if ($botId > 0 && $chatId !== "") {
            $chat = TelegraphChat::query()
                ->where("telegraph_bot_id", $botId)
                ->where("chat_id", $chatId)
                ->first();

            if ($chat) {
                $reportsRemaining = max(0, (int) ($chat->full_reports_remaining ?? 0));
            }
        }

        return view("vin-mini-app", [
            "plans" => $plans,
            "reportsRemaining" => $reportsRemaining,
            "selectedPlanId" => $selectedPlanId > 0 ? $selectedPlanId : null,
        ]);
    }

    public function context(Request $request)
    {
        $data = $request->validate([
            "bot_id" => ["required", "integer", "min:1"],
            "chat_id" => ["required", "string"],
        ]);
        $botId = (int) $data["bot_id"];
        $chatId = trim((string) $data["chat_id"]);
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
        ]);

        $botId = (int) $data["bot_id"];
        $chatId = trim((string) $data["chat_id"]);

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

}
