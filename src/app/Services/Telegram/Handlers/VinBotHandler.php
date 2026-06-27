<?php

namespace App\Services\Telegram\Handlers;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Services\Telegram\ChatLogger;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use App\Services\Telegram\Services\VinService;

class VinBotHandler extends WebhookHandler
{
    private const CANCEL_KEYWORDS = ["отмена", "cancel", "stop", "выход"];
    private const FREE_VISIBLE_FIELDS = ["VIN", "Марка", "Модель", "Год модели"];
    private const MASK_VALUE = "**********";

    public function start(): void
    {
        $message = "🚗 *VIN-бот*\n\n";
        $message .= "Действие: проверка VIN.\n";
        $message .= "Подсказка: нажмите кнопку и отправьте VIN из 17 символов.";

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard(
                Keyboard::make()
                    ->buttons([
                        Button::make("🔍 Проверить VIN")->action("check_vin"),
                        Button::make("❓ Помощь")->action("help"),
                        Button::make("💳 Подписка")->action("subscription"),
                    ])
                    ->chunk(2),
            )
            ->send();

        $this->logOutgoing($message, ["handler_action" => "start"]);
    }

    public function help(): void
    {
        $message = "📚 *Помощь*\n\n";
        $message .= "Команды:\n";
        $message .= "/start — Главное меню\n";
        $message .= "/help — Справка\n";
        $message .= "/vin — Ввести VIN\n";
        $message .= "/subscription — Подписка\n\n";
        $message .= "Формат VIN: 17 символов (A-Z, 0-9, без I/O/Q).\n";
        $message .= "Пример: *JHMCM56557C404453*.\n";
        $message .= "Для отмены отправьте: *Отмена*.\n\n";
        $message .= "📋 *Что можно получить по VIN*\n";
        $message .= "• Основное: VIN, марка, модель, год, тип ТС, кузов, привод.\n";
        $message .= "• Двигатель и трансмиссия: тип двигателя, объем, производитель, топливо, коробка, число передач, экостандарт, расход, CO2.\n";
        $message .= "• Производитель: название, адрес, страна сборки.\n";
        $message .= "• Кузов и размеры: двери, места, колеса, оси, база, высота, длина, ширина, колея.\n";
        $message .= "• Масса и эксплуатация: скорость, масса, нагрузка на крышу, разрешенная масса прицепа.\n";
        $message .= "• Ходовая и оснащение: ABS, тормоза, подвеска, рулевое управление, диски, шины.\n";
        $message .= "• VIN-данные: Vehicle ID, контрольная цифра, серийный номер.\n\n";
        $message .= "Фактический набор зависит от того, какие поля Vincario вернет по конкретному VIN.\n\n";
        $message .= "Подписка: /subscription.";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🔍 Проверить VIN")->action("check_vin");
                $keyboard->button("🏠 На главную")->action("start");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();

        $this->logOutgoing($message, ["handler_action" => "help"]);
    }

    public function vin(): void
    {
        $this->promptVinInput();
    }

    public function subscription(): void
    {
        $remaining = $this->getFullReportsRemaining();
        $plans = $this->getSubscriptionPlans();

        $message = "💳 <b>Подписка</b>\n\n";
        $message .= "Действие: пополнение полного отчета.\n";
        $message .= "Осталось полных отчетов: <b>{$remaining}</b>\n";
        $message .= "Подсказка: выберите пакет ниже. Платежная часть пока заглушка.";

        $this->chat
            ->html($message)
            ->keyboard($this->subscriptionKeyboard($plans))
            ->send();

        $this->logOutgoing($message, ["handler_action" => "subscription"]);
    }

    public function check_vin(): void
    {
        $this->promptVinInput();
    }

