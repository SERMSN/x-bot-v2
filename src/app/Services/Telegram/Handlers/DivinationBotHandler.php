<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\Support\CallbackAction;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Stringable;

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

    private const CARDS = [
        [
            "slug" => "sun",
            "local_image" => "divination/cards/sun.jpg",
            "name" => "Солнце",
            "meaning" => "Карта ясности, энергии и уверенного движения вперед.",
            "description" =>
                "Сейчас удачный момент действовать открыто, не прятать идеи и показывать свои сильные стороны. То, что долго было неочевидным, начинает проясняться.",
        ],
        [
            "slug" => "moon",
            "local_image" => "divination/cards/moon.jpg",
            "name" => "Луна",
            "meaning" =>
                "Карта интуиции, скрытых мотивов и внутреннего поиска.",
            "description" =>
                "Не все в ситуации лежит на поверхности. Полезно не спешить с выводами, прислушаться к ощущениям и проверить детали перед решением.",
        ],
        [
            "slug" => "star",
            "local_image" => "divination/cards/star.jpg",
            "name" => "Звезда",
            "meaning" =>
                "Карта надежды, восстановления и спокойной уверенности.",
            "description" =>
                "Даже если путь сейчас кажется длинным, направление выбрано верно. Сохраняйте ритм, потому что результаты появятся через последовательность, а не через рывок.",
        ],
        [
            "slug" => "wheel-of-fortune",
            "local_image" => "divination/cards/wheel-of-fortune.jpg",
            "name" => "Колесо Фортуны",
            "meaning" => "Карта перемен, поворота событий и нового цикла.",
            "description" =>
                "Обстоятельства могут быстро измениться, и важно не цепляться за старую схему. Гибкость и готовность поймать момент сейчас важнее жесткого контроля.",
        ],
        [
            "slug" => "emperor",
            "local_image" => "divination/cards/emperor.jpg",
            "name" => "Император",
            "meaning" => "Карта структуры, порядка и ответственности.",
            "description" =>
                "Ситуацию лучше решать через план, границы и четкие шаги. Уверенность придет не от вдохновения, а от дисциплины и понятной опоры.",
        ],
        [
            "slug" => "magician",
            "local_image" => "divination/cards/magician.jpg",
            "name" => "Маг",
            "meaning" =>
                "Карта инициативы, мастерства и запуска нового действия.",
            "description" =>
                "У вас уже есть достаточно ресурсов, чтобы начать. Не ждите идеального момента: первый конкретный шаг даст больше, чем долгие сомнения.",
        ],
        [
            "slug" => "hermit",
            "local_image" => "divination/cards/hermit.jpg",
            "name" => "Отшельник",
            "meaning" => "Карта паузы, наблюдения и внутренней мудрости.",
            "description" =>
                "Лучший ответ сейчас рождается в тишине, а не в шуме мнений. Полезно сократить внешнюю суету и дать себе время все обдумать.",
        ],
        [
            "slug" => "strength",
            "local_image" => "divination/cards/strength.jpg",
            "name" => "Сила",
            "meaning" =>
                "Карта выдержки, самообладания и мягкой внутренней мощи.",
            "description" =>
                "Сейчас побеждает не давление, а спокойная устойчивость. Если действовать без резкости, но настойчиво, ситуация постепенно повернется в вашу пользу.",
        ],
    ];

    public function start(): void
    {
        $message = "🔮 *Бот-оракул*\n\n";
        $message .= "Действие: выберите формат предсказания.";

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard($this->mainMenuKeyboard())
            ->send();
    }

    public function help(): void
    {
        $message = "📚 *Помощь*\n\n";
        $message .= "Команды:\n";
        $message .= "/start — Главное меню\n";
        $message .= "/help — Справка\n";
        $message .= "/predict — Случайное предсказание\n";
        $message .= "/card — Карта предсказания\n\n";
        $message .= "Возможности:\n";
        $message .= "• Случайное короткое предсказание\n";
        $message .= "• Карты из локальной колоды проекта\n";
        $message .= "• Быстрый переход между двумя типами предсказаний";

        $this->chat
            ->markdown($message)
            ->keyboard($this->helpKeyboard())
            ->send();
    }

    public function predict(): void
    {
        $this->random_prediction();
    }

    public function card(): void
    {
        $this->random_card_prediction();
    }

    public function random_prediction(bool $editCurrent = false): void
    {
        $prediction = self::PREDICTIONS[array_rand(self::PREDICTIONS)];

        $message = "🔯 *Ваше предсказание*\n\n";
        $message .= $prediction;

        $this->messageTarget($editCurrent)
            ->markdown($message)
            ->keyboard($this->predictionResultKeyboard())
            ->send();
    }

    public function random_card_prediction(bool $editCurrent = false): void
    {
        $card = self::CARDS[array_rand(self::CARDS)];
        $imagePath = $this->resolveCardImagePath($card);

        $message = "🃏 *Ваша карта предсказания*\n\n";
        $message .= "*Карта:* {$card["name"]}\n";
        $message .= "*Значение:* {$card["meaning"]}\n\n";
        $message .= "{$card["description"]}";

        $this->chat
            ->markdown($message)
            ->photo($imagePath)
            ->keyboard($this->cardResultKeyboard())
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

        $action = CallbackAction::parse($callbackData);

        switch ($action) {
            case "random_prediction":
            case "random_prediction_new":
                $this->random_prediction(false);
                break;
            case "random_card_prediction":
                $this->random_card_prediction(true);
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

    private function messageTarget(bool $editCurrent): mixed
    {
        return $editCurrent && isset($this->messageId)
            ? $this->chat->edit($this->messageId)
            : $this->chat;
    }

    private function resolveCardImagePath(array $card): string
    {
        $path = $this->publicAssetPath((string) $card["local_image"]);

        if (!File::isFile($path)) {
            throw new \RuntimeException(
                "Divination card image is missing: {$path}",
            );
        }

        return $path;
    }

    private function publicAssetPath(string $relativePath): string
    {
        $configuredBasePath = trim((string) env("PUBLIC_ASSETS_PATH"));
        if ($configuredBasePath !== "") {
            $basePath = $configuredBasePath;
        } else {
            $hostingMode = $this->readHostingMode();
            if ($hostingMode === 1) {
                $hostingBasePath = dirname(base_path()) . DIRECTORY_SEPARATOR . "public_html";
                $basePath = is_dir($hostingBasePath) ? $hostingBasePath : public_path();
            } else {
                $basePath = public_path();
            }
        }

        $basePath = rtrim($basePath, "/\\");

        return $basePath . DIRECTORY_SEPARATOR . ltrim($relativePath, "/\\");
    }

    private function readHostingMode(): int
    {
        foreach ([base_path(".env"), dirname(base_path()) . DIRECTORY_SEPARATOR . ".env"] as $envPath) {
            if (!is_file($envPath) || !is_readable($envPath)) {
                continue;
            }

            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            $prefix = "HOSTING=";
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === "" || str_starts_with($line, "#")) {
                    continue;
                }
                if (!str_starts_with($line, $prefix)) {
                    continue;
                }

                $value = trim(substr($line, strlen($prefix)));
                $value = trim($value, " \t\n\r\0\x0B\"'");

                return (int) $value;
            }
        }

        return (int) env("HOSTING", 0);
    }

    private function mainMenuKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔯 Генерация случайного предсказания")->action(
                    "random_prediction",
                ),
                Button::make("🃏 Генерация карты")->action(
                    "random_card_prediction",
                ),
                Button::make("❓ Помощь")->action("help"),
            ])
            ->chunk(1);
    }

    private function helpKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔯 Случайное предсказание")->action(
                    "random_prediction",
                ),
                Button::make("🃏 Карта предсказания")->action(
                    "random_card_prediction",
                ),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(1);
    }

    private function predictionResultKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔯 Еще предсказание")->action(
                    "random_prediction",
                ),
                Button::make("🃏 Перейти к карте")->action(
                    "random_card_prediction",
                ),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(1);
    }

    private function cardResultKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🃏 Еще карта")->action("random_card_prediction"),
                Button::make("🔯 Перейти к предсказанию")->action(
                    "random_prediction_new",
                ),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(1);
    }

    private function ackCallbackQuery(string $message = ""): void
    {
        if (isset($this->callbackQueryId) && $this->callbackQueryId) {
            $this->bot->replyWebhook($this->callbackQueryId, $message)->send();
        }
    }
}
