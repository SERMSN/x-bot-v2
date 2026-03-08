<?php

namespace App\Services\Telegram\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Log;

class DivinationBotHandler extends WebhookHandler
{
    private const PREDICTIONS = [
        "Сегодня подходящий день для нового шага.",
        "Терпение сейчас принесет лучший результат.",
        "Скоро появится полезная возможность.",
        "Доверьтесь интуиции, но проверьте детали.",
        "Не откладывайте важный разговор.",
        "Небольшое усилие даст заметный прогресс.",
        "Случайная встреча приведет к хорошей идее.",
        "Ваше спокойствие сегодня станет вашим преимуществом.",
        "Лучший ответ придет после короткой паузы.",
        "Сфокусируйтесь на одном деле, и результат удивит.",
        "Вечером появится повод для радости.",
        "Полезно завершить то, что давно откладывали.",
        "Поддержка придет от человека, от которого не ждете.",
        "Смелое решение окажется верным.",
        "Маленький риск откроет большую возможность.",
        "Внимание к деталям убережет от ошибки.",
        "Сегодня лучше слушать, чем спорить.",
        "Ваши слова окажутся особенно важными.",
        "Новый навык пригодится раньше, чем кажется.",
        "Планы ускорятся, если сделать первый шаг сейчас.",
        "Хорошие новости ближе, чем вы думаете.",
        "Старый контакт снова станет полезным.",
        "Отпустите лишнее, чтобы увидеть главное.",
        "Утро принесет ясность в сложном вопросе.",
        "Порядок в мелочах даст крупный эффект.",
        "Стоит довериться проверенному пути.",
        "Не бойтесь попросить помощь, это ускорит результат.",
        "Сегодня удача на стороне настойчивых.",
        "Приятный сюрприз ждет вас в ближайшие дни.",
        "Один честный разговор изменит многое.",
        "То, что кажется паузой, на самом деле подготовка.",
        "Энергии хватит на большее, чем вы планировали.",
        "Ваш выбор сегодня повлияет на важный поворот.",
        "День подходит для начала нового проекта.",
    ];

    public function start(): void
    {
        $message = "🔮 *Бот-оракул*\n\n";
        $message .= "Выберите действие в меню ниже.";

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard(
                Keyboard::make()
                    ->buttons([
                        Button::make(
                            "🔯 Генерация случайного предсказания",
                        )->action("random_prediction"),
                        Button::make("❓ Помощь")->action("help"),
                    ])
                    ->chunk(1),
            )
            ->send();
    }

    public function help(): void
    {
        $message = "📚 *Помощь*\n\n";
        $message .= "Команды:\n";
        $message .= "/start — Главное меню\n";
        $message .= "/help — Справка\n";
        $message .= "/predict — Случайное предсказание";

        $this->chat
            ->markdown($message)
            ->keyboard(
                Keyboard::make()
                    ->buttons([
                        Button::make(
                            "♉ Генерация случайного предсказания",
                        )->action("random_prediction"),
                        Button::make("🏠 На главную")->action("start"),
                    ])
                    ->chunk(2),
            )
            ->send();
    }

    public function predict(): void
    {
        $this->random_prediction();
    }

    public function random_prediction(bool $editCurrent = false): void
    {
        $prediction = self::PREDICTIONS[array_rand(self::PREDICTIONS)];

        $message = "🔽 *Ваше предсказание*\n\n";
        $message .= $prediction;

        $telegraph =
            $editCurrent && isset($this->messageId)
                ? $this->chat->edit($this->messageId)
                : $this->chat;

        $telegraph
            ->markdown($message)
            ->keyboard(
                Keyboard::make()
                    ->buttons([
                        Button::make("🔯 Еще предсказание")->action(
                            "random_prediction",
                        ),
                        Button::make("🏠 На главную")->action("start"),
                    ])
                    ->chunk(1),
            )
            ->send();
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
            case "random_prediction":
                $this->random_prediction(true);
                break;
            case "help":
                $this->help();
                break;
            case "start":
                $this->start();
                break;
            default:
                $this->start();
                break;
        }
    }

    protected function handleChatMessage(Stringable $text): void
    {
        if ($text->startsWith("/")) {
            parent::handleChatMessage($text);
            return;
        }

        $this->start();
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
                Log::debug("Divination callback parse object error", [
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
}
