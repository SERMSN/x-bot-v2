<?php

namespace App\Services\Telegram\Handlers;

use App\Models\PsychologistEvent;
use App\Models\PsychologistMoodEntry;
use App\Models\PsychologistProfile;
use App\Services\Psychologist\PsychologistConversationService;
use App\Services\Psychologist\PsychologistMessageBuilder;
use App\Services\Psychologist\PsychologistSafetyService;
use App\Services\Telegram\ChatLogger;
use App\Services\Telegram\Support\CallbackAction;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;
use Throwable;

class PsychologistBotHandler extends WebhookHandler
{
    protected PsychologistConversationService $conversationService;
    protected PsychologistMessageBuilder $messages;
    protected PsychologistSafetyService $safety;

    public function __construct()
    {
        parent::__construct();

        $this->conversationService = app(PsychologistConversationService::class);
        $this->messages = app(PsychologistMessageBuilder::class);
        $this->safety = app(PsychologistSafetyService::class);
    }

    public function start(): void
    {
        $profile = $this->profile();

        if ($profile->consent_at === null) {
            $this->sendIntentional(
                $this->messages->consentText(),
                $this->messages->consentKeyboard(),
                "consent",
            );
            return;
        }

        $this->conversationService->resetSession($profile);
        $this->sendTextWithReplyKeyboard(
            $this->messages->startText(),
            "start",
        );
    }

    public function reset(): void
    {
        $profile = $this->profile();

        if ($profile->consent_at === null) {
            $this->start();
            return;
        }

        $this->conversationService->resetSession($profile);
        $this->sendTextWithReplyKeyboard(
            "Начнём с чистого листа. Память о прошлых темах сохранена.\n\n"
                . $this->messages->startText(),
            "reset",
        );
    }

    public function help(): void
    {
        $this->sendIntentional(
            $this->messages->helpText(),
            $this->messages->helpKeyboard(),
            "help",
        );
    }

    public function forget(): void
    {
        $profile = $this->profile();

        if (!$this->conversationService->hasMemory($profile)) {
            $this->sendIntentional(
                $this->messages->forgetEmptyText(),
                $this->messages->helpKeyboard(),
                "forget_empty",
            );
            return;
        }

        $this->sendIntentional(
            $this->messages->forgetConfirmText(),
            $this->messages->forgetKeyboard(),
            "forget_confirm",
        );
    }

    public function mood(): void
    {
        $this->sendIntentional(
            $this->messages->moodText(),
            $this->messages->moodKeyboard(),
            "mood",
        );
    }

    public function exercise(): void
    {
        $this->sendIntentional(
            $this->messages->exerciseMenuText(),
            $this->messages->exerciseKeyboard(),
            "exercise",
        );
    }

    public function summary(): void
    {
        $profile = $this->profile();
        $summary = null;

        try {
            $summary = $this->conversationService->summarizeNow($profile);
        } catch (Throwable $e) {
            Log::warning("Psychologist summary failed", [
                "profile_id" => $profile->id,
                "error_class" => $e::class,
            ]);
        }

        if ($summary === null) {
            $summary = $this->conversationService->memorySummary($profile);
        }

        if ($summary === null) {
            $this->sendIntentional(
                $this->messages->summaryEmptyText(),
                $this->messages->afterReplyKeyboard(),
                "summary_empty",
            );
            return;
        }

        $this->sendIntentional(
            "Коротко о нашей сессии:\n\n{$summary}",
            $this->messages->afterReplyKeyboard(),
            "summary",
        );
    }

    public function settings(): void
    {
        $this->sendIntentional(
            $this->messages->settingsText(),
            $this->messages->settingsKeyboard(),
            "settings",
        );
    }

