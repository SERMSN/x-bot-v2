<?php

namespace Tests\Feature;

use App\Models\PsychologistEvent;
use App\Models\PsychologistMemory;
use App\Models\PsychologistMoodEntry;
use App\Models\PsychologistSession;
use App\Models\TelegramBot;
use App\Models\TelegraphChat;
use App\Services\Psychologist\PsychologistContextStore;
use App\Services\Psychologist\PsychologistConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PsychologistConversationTest extends TestCase
{
    use RefreshDatabase;

    private TelegramBot $bot;
    private TelegraphChat $chat;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            "psychologist.api.key" => "test-key",
            "psychologist.api.base_url" => "https://api.deepseek.com",
            "psychologist.api.model" => "deepseek-flash",
        ]);

        $this->bot = TelegramBot::query()->create([
            "token" => "psychologist-conversation-token",
            "name" => "Тихо",
            "handler_class" => "psychologist",
        ]);
        $this->chat = TelegraphChat::query()->create([
            "telegraph_bot_id" => $this->bot->id,
            "chat_id" => "3003",
            "name" => "Conversation chat",
        ]);
    }

    public function test_it_creates_a_reply_and_updates_technical_counters(): void
    {
        Http::fake([
            "api.deepseek.com/*" => Http::response([
                "choices" => [[
                    "message" => ["content" => "Похоже, тебе сейчас непросто. Что случилось?"],
                ]],
                "usage" => ["prompt_tokens" => 200, "completion_tokens" => 30],
            ]),
        ]);
        Http::preventStrayRequests();

        $service = app(PsychologistConversationService::class);
        $profile = $service->ensureProfile($this->bot, $this->chat);
        $profile->consent_at = now();
        $profile->save();

        $result = $service->reply($profile, "Мне тревожно");

        $this->assertStringContainsString("непросто", $result["content"]);
        $this->assertSame(2, PsychologistSession::query()->firstOrFail()->message_count);
        $this->assertSame(1, PsychologistSession::query()->firstOrFail()->user_messages);
        $this->assertSame(200, PsychologistSession::query()->firstOrFail()->input_tokens);

        $context = app(PsychologistContextStore::class)->get($profile);
        $this->assertCount(2, $context);
        $this->assertSame("Мне тревожно", $context[0]["content"]);
    }

    public function test_forget_removes_memory_mood_events_and_context(): void
    {
        $service = app(PsychologistConversationService::class);
        $profile = $service->ensureProfile($this->bot, $this->chat);

        PsychologistMemory::query()->create([
            "psychologist_profile_id" => $profile->id,
            "summary" => "Краткое резюме",
            "goals" => ["Больше отдыхать"],
            "topics" => ["Выгорание"],
        ]);
        PsychologistMoodEntry::query()->create([
            "psychologist_profile_id" => $profile->id,
            "score" => 4,
            "tags" => [],
        ]);
        PsychologistEvent::query()->create([
            "psychologist_profile_id" => $profile->id,
            "event_type" => "test",
            "meta" => [],
        ]);
        app(PsychologistContextStore::class)->put($profile, [
            ["role" => "user", "content" => "Секрет"],
        ]);

        $service->forget($profile);

        $this->assertDatabaseCount("psychologist_memories", 0);
        $this->assertDatabaseCount("psychologist_mood_entries", 0);
        $this->assertDatabaseCount("psychologist_events", 0);
        $this->assertSame([], app(PsychologistContextStore::class)->get($profile));
    }
}
