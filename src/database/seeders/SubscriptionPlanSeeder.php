<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ["report_count" => 1, "price_rub" => 100, "sort_order" => 1],
            ["report_count" => 5, "price_rub" => 450, "sort_order" => 2],
            ["report_count" => 10, "price_rub" => 850, "sort_order" => 3],
            ["report_count" => 20, "price_rub" => 1600, "sort_order" => 4],
            ["report_count" => 50, "price_rub" => 3500, "sort_order" => 5],
        ];

        foreach ($plans as $plan) {
            $suffix = $plan["report_count"] === 1 ? "" : "ов";
            $name = $plan["report_count"] . " отчет" . $suffix;

            SubscriptionPlan::query()->updateOrCreate(
                [
                    "telegraph_bot_id" => null,
                    "report_count" => $plan["report_count"],
                ],
                [
                    "name" => $name,
                    "price_rub" => $plan["price_rub"],
                    "description" =>
                        "Глобальный базовый пакет подписки для любого бота.",
                    "is_active" => true,
                    "sort_order" => $plan["sort_order"],
                ],
            );
        }
    }
}
