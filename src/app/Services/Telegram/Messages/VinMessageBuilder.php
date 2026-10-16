<?php

namespace App\Services\Telegram\Messages;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class VinMessageBuilder
{
    private const FREE_VISIBLE_FIELDS = ["VIN", "Марка", "Модель", "Год модели"];
    private const MASK_VALUE = "**********";

    public function startText(): string
    {
        return "🚗 *VIN-декодер*\n\n" .
            "Отправьте VIN из 17 символов текстом или пришлите четкое фото номера.";
    }

    public function helpText(): string
    {
        return "📚 *Помощь*\n\n" .
            "Команды:\n" .
            "/start — Главное меню\n" .
            "/help — Справка\n" .
            "/vin — Ввести VIN\n\n" .
            "VIN должен содержать 17 символов: A-Z и 0-9, без I/O/Q.\n\n" .
            "Бесплатно доступны: VIN, марка, модель и год выпуска.\n" .
            "Полный отчет содержит дополнительные данные и списывается с баланса отчетов.\n\n" .
            "Для отмены отправьте: *Отмена*.";
    }

    public function promptVinInputText(): string
    {
        return "🚗 *Проверка VIN-номера*\n\n" .
            "Отправьте VIN из 17 символов текстом или пришлите четкое фото номера.\n" .
            "Распознанный с фото номер нужно будет подтвердить.\n" .
            "Пробелы и дефисы можно не удалять.\n" .
            "Для отмены отправьте: *Отмена*.";
    }

    public function invalidVinText(): string
    {
        return "❌ *Некорректный VIN*\n\n" .
            "VIN должен содержать 17 символов.\n" .
            "Буквы I, O и Q не используются.\n\n" .
            "Отправьте VIN еще раз.";
    }

    public function photoVinCandidateText(string $vin): string
    {
        return "🔎 Найден VIN: <code>{$this->escapeHtml($vin)}</code>\n\n" .
            "Проверьте символы на фото и подтвердите номер перед проверкой.";
    }

    public function photoVinNotFoundText(): string
    {
        return "Не удалось уверенно распознать VIN на фото.\n\n" .
            "Пришлите более четкое фото или отправьте VIN текстом.";
    }

    public function photoVinExpiredText(): string
    {
        return "Срок подтверждения VIN истек. Отправьте фото или VIN заново.";
    }

