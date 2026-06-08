<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\ChatLogger;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Log;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use App\Services\Telegram\Services\VinService;

class VinBotHandler extends WebhookHandler
{
    private const CANCEL_KEYWORDS = ["отмена", "cancel", "stop", "выход"];

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
        $message .= "/vin — Ввести VIN\n\n";
        $message .= "Формат VIN: 17 символов (A-Z, 0-9, без I/O/Q).\n";
        $message .= "Пример: *JHMCM56557C404453*.\n";
        $message .= "Для отмены отправьте: *Отмена*.";

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
            case "help":
                $this->help();
                break;
            case "start":
                $this->start();
                break;
            default:
                $this->chat->html("Действие: {$action}")->send();
                $this->logOutgoing("Действие: {$action}", [
                    "handler_action" => "unknown_callback",
                ]);
        }
    }

    private function processVin(string $vin): void
    {
        try {
            /** @var VinService $vinService */
            $vinService = app(VinService::class);
            $carData = $vinService->decode((int) $this->bot->id, $vin);

            $message = $this->buildVinResultMessage($carData);

            if (($carData["error_code"] ?? "") !== "0") {
                $message .= "\n\n⚠️ API сообщило: *{$carData["error_text"]}*";
            }
        } catch (\Throwable $e) {
            Log::error("VIN API error", ["exception" => $e]);
            $message = "❌ *Не удалось получить данные по VIN*\n\n";
            $message .= "Попробуйте позже или проверьте корректность VIN.";
        }

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🔍 Проверить еще")->action("check_vin");
                $keyboard->button("🏠 На главную")->action("start");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();

        $this->logOutgoing($message, ["handler_action" => "process_vin"]);
    }

    private function buildVinResultMessage(array $carData): string
    {
        $message = "✅ *Результат VIN-проверки*\n\n";
        $sections = $carData["sections"] ?? [];

        if (!is_array($sections) || $sections === []) {
            return $this->buildLegacyVinResultMessage($carData);
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

                $lines[] = "{$label}: *{$this->escapeMarkdown($value)}*";
            }

            if ($lines === []) {
                continue;
            }

            $message .= "*{$sectionTitle}*\n";
            $message .= implode("\n", $lines) . "\n\n";
        }

        return trim($message);
    }

    private function buildLegacyVinResultMessage(array $carData): string
    {
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

            $lines[] = "{$label}: *{$this->escapeMarkdown($value)}*";
        }

        if ($lines === []) {
            return "❌ *Не удалось разобрать данные по VIN*\n\nПопробуйте повторить запрос позже.";
        }

        return "✅ *Результат VIN-проверки*\n\n" . implode("\n", $lines);
    }

    private function escapeMarkdown(string $value): string
    {
        return str_replace(
            ["\\", "*", "_", "`", "["],
            ["\\\\", "\\*", "\\_", "\\`", "\\["],
            $value,
        );
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
