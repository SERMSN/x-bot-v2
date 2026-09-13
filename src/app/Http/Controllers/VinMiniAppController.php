<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\TelegraphChat;
use App\Services\Telegram\Services\VinSubscriptionService;
use Illuminate\Http\Request;

class VinMiniAppController extends Controller
{
    public function index(Request $request)
    {
        $plans = SubscriptionPlan::query()
            ->where("is_active", true)
            ->orderBy("sort_order")
            ->orderBy("id")
            ->get();

        $selectedPlanId = $request->integer("plan_id");
        $reportsRemaining = 0;

        $botId = $request->integer("bot_id");
        $chatId = trim((string) $request->query("chat_id", ""));

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

    public function purchase(Request $request)
    {
        $data = $request->validate([
            "plan_id" => ["required", "integer", "exists:subscription_plans,id"],
            "bot_id" => ["nullable", "integer", "min:1"],
            "chat_id" => ["nullable", "string"],
        ]);

        $botId = isset($data["bot_id"]) ? (int) $data["bot_id"] : 0;
        $chatId = trim((string) ($data["chat_id"] ?? ""));

        $plan = SubscriptionPlan::query()->findOrFail($data["plan_id"]);
        $message = "Заглушка оплаты для тарифа #{$plan->id}.";

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