    public function photoVinConfirmationKeyboard(string $token): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("✅ Подтвердить VIN")
                    ->action("confirm_photo_vin")
                    ->param("token", $token),
                Button::make("✏️ Ввести вручную")
                    ->action("cancel_photo_vin")
                    ->param("token", $token),
            ])
            ->chunk(2);
    }

    public function vinResultText(array $carData, bool $fullReportAllowed, int $remainingAfterUse): string
    {
        $message = "✅ <b>Результат VIN-проверки</b>\n\n";
        $message .= $fullReportAllowed
            ? "Полный отчет. Баланс: <b>{$remainingAfterUse}</b>\n\n"
            : "Базовые данные. Дополнительные поля доступны из баланса отчетов.\n\n";

        $sections = $carData["sections"] ?? [];
        if (!is_array($sections) || $sections === []) {
            return $this->legacyVinResultText($carData, $fullReportAllowed, $remainingAfterUse);
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

                $displayValue = $this->shouldRevealField((string) $label, $fullReportAllowed)
                    ? $this->escapeHtml($value)
                    : self::MASK_VALUE;

                $lines[] = "{$this->escapeHtml((string) $label)}: <b>{$displayValue}</b>";
            }

            if ($lines === []) {
                continue;
            }

            $message .= "<b>{$this->escapeHtml((string) $sectionTitle)}</b>\n";
            $message .= implode("\n", $lines) . "\n\n";
        }

        return trim($message);
    }

    public function legacyVinResultText(array $carData, bool $fullReportAllowed, int $remainingAfterUse): string
    {
        $message = "✅ <b>Результат VIN-проверки</b>\n\n";
        $message .= $fullReportAllowed
            ? "Полный отчет. Баланс: <b>{$remainingAfterUse}</b>\n\n"
            : "Базовые данные. Дополнительные поля доступны из баланса отчетов.\n\n";

        $fields = [
            "VIN" => $carData["vin"] ?? "-",
            "Марка" => $carData["make"] ?? "-",
            "Модель" => $carData["model"] ?? "-",
            "Год модели" => $carData["model_year"] ?? "-",
            "Тип ТС" => $carData["vehicle_type"] ?? "-",
            "Класс кузова" => $carData["body_class"] ?? "-",
            "Двигатель" => trim(
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

            $displayValue = $this->shouldRevealField((string) $label, $fullReportAllowed)
                ? $this->escapeHtml($value)
                : self::MASK_VALUE;

            $lines[] = "{$this->escapeHtml((string) $label)}: <b>{$displayValue}</b>";
        }

        if ($lines === []) {
            return "❌ <b>Не удалось разобрать данные по VIN</b>\n\nПопробуйте повторить запрос позже.";
        }

        return $message . implode("\n", $lines);
    }

    public function vinResultNextText(bool $fullReportAllowed): string
    {
        return "Выберите следующее действие.";
    }

    public function vinFailureText(): string
    {
        return "❌ <b>Не удалось получить данные по VIN</b>\n\n" .
            "Попробуйте позже или проверьте корректность VIN.";
    }

    public function makeLogoCaption(array $carData): string
    {
        $make = trim((string) ($carData["make"] ?? ""));
        $model = trim((string) ($carData["model"] ?? ""));
        $caption = trim("{$make} {$model}");

        return $caption !== ""
            ? "🚗 <b>{$this->escapeHtml($caption)}</b>"
            : "🚗 <b>VIN отчет</b>";
    }

    public function mainMenuKeyboard(?int $botId = null, ?string $chatId = null, ?int $reportsRemaining = null): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить VIN")->action("check_vin"),
                Button::make("❓ Помощь")->action("help"),
                Button::make($this->subscriptionButtonLabel($reportsRemaining))->webApp($this->miniAppUrl($botId, $chatId)),
            ])
            ->chunk(2);
    }

    public function helpKeyboard(?int $botId = null, ?string $chatId = null, ?int $reportsRemaining = null): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить VIN")->action("check_vin"),
                Button::make($this->subscriptionButtonLabel($reportsRemaining))->webApp($this->miniAppUrl($botId, $chatId)),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    public function promptVinKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([Button::make("🏠 На главную")->action("start")])
            ->chunk(2);
    }

    public function invalidVinKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить VIN")->action("check_vin"),
                Button::make("❓ Помощь")->action("help"),
            ])
            ->chunk(2);
    }

    public function vinResultKeyboard(bool $fullReportAllowed, ?int $botId = null, ?string $chatId = null, ?int $reportsRemaining = null): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить еще")->action("check_vin"),
                Button::make($this->subscriptionButtonLabel($reportsRemaining))->webApp($this->miniAppUrl($botId, $chatId)),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    public function unknownCommandKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить VIN")->action("check_vin"),
                Button::make("❓ Помощь")->action("help"),
            ])
            ->chunk(2);
    }

    public function miniAppUrl(?int $botId = null, ?string $chatId = null): string
    {
        $url = "https://x-bot.su/vin-mini-app";

        if ($botId !== null && $botId > 0 && $chatId !== null && $chatId !== '') {
            $url .= "?bot_id=" . urlencode((string) $botId) . "&chat_id=" . urlencode((string) $chatId);
        }

        return $url;
    }

    public function subscriptionButtonLabel(?int $reportsRemaining = null): string
    {
        $count = $reportsRemaining ?? 0;

        return "💳 Пакеты (" . $count . " отч.)";
    }

    public function splitLongMessage(string $message): array
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

    public function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }

    private function shouldRevealField(string $label, bool $fullReportAllowed): bool
    {
        return $fullReportAllowed || in_array($label, self::FREE_VISIBLE_FIELDS, true);
    }
}
