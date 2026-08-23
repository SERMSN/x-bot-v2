<?php

namespace App\Services\Telegram\Services;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\TelegraphChat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VinSubscriptionService
{
    public function getFullReportsRemaining(TelegraphChat $chat): int
    {
        return max(0, (int) ($chat->full_reports_remaining ?? 0));
    }

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function getSubscriptionPlans(int $botId): Collection
    {
        return SubscriptionPlan::query()
            ->active()
            ->where(function ($query) use ($botId) {
                $query
                    ->whereNull("telegraph_bot_id")
                    ->orWhere("telegraph_bot_id", $botId);
            })
            ->orderByRaw("CASE WHEN telegraph_bot_id IS NULL THEN 1 ELSE 0 END")
            ->orderBy("sort_order")
            ->orderBy("report_count")
            ->get()
            ->groupBy("report_count")
            ->map(fn ($group) => $group->first())
            ->values();
    }

    public function findSubscriptionPlan(int $botId, int $planId): ?SubscriptionPlan
    {
        return $this->getSubscriptionPlans($botId)->firstWhere("id", $planId);
    }

    public function simulatePurchase(TelegraphChat $chat, int $botId, SubscriptionPlan $plan): array
    {
        return DB::transaction(function () use ($chat, $botId, $plan): array {
            /** @var TelegraphChat $lockedChat */
            $lockedChat = TelegraphChat::query()
                ->whereKey($chat->id)
                ->lockForUpdate()
                ->firstOrFail();

            $count = (int) $plan->report_count;
            $before = $this->getFullReportsRemaining($lockedChat);
            $after = $before + $count;

            $lockedChat->full_reports_remaining = $after;
            $lockedChat->save();

            SubscriptionTransaction::query()->create([
                "telegraph_bot_id" => $botId,
                "telegraph_chat_id" => (int) $lockedChat->id,
                "subscription_plan_id" => $plan->id,
                "transaction_type" => "purchase_simulated",
                "reports_before" => $before,
                "reports_delta" => $count,
                "reports_after" => $after,
                "amount_rub" => (float) $plan->price_rub,
                "status" => "done",
                "comment" => "Имитация покупки подписки из Telegram",
                "meta" => [
                    "plan_name" => $plan->name,
                ],
            ]);

            SubscriptionTransaction::query()->create([
                "telegraph_bot_id" => $botId,
                "telegraph_chat_id" => (int) $lockedChat->id,
                "subscription_plan_id" => $plan->id,
                "transaction_type" => "credit",
                "reports_before" => $before,
                "reports_delta" => $count,
                "reports_after" => $after,
                "amount_rub" => null,
                "status" => "done",
                "comment" => "Начисление отчетов после имитации покупки",
                "meta" => [
                    "plan_name" => $plan->name,
                ],
            ]);

            $chat->forceFill(["full_reports_remaining" => $after]);

            return [
                "before" => $before,
                "after" => $after,
                "count" => $count,
            ];
        });
    }
}
