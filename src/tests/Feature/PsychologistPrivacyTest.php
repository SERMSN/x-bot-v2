<?php

namespace Tests\Feature;

use App\Models\ChatLog;
use App\Models\TelegramBot;
use App\Models\TelegraphChat;
use App\Services\Telegram\ChatLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PsychologistPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_store_psychologist_message_text(): void
    {
        $bot = TelegramBot::query()->create([
            "token" => "psychologist-test-token",
            "name" => "Тихо",
            "handler_class" => "psychologist",
        ]);
        $chat = TelegraphChat::query()->create([
            "telegraph_bot_id" => $bot->id,
            "chat_id" => "1001",
            "name" => "Test chat",
        ]);

        $request = Request::create("/telegram/token", "POST", [
            "update_id" => 777,
            "message" => [
                "message_id" => 1,
                "date" => time(),
                "text" => "Очень личное сообщение",
                "from" => ["id" => 1001],
                "chat" => ["id" => 1001, "type" => "private"],
            ],
        ]);

        app(ChatLogger::class)->logInbound($request, (int) $bot->id, "psychologist");

        $log = ChatLog::query()->latest("id")->firstOrFail();

        $this->assertNull($log->message_text);
        $this->assertSame(22, $log->meta["text_length"]);
        $this->assertTrue($log->meta["sensitive"]);
    }

    public function test_it_keeps_text_for_non_psychologist_bots(): void
    {
        $bot = TelegramBot::query()->create([
            "token" => "weather-test-token",
            "name" => "Weather",
            "handler_class" => "weather",
        ]);
        TelegraphChat::query()->create([
            "telegraph_bot_id" => $bot->id,
            "chat_id" => "2002",
            "name" => "Weather chat",
        ]);

        $request = Request::create("/telegram/token", "POST", [
            "update_id" => 778,
            "message" => [
                "message_id" => 2,
                "date" => time(),
                "text" => "Москва",
                "from" => ["id" => 2002],
                "chat" => ["id" => 2002, "type" => "private"],
            ],
        ]);

        app(ChatLogger::class)->logInbound($request, (int) $bot->id, "weather");

        $this->assertSame("Москва", ChatLog::query()->latest("id")->firstOrFail()->message_text);
    }

    public function test_it_can_store_an_outbound_log_without_text(): void
    {
        $bot = TelegramBot::query()->create([
            "token" => "psychologist-out-test-token",
            "name" => "Тихо",
            "handler_class" => "psychologist",
        ]);

        app(ChatLogger::class)->logOutbound(
            (int) $bot->id,
            null,
            "1001",
            "Ответ бота",
            ["handler_action" => "reply"],
            false,
        );

        $log = ChatLog::query()->latest("id")->firstOrFail();

        $this->assertNull($log->message_text);
        $this->assertSame(10, $log->meta["text_length"]);
        $this->assertTrue($log->meta["sensitive"]);
    }
}
