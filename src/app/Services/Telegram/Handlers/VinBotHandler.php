<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\Messages\VinMessageBuilder;
use App\Services\Telegram\Services\ReportBalanceService;
use App\Services\Telegram\Services\VinSubscriptionService;
use App\Services\Telegram\Support\BotCommandRouter;
use App\Services\Telegram\Support\BotContext;
use App\Services\Telegram\Support\CallbackAction;
use App\Services\Telegram\Support\TelegramResponder;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Services\Telegram\Services\VinService;

class VinBotHandler extends WebhookHandler
{
    private const CANCEL_KEYWORDS = ["отмена", "cancel", "stop", "выход"];

    public function start(): void
    {
        $message = $this->messages()->startText();

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard($this->messages()->mainMenuKeyboard(
                (int) $this->bot->id,
                (string) ($this->chat->chat_id ?? ''),
            ))
            ->send();

        $this->logOutgoing($message, ["handler_action" => "start"]);
    }

    public function help(): void
    {
        $message = $this->messages()->helpText();

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->helpKeyboard(
                (int) $this->bot->id,
                (string) ($this->chat->chat_id ?? ''),
            ))
            ->send();

        $this->logOutgoing($message, ["handler_action" => "help"]);
    }

    public function vin(): void
    {
        $this->promptVinInput();
    }

    public function subscription(): void
    {
        $miniAppUrl = $this->messages()->miniAppUrl(
            (int) $this->bot->id,
            (string) ($this->chat->chat_id ?? ''),
        );
        $message = "💳 <b>Подписка</b>\n\nОткройте мини-приложение и выберите нужный пакет отчётов.";

        $this->chat
            ->html($message)
            ->keyboard(
                \DefStudio\Telegraph\Keyboard\Keyboard::make()
                    ->buttons([
                        \DefStudio\Telegraph\Keyboard\Button::make("💳 Открыть Mini App")->webApp($miniAppUrl),
                    ])
                    ->chunk(1),
            )
            ->send();

        $this->logOutgoing($message, ["handler_action" => "subscription_open_mini_app"]);
    }

    public function check_vin(): void
    {
        $this->promptVinInput();
    }

    public function promptVinInput(): void
    {
        $message = $this->messages()->promptVinInputText();

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->promptVinKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "prompt_vin_input"]);
    }

    protected function handleChatMessage(Stringable $text): void
    {
        if ($text->startsWith("/")) {
            parent::handleChatMessage($text);
            return;
        }

        if ($this->isCancel($text->toString())) {
            $this->start();
            return;
        }

        $vin = $this->normalizeVin($text->toString());

        if ($this->isValidVin($vin)) {
            $this->processVin($vin);
        } else {
            $message = $this->messages()->invalidVinText();

            $this->chat
                ->markdown($message)
                ->keyboard($this->messages()->invalidVinKeyboard())
                ->send();

            $this->logOutgoing($message, [
                "handler_action" => "invalid_vin",
                "is_error" => true,
            ]);
        }
    }

    protected function handleCallbackQuery(): void
    {
        $this->extractCallbackQueryData();
        $this->ackCallbackQuery();
        $callbackData = $this->callbackQuery?->data();

        if (!$callbackData) {
            return;
        }

        $action = CallbackAction::parse($callbackData);

        app(BotCommandRouter::class)->dispatch(
            $action,
            [
                "check_vin" => fn () => $this->promptVinInput(),
                "subscription" => fn () => $this->subscription(),
                "help" => fn () => $this->help(),
                "start" => fn () => $this->start(),
            ],
            function (string $action): void {
                if (str_starts_with($action, "subscription_plan_")) {
                    $planId = (int) substr(
                        $action,
                        strlen("subscription_plan_"),
                    );
                    $this->subscriptionPlan($planId);
                    return;
                }

                $this->chat->html("Действие: {$action}")->send();
                $this->logOutgoing("Действие: {$action}", [
                    "handler_action" => "unknown_callback",
                ]);
            },
        );
    }

    private function processVin(string $vin): void
    {
        $fullReportAllowed = false;
        $remainingAfterUse = 0;
        $carData = [];

        try {
            /** @var VinService $vinService */
            $vinService = app(VinService::class);
            $carData = $vinService->decode((int) $this->bot->id, $vin);
            $consumeResult = app(ReportBalanceService::class)->consumeFullReport(
                $this->chat,
                (int) $this->bot->id,
                $vin,
                $carData,
            );
            $fullReportAllowed = $consumeResult !== null;
            $remainingAfterUse = $fullReportAllowed
                ? (int) $consumeResult["after"]
                : $this->subscriptions()->getFullReportsRemaining($this->chat);

            $message = $this->messages()->vinResultText(
                $carData,
                $fullReportAllowed,
                $remainingAfterUse,
            );

            if ($fullReportAllowed) {
                $this->sendMakeLogo($carData);
            }

            if (($carData["error_code"] ?? "") !== "0") {
                $message .=
                    "\n\n⚠️ API сообщило: <b>" .
                    $this->messages()->escapeHtml((string) ($carData["error_text"] ?? "")) .
                    "</b>";
            }
        } catch (\Throwable $e) {
            Log::error("VIN API error", ["exception" => $e]);
            $message = $this->messages()->vinFailureText();
        }

        $messages = $this->messages()->splitLongMessage($message);
        foreach ($messages as $messagePart) {
            $this->chat->html($messagePart)->send();
        }

        $this->chat
            ->html($this->messages()->vinResultNextText($fullReportAllowed))
                ->keyboard($this->messages()->vinResultKeyboard(
                    $fullReportAllowed,
                    (int) $this->bot->id,
                    (string) ($this->chat->chat_id ?? ''),
                ))
        $this->logOutgoing($message, ["handler_action" => "process_vin"]);
    }

    private function sendMakeLogo(array $carData): void
    {
        $logoUrl = trim((string) ($carData["make_logo_url"] ?? ""));
        if ($logoUrl === "" || $logoUrl === "-") {
            return;
        }

        $photo = $this->makeLogoPhoto($logoUrl);
        if ($photo === null) {
            return;
        }

        $caption = $this->messages()->makeLogoCaption($carData);

        try {
            $this->chat->photo($photo)->html($caption)->send();
        } catch (\Throwable $e) {
            Log::warning("VIN make logo send failed", [
                "make_logo_url" => $logoUrl,
                "photo" => $photo,
                "error" => $e->getMessage(),
            ]);
        }
    }

    private function makeLogoPhoto(string $logoUrl): ?string
    {
        if (!$this->isSvgUrl($logoUrl)) {
            return $logoUrl;
        }

        return $this->convertSvgLogoToPng($logoUrl);
    }

    private function isSvgUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        return is_string($path) && str_ends_with(strtolower($path), ".svg");
    }

    private function convertSvgLogoToPng(string $logoUrl): ?string
    {
        $directory = storage_path("app/vin-logos");
        $path = "{$directory}/vin-logo-v3-" . sha1($logoUrl) . ".png";

        if (File::isFile($path)) {
            return $path;
        }

        try {
            File::ensureDirectoryExists($directory);

            $response = Http::timeout(10)->get($logoUrl);
            if (!$response->successful()) {
                Log::warning("VIN make logo download failed", [
                    "make_logo_url" => $logoUrl,
                    "status" => $response->status(),
                ]);
                return null;
            }

            $svgPath = "{$directory}/vin-logo-v3-" . sha1($logoUrl) . ".svg";
            File::put($svgPath, $response->body());

            $command = sprintf(
                'rsvg-convert -b white -w 200 -h 200 -o %s %s 2>/dev/null',
                escapeshellarg($path),
                escapeshellarg($svgPath),
            );
            exec($command, $output, $exitCode);

            File::delete($svgPath);

            if ($exitCode !== 0 || !File::isFile($path)) {
                Log::warning("VIN make logo conversion failed", [
                    "make_logo_url" => $logoUrl,
                    "exit_code" => $exitCode,
                ]);
                return null;
            }

            return $path;
        } catch (\Throwable $e) {
            Log::warning("VIN make logo conversion failed", [
                "make_logo_url" => $logoUrl,
                "error" => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function subscriptionPlan(int $planId): void
    {
        $botId = (int) $this->bot->id;
        $plan = $this->subscriptions()->findSubscriptionPlan($botId, $planId);

        if (!$plan) {
            $plans = $this->subscriptions()->getSubscriptionPlans($botId);
            $message = $this->messages()->subscriptionPlanMissingText();

            $this->chat
                ->html($message)
                ->keyboard($this->messages()->subscriptionKeyboard($plans))
                ->send();

            $this->logOutgoing($message, [
                "handler_action" => "subscription_plan_missing",
                "plan_id" => $planId,
            ]);

            return;
        }

        $count = (int) $plan->report_count;
        $purchase = $this->subscriptions()->simulatePurchase($this->chat, $botId, $plan);
        $before = (int) $purchase["before"];
        $after = (int) $purchase["after"];
        $message = $this->messages()->subscriptionPlanPurchasedText($plan, $before, $after);

        $this->chat
            ->html($message)
            ->keyboard(
                $this->messages()->subscriptionKeyboard(
                    $this->subscriptions()->getSubscriptionPlans($botId),
                ),
            )
            ->send();

        $this->logOutgoing($message, [
            "handler_action" => "subscription_plan",
            "plan_id" => $plan->id,
            "report_count" => $count,
            "price_rub" => (float) $plan->price_rub,
            "full_reports_before" => $before,
            "full_reports_after" => $after,
        ]);

        Log::info("VIN subscription simulated purchase", [
            "bot_id" => $botId,
            "chat_model_id" => isset($this->chat->id) ? (int) $this->chat->id : null,
            "telegram_chat_id" => isset($this->chat->chat_id)
                ? (string) $this->chat->chat_id
                : null,
            "plan_id" => $plan->id,
            "report_count" => $count,
            "price_rub" => (float) $plan->price_rub,
            "before" => $before,
            "after" => $after,
        ]);
    }

    public function handleUnknownCommand(Stringable $text): void
    {
        $this->chat
            ->markdown(
                "❌ Неизвестная команда в VIN-декодере: *{$text}*\n\nИспользуйте /help для справки.",
            )
            ->keyboard($this->messages()->unknownCommandKeyboard())
            ->send();

        $this->logOutgoing("❌ Неизвестная команда в VIN-декодере: {$text}", [
            "handler_action" => "unknown_command",
            "is_error" => true,
        ]);
    }

    private function normalizeVin(string $vin): string
    {
        $vin = strtoupper(trim($vin));
        return preg_replace("/[^A-Z0-9]/", "", $vin) ?? "";
    }

    private function isValidVin(string $vin): bool
    {
        $requiredLength = (int) config("vin.validation.length", 17);
        if (strlen($vin) !== $requiredLength) {
            return false;
        }

        return (bool) preg_match("/^[A-HJ-NPR-Z0-9]+$/", $vin);
    }

    private function isCancel(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        return in_array($normalized, self::CANCEL_KEYWORDS, true);
    }

    private function ackCallbackQuery(string $message = ""): void
    {
        app(TelegramResponder::class)->ackCallback(
            $this->botContext(),
            isset($this->callbackQueryId) ? (string) $this->callbackQueryId : null,
            $message,
        );
    }

    private function logOutgoing(string $message, array $meta = []): void
    {
        app(TelegramResponder::class)->logOutgoing($this->botContext(), $message, $meta);
    }

    private function botContext(): BotContext
    {
        return new BotContext($this->bot, $this->chat);
    }

    private function messages(): VinMessageBuilder
    {
        return app(VinMessageBuilder::class);
    }

    private function subscriptions(): VinSubscriptionService
    {
        return app(VinSubscriptionService::class);
    }
}