    public function crisis(): void
    {
        $this->sendIntentional(
            $this->messages->crisisText(),
            $this->messages->helpKeyboard(),
            "crisis",
        );
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $value = trim($text->toString());

        if ($value === "") {
            $this->sendTextWithReplyKeyboard(
                "Я работаю с текстом. Опиши, пожалуйста, что происходит, словами.",
                "non_text_message",
            );
            return;
        }

        if ($this->isCommandLike($value)) {
            parent::handleChatMessage($text);
            return;
        }

        $label = mb_strtolower($value);

        if ($this->matches($label, PsychologistMessageBuilder::BTN_TALK, "поговорить")) {
            $this->sendTextWithReplyKeyboard(
                $this->messages->startText(),
                "talk_button",
            );
            return;
        }

        if ($this->matches($label, PsychologistMessageBuilder::BTN_RESET, "начать заново", "перезапустить")) {
            $this->reset();
            return;
        }

        if ($this->matches($label, PsychologistMessageBuilder::BTN_EXERCISE, "упражнение")) {
            $this->exercise();
            return;
        }

        if ($this->matches($label, PsychologistMessageBuilder::BTN_MOOD, "дневник", "настроение")) {
            $this->mood();
            return;
        }

        if ($this->matches($label, PsychologistMessageBuilder::BTN_HELP, "помощь")) {
            $this->help();
            return;
        }

        if ($this->matches($label, PsychologistMessageBuilder::BTN_SETTINGS, "настройки")) {
            $this->settings();
            return;
        }

        $profile = $this->profile();

        if ($profile->consent_at === null) {
            $this->start();
            return;
        }

        if ($this->conversationService->crisisPending($profile)) {
            if ($this->looksSafe($value)) {
                $this->conversationService->clearCrisisPending($profile);
                PsychologistEvent::query()->create([
                    "psychologist_profile_id" => $profile->id,
                    "event_type" => "crisis_safe",
                    "meta" => ["text_length" => mb_strlen($value)],
                ]);
                $this->sendIntentional(
                    "Хорошо, что ты в безопасности. Давай не будем оставаться с этим в одиночку: можно сделать короткое заземление или написать близкому человеку.",
                    $this->messages->afterExerciseKeyboard(),
                    "crisis_safe",
                );
                return;
            }

            $this->sendIntentional(
                $this->messages->crisisText(),
                $this->messages->helpKeyboard(),
                "crisis_pending",
            );
            return;
        }

        if ($this->safety->detect($value)) {
            $this->conversationService->recordCrisis($profile);
            $this->conversationService->markCrisisPending($profile);
            PsychologistEvent::query()->create([
                "psychologist_profile_id" => $profile->id,
                "event_type" => "crisis_detected",
                "meta" => ["text_length" => mb_strlen($value)],
            ]);
            $this->sendIntentional(
                $this->messages->crisisText(),
                $this->messages->helpKeyboard(),
                "crisis_detected",
            );
            return;
        }

        try {
            $result = $this->conversationService->reply($profile, $value);
        } catch (Throwable $e) {
            Log::warning("Psychologist reply failed", [
                "profile_id" => $profile->id,
                "error_class" => $e::class,
            ]);
            $this->sendTextWithReplyKeyboard(
                $this->messages->offlineText(),
                "offline",
            );
            return;
        }

        $this->sendIntentional(
            $result["content"],
            $this->messages->afterReplyKeyboard(),
            "reply",
            [
                "input_tokens" => $result["input_tokens"],
                "output_tokens" => $result["output_tokens"],
            ],
        );
    }

    protected function handleCallbackQuery(): void
    {
        $this->extractCallbackQueryData();
        $this->ackCallbackQuery();

        $data = $this->callbackQuery?->data();
        if (!$data) {
            return;
        }

        $action = CallbackAction::parse($data);

        if (preg_match('/^psychologist_mood_([1-9]|10)$/', $action, $matches) === 1) {
            $this->saveMood((int) $matches[1]);
            return;
        }

        switch ($action) {
            case "psychologist_consent":
                $this->consent();
                break;
            case "psychologist_talk":
                $this->sendTextWithReplyKeyboard(
                    $this->messages->startText(),
                    "talk_callback",
                );
                break;
            case "psychologist_help":
                $this->help();
                break;
            case "psychologist_forget":
                $this->forget();
                break;
            case "psychologist_forget_confirm":
                $this->forgetConfirm();
                break;
            case "psychologist_forget_cancel":
                $this->sendTextWithReplyKeyboard(
                    "Хорошо, ничего не удаляю. Память осталась на месте.",
                    "forget_cancel",
                );
                break;
            case "psychologist_mood":
                $this->mood();
                break;
            case "psychologist_exercise":
                $this->exercise();
                break;
            case "psychologist_exercise_breathing":
                $this->sendIntentional(
                    $this->messages->breathingText(),
                    $this->messages->afterExerciseKeyboard(),
                    "exercise_breathing",
                );
                break;
            case "psychologist_exercise_grounding":
                $this->sendIntentional(
                    $this->messages->groundingText(),
                    $this->messages->afterExerciseKeyboard(),
                    "exercise_grounding",
                );
                break;
            case "psychologist_exercise_thought":
                $this->sendIntentional(
                    $this->messages->thoughtRecordText(),
                    $this->messages->afterExerciseKeyboard(),
                    "exercise_thought",
                );
                break;
            case "psychologist_summary":
                $this->summary();
                break;
            case "psychologist_settings":
                $this->settings();
                break;
            case "psychologist_style_warm":
                $this->setStyle("warm");
                break;
            case "psychologist_style_direct":
                $this->setStyle("direct");
                break;
            case "psychologist_style_brief":
                $this->setStyle("brief");
                break;
            case "psychologist_crisis":
                $this->crisis();
                break;
            case "psychologist_start":
                $this->start();
                break;
            default:
                $this->sendTextWithReplyKeyboard(
                    $this->messages->unknownText(),
                    "unknown_callback",
                );
        }
    }

