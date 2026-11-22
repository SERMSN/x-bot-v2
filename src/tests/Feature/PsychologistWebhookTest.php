<?php

namespace Tests\Feature;

use App\Models\ChatLog;
use App\Models\PsychologistProfile;
use App\Models\TelegramBot;
use App\Models\TelegraphChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PsychologistWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_a_profile_and_does_not_log_the_text(): void
    {
        $bot = TelegramBot::query()->create([
            "token" => "123456:psychologist-webhook-token",
            "name" => "Тихо",
            "handler_class" => "psychologist",
        ]);

        Http::fake([
            "api.telegram.org/*" => Http::response([
                "ok" => true,
                "result" => ["message_id" => 1],
            ]),
        ]);
        Http::preventStrayRequests();

        $response = $this->postJson(
            route("webhook.telegram.token", ["token" => $bot->token]),
            [
                "update_id" => 9001,
                "message" => [
                    "message_id" => 10,
                    "date" => time(),
                    "text" => "/start",
                    "from" => ["id" => 4004],
                    "chat" => ["id" => 4004, "type" => "private"],
                ],
            ],
        );

        $response->assertOk()->assertJson(["ok" => true]);

        $this->assertDatabaseHas("psychologist_profiles", [
            "telegraph_bot_id" => $bot->id,
        ]);

        $inbound = ChatLog::query()
            ->where("telegraph_bot_id", $bot->id)
            ->where("direction", "in")
            ->firstOrFail();

        $this->assertNull($inbound->message_text);
        $this->assertSame("/start", $inbound->command);
        $this->assertTrue($inbound->meta["sensitive"]);
    }

    public function test_crisis_message_triggers_safety_flow_without_ai_call(): void
    {
        $bot = TelegramBot::query()->create([
            "token" => "123456:psychologist-crisis-token",
            "name" => "Тихо",
            "handler_class" => "psychologist",
        ]);
        $chat = TelegraphChat::query()->create([
            "telegraph_bot_id" => $bot->id,
            "chat_id" => "4005",
            "name" => "Crisis chat",
        ]);
        PsychologistProfile::query()->create([
            "telegraph_bot_id" => $bot->id,
            "telegraph_chat_id" => $chat->id,
            "consent_at" => now(),
        ]);

        Http::fake([
            "api.telegram.org/*" => Http::response([
                "ok" => true,
                "result" => ["message_id" => 1],
            ]),
        ]);
        Http::preventStrayRequests();

        $this->postJson(
            route("webhook.telegram.token", ["token" => $bot->token]),
            [
                "update_id" => 9002,
                "message" => [
                    "message_id" => 11,
                    "date" => time(),
                    "text" => "Иногда я думаю, что не хочу жить",
                    "from" => ["id" => 4005],
                    "chat" => ["id" => 4005, "type" => "private"],
                ],
            ],
        )->assertOk();

        $this->assertDatabaseHas("psychologist_events", [
            "psychologist_profile_id" => 1,
            "event_type" => "crisis_detected",
        ]);
        $this->assertDatabaseHas("psychologist_sessions", [
            "psychologist_profile_id" => 1,
            "safety_flags" => 1,
        ]);
        $this->assertNull(
            ChatLog::query()
                ->where("telegraph_bot_id", $bot->id)
                ->where("direction", "in")
                ->firstOrFail()
                ->message_text,
        );
    }
}
