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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\Telegram\Services\VinService;
use App\Services\Telegram\Services\VinImageOcrService;

class VinBotHandler extends WebhookHandler
{
    private const CANCEL_KEYWORDS = ["отмена", "cancel", "stop", "выход"];

    public function start(): void
    {
        $this->clearPendingPhotoVin();
        $message = $this->messages()->startText();

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard($this->messages()->mainMenuKeyboard(
                (int) $this->bot->id,
                (string) ($this->chat->chat_id ?? ''),
                $this->subscriptions()->getFullReportsRemaining($this->chat),
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
                $this->subscriptions()->getFullReportsRemaining($this->chat),
            ))
            ->send();

        $this->logOutgoing($message, ["handler_action" => "help"]);
    }

    public function vin(): void
    {
        $this->promptVinInput();
    }

    public function check_vin(): void
    {
        $this->promptVinInput();
    }

    public function promptVinInput(): void
    {
        $this->clearPendingPhotoVin();
        $message = $this->messages()->promptVinInputText();

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->promptVinKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "prompt_vin_input"]);
    }

    protected function handleMessage(): void
    {
        if ($this->message?->photos()->isNotEmpty()) {
            $this->handleVinPhoto();
            return;
        }

        $this->clearPendingPhotoVin();
        parent::handleMessage();
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
                "help" => fn () => $this->help(),
                "start" => fn () => $this->start(),
                "confirm_photo_vin" => fn () => $this->confirmPhotoVin(),
                "cancel_photo_vin" => fn () => $this->cancelPhotoVin(),
            ],
            function (string $action): void {
                $this->chat->html("Действие: {$action}")->send();
                $this->logOutgoing("Действие: {$action}", [
                    "handler_action" => "unknown_callback",
                ]);
            },
        );
    }

    private function handleVinPhoto(): void
    {
        $this->clearPendingPhotoVin();

        $rateLimitKey = $this->photoRateLimitCacheKey();
        if (
            $rateLimitKey !== null &&
            !Cache::add(
                $rateLimitKey,
                true,
                now()->addSeconds((int) config('vin.ocr.rate_limit_seconds', 10)),
            )
        ) {
            $this->chat->html('Подождите немного перед отправкой следующего фото VIN.')->send();
            return;
        }

        $photo = $this->message?->photos()
            ->sortBy(fn ($item) => $item->width() * $item->height())
            ->last();

        if ($photo === null) {
            $this->sendPhotoVinNotFound();
            return;
        }

        $maximumBytes = (int) config('vin.ocr.max_image_size_mb', 10) * 1024 * 1024;
        if (($photo->filesize() ?? 0) > $maximumBytes) {
            $this->chat->html('Фото слишком большое. Отправьте изображение размером до 10 МБ.')->send();
            return;
        }

        $imagePath = null;

        try {
            $directory = storage_path('app/private/vin-ocr');
            $imagePath = $this->bot->store($photo, $directory, Str::uuid() . '.jpg');

            if (filesize($imagePath) > $maximumBytes) {
                throw new \RuntimeException('VIN photo exceeds the configured size limit.');
            }

            $vin = app(VinImageOcrService::class)->recognize($imagePath);
        } catch (\Throwable $exception) {
            Log::warning('VIN photo OCR failed', [
                'bot_id' => (int) $this->bot->id,
                'chat_id' => $this->chat->chat_id ?? null,
                'error' => $exception->getMessage(),
            ]);
            $vin = null;
        } finally {
            if (is_string($imagePath) && is_file($imagePath)) {
                File::delete($imagePath);
            }
        }

        if ($vin === null) {
            $this->sendPhotoVinNotFound();
            return;
        }

        $cacheKey = $this->pendingPhotoVinCacheKey();
        if ($cacheKey === null) {
            $this->sendPhotoVinNotFound();
            return;
        }

        $confirmationToken = Str::random(16);
        Cache::put(
            $cacheKey,
            [
                'vin' => $vin,
                'token' => $confirmationToken,
            ],
            now()->addMinutes((int) config('vin.ocr.confirmation_ttl_minutes', 5)),
        );

        $message = $this->messages()->photoVinCandidateText($vin);
        $this->chat
            ->html($message)
            ->keyboard($this->messages()->photoVinConfirmationKeyboard($confirmationToken))
            ->send();
        $this->logOutgoing('VIN photo candidate presented for confirmation', [
            'handler_action' => 'vin_photo_candidate',
        ]);
    }

    private function confirmPhotoVin(): void
    {
        $cacheKey = $this->pendingPhotoVinCacheKey();
        $pending = $cacheKey === null ? null : Cache::get($cacheKey);
        $callbackToken = $this->callbackQuery?->data()->get('token');

        if (
            !is_array($pending) ||
            !is_string($pending['token'] ?? null) ||
            !is_string($callbackToken) ||
            !hash_equals($pending['token'], $callbackToken) ||
            !is_string($pending['vin'] ?? null) ||
            !$this->isValidVin($pending['vin'])
        ) {
            $this->chat
                ->html($this->messages()->photoVinExpiredText())
                ->keyboard($this->messages()->invalidVinKeyboard())
                ->send();
            return;
        }

        Cache::forget($cacheKey);
        $this->processVin($pending['vin']);
    }

    private function cancelPhotoVin(): void
    {
        $cacheKey = $this->pendingPhotoVinCacheKey();
        $pending = $cacheKey === null ? null : Cache::get($cacheKey);
        $callbackToken = $this->callbackQuery?->data()->get('token');

        if (
            !is_array($pending) ||
            !is_string($pending['token'] ?? null) ||
            !is_string($callbackToken) ||
            !hash_equals($pending['token'], $callbackToken)
        ) {
            $this->chat
                ->html($this->messages()->photoVinExpiredText())
                ->send();
            return;
        }

        Cache::forget($cacheKey);
        $this->promptVinInput();
    }

    private function sendPhotoVinNotFound(): void
    {
        $message = $this->messages()->photoVinNotFoundText();
        $this->chat
            ->html($message)
            ->keyboard($this->messages()->invalidVinKeyboard())
            ->send();
        $this->logOutgoing($message, [
            'handler_action' => 'vin_photo_not_recognized',
            'is_error' => true,
        ]);
    }

    private function clearPendingPhotoVin(): void
    {
        $cacheKey = $this->pendingPhotoVinCacheKey();
        if ($cacheKey !== null) {
            Cache::forget($cacheKey);
        }
    }

    private function photoRateLimitCacheKey(): ?string
    {
        $userId = $this->currentTelegramUserId();
        $chatId = (string) ($this->chat->chat_id ?? '');

        if ($userId === null || $chatId === '') {
            return null;
        }

        return 'vin:photo:rate:' . (int) $this->bot->id . ':' . sha1($chatId) . ':' . $userId;
    }

    private function pendingPhotoVinCacheKey(): ?string
    {
        $userId = $this->currentTelegramUserId();
        $chatId = (string) ($this->chat->chat_id ?? '');

        if ($userId === null || $chatId === '') {
            return null;
        }

        return 'vin:photo:pending:' . (int) $this->bot->id . ':' . sha1($chatId) . ':' . $userId;
    }

    private function currentTelegramUserId(): ?int
    {
        if (isset($this->callbackQuery)) {
            return $this->callbackQuery->from()->id();
        }

        return $this->message?->from()?->id();
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
            $apiHasError = ($carData["error_code"] ?? "0") !== "0";

            if (!$apiHasError) {
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
            } else {
                $remainingAfterUse = $this->subscriptions()->getFullReportsRemaining($this->chat);
            }

            $message = $this->messages()->vinResultText(
                $carData,
                $fullReportAllowed,
                $remainingAfterUse,
            );

            if ($fullReportAllowed) {
                $this->sendMakeLogo($carData);
            }

            if ($apiHasError) {
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
                $this->subscriptions()->getFullReportsRemaining($this->chat),
            ))
            ->send();

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