    public function handleUnknownCommand(Stringable $text): void
    {
        $this->sendIntentional(
            "Не знаю такую команду. Вот что можно сделать:",
            $this->messages->helpKeyboard(),
            "unknown_command",
        );
    }

    private function consent(): void
    {
        $profile = $this->profile();

        if ($profile->consent_at === null) {
            $profile->consent_at = now();
            $profile->save();
        }

        $this->conversationService->resetSession($profile);
        $this->sendTextWithReplyKeyboard(
            "Спасибо, что доверяешь. Я рядом.\n\n" . $this->messages->startText(),
            "consent_accepted",
        );
    }

    private function forgetConfirm(): void
    {
        $profile = $this->profile();
        $this->conversationService->forget($profile);

        $this->sendTextWithReplyKeyboard(
            $this->messages->forgetDoneText(),
            "forget_done",
        );
    }

    private function saveMood(int $score): void
    {
        $score = max(1, min(10, $score));
        $profile = $this->profile();

        PsychologistMoodEntry::query()->create([
            "psychologist_profile_id" => $profile->id,
            "score" => $score,
            "tags" => [],
        ]);

        $this->sendIntentional(
            $this->messages->moodSavedText($score),
            $this->messages->afterReplyKeyboard(),
            "mood_saved",
            ["score" => $score],
        );
    }

    private function setStyle(string $style): void
    {
        $profile = $this->profile();
        $this->conversationService->setStyle($profile, $style);

        $this->sendIntentional(
            $this->messages->styleSavedText($style),
            $this->messages->settingsKeyboard(),
            "style_saved",
            ["style" => $style],
        );
    }

    private function profile(): PsychologistProfile
    {
        return $this->conversationService->ensureProfile(
            $this->bot,
            $this->chat,
        );
    }

    private function isCommandLike(string $value): bool
    {
        return str_starts_with(trim($value), "/");
    }

    private function looksSafe(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return preg_match(
            '/(?<![\p{L}])(да|в безопасности|опасности нет|нет опасности|мне ничего не угрожает|я в порядке|спокоен|спокойна)(?![\p{L}])/ui',
            $normalized,
        ) === 1;
    }

    private function matches(string $value, string $label, string ...$fallbacks): bool
    {
        $candidates = [$label, ...$fallbacks];

        foreach ($candidates as $candidate) {
            if ($value === mb_strtolower($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function sendTextWithReplyKeyboard(string $text, string $action): void
    {
        $this->chat
            ->message($text)
            ->replyKeyboard($this->messages->mainReplyKeyboard())
            ->send();

        $this->logOutgoing($text, [
            "handler_action" => $action,
            "reply_keyboard" => true,
        ]);
    }

    private function sendIntentional(
        string $text,
        ?Keyboard $keyboard = null,
        string $action = "message",
        array $meta = [],
    ): void {
        $telegraph = $this->chat->message($text);

        if ($keyboard !== null) {
            $telegraph = $telegraph->keyboard($keyboard);
        }

        $telegraph->send();

        $this->logOutgoing($text, [
            "handler_action" => $action,
            ...$meta,
        ]);
    }

    private function logOutgoing(string $text, array $meta = []): void
    {
        app(ChatLogger::class)->logOutbound(
            (int) $this->bot->id,
            isset($this->chat->id) ? (int) $this->chat->id : null,
            isset($this->chat->chat_id) ? (string) $this->chat->chat_id : null,
            $text,
            $meta,
            false,
        );
    }

    private function ackCallbackQuery(string $message = ""): void
    {
        if (isset($this->callbackQueryId) && $this->callbackQueryId) {
            $this->bot->replyWebhook($this->callbackQueryId, $message)->send();
        }
    }
}