    public function promptVinInput(): void
    {
        $message = "🚗 *Проверка VIN-номера*\n\n";
        $message .= "Действие: отправьте VIN.\n";
        $message .= "Подсказка: 17 символов, пример *JHMCM56557C404453*.\n";
        $message .= "Для отмены отправьте: *Отмена*.";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🏠 На главную")->action("start");
                return $keyboard;
            })
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
            $this->chat
                ->markdown(
                    "❌ *Некорректный VIN*\n\nДействие: введите VIN повторно.\nПодсказка: используйте 17 символов без I/O/Q.",
                )
                ->keyboard(function ($keyboard) {
                    $keyboard->button("🔍 Проверить VIN")->action("check_vin");
                    $keyboard->button("❓ Помощь")->action("help");
                    $keyboard->chunk(2);
                    return $keyboard;
                })
                ->send();
            $this->logOutgoing("❌ *Некорректный VIN*", [
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

        $action = $this->extractActionFromJson($callbackData);

        switch ($action) {
            case "check_vin":
                $this->promptVinInput();
                break;
            case "subscription":
                $this->subscription();
                break;
            case "help":
                $this->help();
                break;
            case "start":
                $this->start();
                break;
            default:
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
        }
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
            $remainingBefore = $this->getFullReportsRemaining();
            $fullReportAllowed = $remainingBefore > 0;
            $remainingAfterUse = $fullReportAllowed
                ? $remainingBefore - 1
                : $remainingBefore;

            $message = $this->buildVinResultMessage(
                $carData,
                $fullReportAllowed,
                $remainingAfterUse,
            );

            if ($fullReportAllowed) {
                $this->sendMakeLogo($carData);
            }

            if (($carData["error_code"] ?? "") !== "0") {
                $message .= "\n\n⚠️ API сообщило: <b>{$this->escapeHtml((string) $carData["error_text"])}</b>";
            }
        } catch (\Throwable $e) {
            Log::error("VIN API error", ["exception" => $e]);
            $message = "❌ <b>Не удалось получить данные по VIN</b>\n\n";
            $message .= "Попробуйте позже или проверьте корректность VIN.";
        }

        $messages = $this->splitLongMessage($message);
        foreach ($messages as $messagePart) {
            $this->chat->html($messagePart)->send();
        }

        $this->chat
            ->html(
                $fullReportAllowed
                    ? "Дальше: полный отчет использован, можно проверить VIN еще раз или перейти в подписку."
                    : "Дальше: можно проверить VIN еще раз или перейти в подписку.",
            )
            ->keyboard($this->vinResultKeyboard($fullReportAllowed))
            ->send();

        if ($fullReportAllowed) {
            try {
                $this->consumeFullReport($vin, $carData, $remainingAfterUse);
            } catch (\Throwable $e) {
                Log::warning("VIN full report consume failed", [
                    "bot_id" => (int) $this->bot->id,
                    "chat_model_id" => isset($this->chat->id)
                        ? (int) $this->chat->id
                        : null,
                    "telegram_chat_id" => isset($this->chat->chat_id)
                        ? (string) $this->chat->chat_id
                        : null,
                    "vin" => $vin,
                    "error" => $e->getMessage(),
                ]);
            }
        }

        $this->logOutgoing($message, ["handler_action" => "process_vin"]);
    }

    private function buildVinResultMessage(
        array $carData,
        bool $fullReportAllowed,
        int $remainingAfterUse,
    ): string
    {
        $message = "✅ <b>Результат VIN-проверки</b>\n\n";
        $message .= $fullReportAllowed
            ? "Доступен полный отчет. Осталось после списания: <b>{$remainingAfterUse}</b>\n\n"
            : "Бесплатный режим. Полный отчет доступен по подписке.\n\n";
        $sections = $carData["sections"] ?? [];

        if (!is_array($sections) || $sections === []) {
            return $this->buildLegacyVinResultMessage(
                $carData,
                $fullReportAllowed,
                $remainingAfterUse,
            );
        }

        foreach ($sections as $sectionTitle => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            $lines = [];
            foreach ($fields as $label => $value) {
                $value = trim((string) $value);
                if ($value === "" || $value === "-") {
                    continue;
                }

                $displayValue = $this->shouldRevealField(
                    (string) $label,
                    $fullReportAllowed,
                )
                    ? $this->escapeHtml($value)
                    : self::MASK_VALUE;

                $lines[] =
                    "{$this->escapeHtml((string) $label)}: <b>{$displayValue}</b>";
            }

            if ($lines === []) {
                continue;
            }

            $message .= "<b>{$this->escapeHtml((string) $sectionTitle)}</b>\n";
            $message .= implode("\n", $lines) . "\n\n";
        }

        return trim($message);
    }

    private function buildLegacyVinResultMessage(
        array $carData,
        bool $fullReportAllowed,
        int $remainingAfterUse,
    ): string
    {
        $message = "✅ <b>Результат VIN-проверки</b>\n\n";
        $message .= $fullReportAllowed
            ? "Доступен полный отчет. Осталось после списания: <b>{$remainingAfterUse}</b>\n\n"
            : "Бесплатный режим. Полный отчет доступен по подписке.\n\n";

        $fields = [
            "VIN" => $carData["vin"] ?? "-",
            "Марка" => $carData["make"] ?? "-",
            "Модель" => $carData["model"] ?? "-",
            "Год модели" => $carData["model_year"] ?? "-",
            "Тип ТС" => $carData["vehicle_type"] ?? "-",
            "Класс кузова" => $carData["body_class"] ?? "-",
            "Двигатель" =>
                trim(
                    (string) ($carData["engine_cylinders"] ?? "-") .
                        " / " .
                        (string) ($carData["engine_liters"] ?? "-") .
                        "L",
                ),
            "Топливо" => $carData["fuel_type"] ?? "-",
            "Страна сборки" => $carData["plant_country"] ?? "-",
            "Завод" => $carData["plant_company"] ?? "-",
        ];

        $lines = [];
        foreach ($fields as $label => $value) {
            $value = trim((string) $value);
            if ($value === "" || $value === "-" || $value === "- / -L") {
                continue;
            }

            $displayValue = $this->shouldRevealField(
                (string) $label,
                $fullReportAllowed,
            )
                ? $this->escapeHtml($value)
                : self::MASK_VALUE;

            $lines[] =
                "{$this->escapeHtml((string) $label)}: <b>{$displayValue}</b>";
        }

        if ($lines === []) {
            return "❌ <b>Не удалось разобрать данные по VIN</b>\n\nПопробуйте повторить запрос позже.";
        }

        return $message . implode("\n", $lines);
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

        $make = trim((string) ($carData["make"] ?? ""));
        $model = trim((string) ($carData["model"] ?? ""));
        $caption = trim("{$make} {$model}");
        $caption =
            $caption !== ""
                ? "🚗 <b>{$this->escapeHtml($caption)}</b>"
                : "🚗 <b>VIN отчет</b>";

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

    private function shouldRevealField(string $label, bool $fullReportAllowed): bool
    {
        return $fullReportAllowed || in_array($label, self::FREE_VISIBLE_FIELDS, true);
    }

    private function consumeFullReport(
        string $vin,
        array $carData,
        int $remainingAfterUse,
    ): void {
        if (!isset($this->chat->full_reports_remaining)) {
            return;
        }

        $before = (int) $this->chat->full_reports_remaining;
        $after = max(0, $remainingAfterUse);

        $this->chat->full_reports_remaining = $after;
        $this->chat->save();

        SubscriptionTransaction::query()->create([
            "telegraph_bot_id" => (int) $this->bot->id,
            "telegraph_chat_id" => isset($this->chat->id) ? (int) $this->chat->id : null,
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

        Log::info("VIN full report consumed", [
            "bot_id" => (int) $this->bot->id,
            "chat_model_id" => isset($this->chat->id)
                ? (int) $this->chat->id
                : null,
            "telegram_chat_id" => isset($this->chat->chat_id)
                ? (string) $this->chat->chat_id
                : null,
            "vin" => $vin,
            "make" => $carData["make"] ?? null,
            "model" => $carData["model"] ?? null,
            "before" => $before,
            "after" => $after,
        ]);
    }

    private function vinResultKeyboard(bool $fullReportAllowed): Keyboard
    {
        return $fullReportAllowed
            ? $this->fullReportKeyboard()
            : $this->freeReportKeyboard();
    }

    private function freeReportKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить еще")->action("check_vin"),
                Button::make("💳 Подписка")->action("subscription"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    private function fullReportKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить еще")->action("check_vin"),
                Button::make("💳 Подписка")->action("subscription"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    private function subscriptionKeyboard($plans): Keyboard
    {
        $buttons = [];

        foreach ($plans as $plan) {
            $buttons[] = Button::make(
                $this->formatSubscriptionPlanLabel($plan),
            )->action("subscription_plan_{$plan->id}");
        }

        $buttons[] = Button::make("🏠 На главную")->action("start");

        return Keyboard::make()->buttons($buttons)->chunk(2);
    }

    private function subscriptionPlan(int $planId): void
    {
        $plan = $this->getSubscriptionPlans()->firstWhere("id", $planId);

        if (!$plan) {
            $message =
                "💳 <b>Подписка</b>\n\nВыбранный пакет не найден или неактивен.";

            $this->chat
                ->html($message)
                ->keyboard($this->subscriptionKeyboard($this->getSubscriptionPlans()))
                ->send();

            $this->logOutgoing($message, [
                "handler_action" => "subscription_plan_missing",
                "plan_id" => $planId,
            ]);

            return;
        }

        $count = (int) $plan->report_count;
        $price = number_format((float) $plan->price_rub, 0, ",", " ");
        $before = $this->getFullReportsRemaining();
        $after = $before + $count;

        DB::transaction(function () use ($plan, $count, $price, $before, $after): void {
            $this->chat->full_reports_remaining = $after;
            $this->chat->save();

            SubscriptionTransaction::query()->create([
                "telegraph_bot_id" => (int) $this->bot->id,
                "telegraph_chat_id" => isset($this->chat->id)
                    ? (int) $this->chat->id
                    : null,
                "subscription_plan_id" => $plan->id,
                "transaction_type" => "purchase_simulated",
                "reports_before" => $before,
                "reports_delta" => $count,
                "reports_after" => $after,
                "amount_rub" => (float) $plan->price_rub,
                "status" => "done",
                "comment" => "Имитация покупки подписки из Telegram",
                "meta" => [
                    "plan_name" => $plan->name,
                ],
            ]);

            SubscriptionTransaction::query()->create([
                "telegraph_bot_id" => (int) $this->bot->id,
                "telegraph_chat_id" => isset($this->chat->id)
                    ? (int) $this->chat->id
                    : null,
                "subscription_plan_id" => $plan->id,
                "transaction_type" => "credit",
                "reports_before" => $before,
                "reports_delta" => $count,
                "reports_after" => $after,
                "amount_rub" => null,
                "status" => "done",
                "comment" => "Начисление отчетов после имитации покупки",
                "meta" => [
                    "plan_name" => $plan->name,
                ],
            ]);
        });

        $message =
            "💳 <b>Подписка</b>\n\n" .
            "Выбран пакет: <b>" .
            $this->escapeHtml((string) $plan->name) .
            "</b>\n" .
            "Количество отчетов: <b>{$count}</b>\n" .
            "Стоимость: <b>{$price} ₽</b>\n\n" .
            "Подписка зачислена в тестовом режиме.\n" .
            "Баланс отчетов: <b>{$before}</b> → <b>{$after}</b>.";

        $this->chat
            ->html($message)
            ->keyboard($this->subscriptionKeyboard($this->getSubscriptionPlans()))
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
            "bot_id" => (int) $this->bot->id,
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

    private function formatSubscriptionPlanLabel(SubscriptionPlan $plan): string
    {
        $count = (int) $plan->report_count;
        $suffix = $count === 1 ? "отчет" : "отчетов";
        $price = number_format((float) $plan->price_rub, 0, ",", " ");

        return "{$count} {$suffix} = {$price}р";
    }

    private function getSubscriptionPlans()
    {
        $plans = SubscriptionPlan::query()
            ->active()
            ->where(function ($query) {
                $query
                    ->whereNull("telegraph_bot_id")
                    ->orWhere("telegraph_bot_id", $this->bot->id);
            })
            ->orderByRaw("CASE WHEN telegraph_bot_id IS NULL THEN 1 ELSE 0 END")
            ->orderBy("sort_order")
            ->orderBy("report_count")
            ->get();

        return $plans
            ->groupBy("report_count")
            ->map(fn ($group) => $group->first())
            ->values();
    }

    private function getFullReportsRemaining(): int
    {
        return max(0, (int) ($this->chat->full_reports_remaining ?? 0));
    }

    private function splitLongMessage(string $message): array
    {
        $limit = 3500;
        if (mb_strlen($message) <= $limit) {
            return [$message];
        }

        $parts = [];
        $current = "";

        foreach (explode("\n\n", $message) as $block) {
            $candidate = $current === "" ? $block : "{$current}\n\n{$block}";
            if (mb_strlen($candidate) <= $limit) {
                $current = $candidate;
                continue;
            }

            if ($current !== "") {
                $parts[] = $current;
            }

            $current = $block;
        }

        if ($current !== "") {
            $parts[] = $current;
        }

        return $parts;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }

    public function handleUnknownCommand(Stringable $text): void
    {
        $this->chat
            ->markdown(
                "❌ Неизвестная команда в VIN-декодере: *{$text}*\n\nИспользуйте /help для справки.",
            )
            ->keyboard(function ($keyboard) {
                $keyboard->button("🔍 Проверить VIN")->action("check_vin");
                $keyboard->button("❓ Помощь")->action("help");
                return $keyboard;
            })
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
        if (isset($this->callbackQueryId) && $this->callbackQueryId) {
            $this->bot->replyWebhook($this->callbackQueryId, $message)->send();
        }
    }

    private function extractActionFromJson(mixed $data): string
    {
        if (is_object($data) && method_exists($data, "get")) {
            try {
                $action = $data->get("action");
                if (is_string($action) && $action !== "") {
                    return $action;
                }
            } catch (\Throwable $e) {
                Log::debug("VIN callback parse object error", [
                    "error" => $e->getMessage(),
                ]);
            }
        }

        if (is_array($data) && isset($data["action"])) {
            return (string) $data["action"];
        }

        if (is_string($data)) {
            $json = trim($data);
            if (str_starts_with($json, "{")) {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && isset($decoded["action"])) {
                    return (string) $decoded["action"];
                }
            }

            $action = str_replace('{"action":"', "", $json);
            $action = str_replace('"}', "", $action);
            $action = trim($action, '"\'');
            if ($action !== "") {
                return $action;
            }
        }

        return "";
    }

    private function logOutgoing(string $message, array $meta = []): void
    {
        app(ChatLogger::class)->logOutbound(
            (int) $this->bot->id,
            isset($this->chat->id) ? (int) $this->chat->id : null,
            isset($this->chat->chat_id) ? (string) $this->chat->chat_id : null,
            $message,
            $meta,
        );
    }
}
