<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
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

        return view("vin-mini-app", [
            "plans" => $plans,
            "reportsRemaining" => 0,
            "selectedPlanId" => $selectedPlanId > 0 ? $selectedPlanId : null,
        ]);
    }

    public function purchase(Request $request)
    {
        $data = $request->validate([
            "plan_id" => ["required", "integer", "exists:subscription_plans,id"],
        ]);

        return redirect()
            ->route("vin-mini-app", ["plan_id" => $data["plan_id"]])
            ->with(
                "vin-mini-app-action",
                "Заглушка оплаты для тарифа #{$data['plan_id']}.",
            );
    }
}
