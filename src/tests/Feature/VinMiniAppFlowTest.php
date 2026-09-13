<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\TelegramBot;
use App\Models\TelegraphChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VinMiniAppFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_route_increments_selected_report_bundle(): void
    {
        $bot = TelegramBot::query()->create([
            'token' => 'test-token',
            'name' => 'Test Bot',
        ]);

        $chat = TelegraphChat::query()->create([
            'telegraph_bot_id' => $bot->id,
            'chat_id' => '123456',
            'name' => 'Test Chat',
            'full_reports_remaining' => 2,
        ]);

        $plan = SubscriptionPlan::query()->create([
            'telegraph_bot_id' => $bot->id,
            'name' => 'Пакет 10 отчетов',
            'report_count' => 10,
            'price_rub' => 1990.00,
            'description' => 'Test plan',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->post(route('vin-mini-app.purchase'), [
            'plan_id' => $plan->id,
            'bot_id' => $bot->id,
            'chat_id' => $chat->chat_id,
        ])->assertRedirect();

        $chat->refresh();

        $this->assertSame(12, $chat->full_reports_remaining);
    }

    public function test_mini_app_uses_real_purchase_request_and_closes_telegram_app_after_success(): void
    {
        $response = $this->get(route('vin-mini-app', [
            'bot_id' => 42,
            'chat_id' => '123456',
        ]));

        $response->assertOk();
        $response->assertSee('fetch(', false);
        $response->assertSee('telegramApp.close()', false);
        $response->assertDontSee('event.preventDefault();', false);
    }
}
