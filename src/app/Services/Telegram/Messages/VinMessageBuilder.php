<?php

namespace App\Services\Telegram\Messages;

use App\Models\SubscriptionPlan;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Collection;

class VinMessageBuilder
{
    private const FREE_VISIBLE_FIELDS = ["VIN", "Марка", "Модель", "Год модели"];
    private const MASK_VALUE = "**********";

    public function startText(): string
    {
        return "🚗 *VIN-бот*\n\n" .
            "Действие: проверка VIN.\n" .
            "Подсказка: нажмите кнопку и отправьте VIN из 17 символов.";
    }

    public function helpText(): string
    {
        return "📚 *Помощь*\n\n" .
            "Команды:\n" .
            "/start — Главное меню\n" .
            "/help — Справка\n" .
            "/vin — Ввести VIN\n" .
            "/subscription — Подписка\n\n" .
            "Формат VIN: 17 символов (A-Z, 0-9, без I/O/Q).\n" .
            "Пример: *JHMCM56557C404453*.\n" .
            "Для отмены отправьте: *Отмена*.\n\n" .
            "📋 *Что можно получить по VIN*\n" .
            "• Основное: VIN, марка, модель, год, тип ТС, кузов, привод.\n" .
            "• Двигатель и трансмиссия: тип двигателя, объем, производитель, топливо, коробка, число передач, экостандарт, расход, CO2.\n" .
            "• Производитель: название, адрес, страна сборки.\n" .
            "• Кузов и размеры: двери, места, колеса, оси, база, высота, длина, ширина, колея.\n" .
            "• Масса и эксплуатация: скорость, масса, нагрузка на крышу, разрешенная масса прицепа.\n" .
            "• Ходовая и оснащение: ABS, тормоза, подвеска, рулевое управление, диски, шины.\n" .
            "• VIN-данные: Vehicle ID, контрольная цифра, серийный номер.\n\n" .
            "Фактический набор зависит от того, какие поля Vincario вернет по конкретному VIN.\n\n" .
            "Подписка: /subscription.";
    }

    public function promptVinInputText(): string
    {
        return "🚗 *Проверка VIN-номера*\n\n" .
            "Действие: отправьте VIN.\n" .
            "Подсказка: 17 символов, пример *JHMCM56557C404453*.\n" .
            "Для отмены отправьте: *Отмена*.";
    }

    public function invalidVinText(): string
    {
        return "❌ *Некорректный VIN*\n\n" .
            "Действие: введите VIN повторно.\n" .
            "Подсказка: используйте 17 символов без I/O/Q.";
    }

    public function subscriptionText(int $remaining): string
    {
        return "💳 <b>Подписка</b>\n\n" .
            "Действие: пополнение полного отчета.\n" .
            "Осталось полных отчетов: <b>{$remaining}</b>\n" .
            "Подсказка: выберите пакет ниже. Платежная часть пока заглушка.";
    }

    public function subscriptionPlanMissingText(): string
    {
        return "💳 <b>Подписка</b>\n\nВыбранный пакет не найден или неактивен.";
    }

    public function subscriptionPlanPurchasedText(SubscriptionPlan $plan, int $before, int $after): string
    {
        $count = (int) $plan->report_count;
        $price = number_format((float) $plan->price_rub, 0, ",", " ");

        return "💳 <b>Подписка</b>\n\n" .
            "Выбран пакет: <b>" .
            $this->escapeHtml((string) $plan->name) .
            "</b>\n" .
            "Количество отчетов: <b>{$count}</b>\n" .
            "Стоимость: <b>{$price} ₽</b>\n\n" .
            "Подписка зачислена в тестовом режиме.\n" .
            "Баланс отчетов: <b>{$before}</b> → <b>{$after}</b>.";
    }

    public function vinResultText(array $carData, bool $fullReportAllowed, int $remainingAfterUse): string
    {
        $message = "✅ <b>Результат VIN-проверки</b>\n\n";
        $message .= $fullReportAllowed
            ? "Доступен полный отчет. Осталось после списания: <b>{$remainingAfterUse}</b>\n\n"
            : "Бесплатный режим. Полный отчет доступен по подписке.\n\n";

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
            ? "Доступен полный отчет. Осталось после списания: <b>{$remainingAfterUse}</b>\n\n"
            : "Бесплатный режим. Полный отчет доступен по подписке.\n\n";

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
        return $fullReportAllowed
            ? "Дальше: полный отчет использован, можно проверить VIN еще раз или перейти в подписку."
            : "Дальше: можно проверить VIN еще раз или перейти в подписку.";
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

    public function mainMenuKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить VIN")->action("check_vin"),
                Button::make("❓ Помощь")->action("help"),
                Button::make("💳 Подписка")->action("subscription"),
            ])
            ->chunk(2);
    }

    public function helpKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить VIN")->action("check_vin"),
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

    public function vinResultKeyboard(bool $fullReportAllowed): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔍 Проверить еще")->action("check_vin"),
                Button::make("💳 Подписка")->action("subscription"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    /**
     * @param Collection<int, SubscriptionPlan> $plans
     */
    public function subscriptionKeyboard(Collection $plans): Keyboard
    {
        $buttons = [];

        foreach ($plans as $plan) {
            $buttons[] = Button::make($this->subscriptionPlanLabel($plan))
                ->action("subscription_plan_{$plan->id}");
        }

        $buttons[] = Button::make("📱 Mini App")
            ->webApp('https://x-bot.su/vin-mini-app');

        $buttons[] = Button::make("🏠 На главную")->action("start");

        return Keyboard::make()->buttons($buttons)->chunk(2);
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

    private function subscriptionPlanLabel(SubscriptionPlan $plan): string
    {
        $count = (int) $plan->report_count;
        $suffix = $count === 1 ? "отчет" : "отчетов";
        $price = number_format((float) $plan->price_rub, 0, ",", " ");

        return "{$count} {$suffix} = {$price}р";
    }

    private function shouldRevealField(string $label, bool $fullReportAllowed): bool
    {
        return $fullReportAllowed || in_array($label, self::FREE_VISIBLE_FIELDS, true);
    }
}
