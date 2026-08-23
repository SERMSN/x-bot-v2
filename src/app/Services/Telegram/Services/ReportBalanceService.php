<?php

namespace App\Services\Telegram\Services;

use App\Models\SubscriptionTransaction;
use App\Models\TelegraphChat;
use Illuminate\Support\Facades\DB;

class ReportBalanceService
{
    public function getRemaining(TelegraphChat $chat): int
    {
        return max(0, (int) ($chat->full_reports_remaining ?? 0));
    }

    public function consumeFullReport(TelegraphChat $chat, int $botId, string $vin, array $carData): ?array
    {
        return DB::transaction(function () use ($chat, $botId, $vin, $carData): ?array {
            /** @var TelegraphChat|null $lockedChat */
            $lockedChat = TelegraphChat::query()
                ->whereKey($chat->id)
                ->lockForUpdate()
                ->first();

            if ($lockedChat === null) {
                return null;
            }

            $before = $this->getRemaining($lockedChat);
            if ($before <= 0) {
                return null;
            }

            $after = $before - 1;
            $lockedChat->full_reports_remaining = $after;
            $lockedChat->save();

            SubscriptionTransaction::query()->create([
                "telegraph_bot_id" => $botId,
                "telegraph_chat_id" => (int) $lockedChat->id,
                "subscription_plan_id" => null,
                "transaction_type" => "consume",
                "reports_before" => $before,
                "reports_delta" => -1,
                "reports_after" => $after,
                "amount_rub" => null,
                "status" => "done",
                "comment" => "Списание полного отчета при выдаче VIN-результата",
                "meta" => [
                    "vin" => $vin,
                    "make" => $carData["make"] ?? null,
                    "model" => $carData["model"] ?? null,
                ],
            ]);

            $chat->forceFill(["full_reports_remaining" => $after]);

            return [
                "before" => $before,
                "after" => $after,
            ];
        });
    }
}
