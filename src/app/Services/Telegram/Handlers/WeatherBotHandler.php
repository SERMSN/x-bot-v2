<?php
namespace App\Services\Telegram\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Stringable;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use App\Services\Telegram\Services\WeatherService;
use Illuminate\Support\Facades\Log;

class WeatherBotHandler extends WebhookHandler
{
    private const CANCEL_KEYWORDS = ["отмена", "cancel", "stop", "выход"];

    public function start(): void
    {
        $message = "🌤️ *Погодный бот*\n\n";
        $message .= "Действие: получить погоду.\n";
        $message .= "Подсказка: можно выбрать по городу или локации.";

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard(
                Keyboard::make()
                    ->buttons([
                        Button::make("🌤️ Погода")->action("get_weather"),
                        Button::make("❓ Помощь")->action("help"),
                        Button::make("⚙ Настройка")->action("setting"),
                        Button::make("💳 Подписка")->action("subscription"),
                    ])
                    ->chunk(2),
            )
            ->send();
    }
    // Помощь по командам погодного бота
    public function help(): void
    {
        $message = "📚 *Помощь*\n\n";
        $message .= "Действие: команды и сценарии.\n";
        $message .= "Подсказка: можно отправить город текстом.\n\n";
        $message .= "Команды:\n";
        $message .= "/start — Главное меню\n";
        $message .= "/help — Справка\n";
        $message .= "/weather — Получить погоду\n\n";
        $message .= "Для отмены отправьте: *Отмена*.";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🏠 На главную")->action("start");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();
    }

    //
    public function get_weather_location(): void
    {
        $message = "📍 *Погода по локации*\n\n";
        $message .= "Действие: отправьте локацию.\n";
        $message .= "Подсказка: можно отменить — *Отмена*.";

        // Построим reply-клавиатуру с кнопкой, запрашивающей локацию у клиента.
        $replyKeyboard = function ($keyboard) {
            $keyboard->button("📍 Передать локацию")->requestLocation();
            $keyboard->oneTime(true);
            $keyboard->resize(true);
            return $keyboard;
        };

        $this->chat->markdown($message)->replyKeyboard($replyKeyboard)->send();
    }

    public function get_weather_city(): void
    {
        $this->sendCityPrompt();
    }

    public function setting(): void
    {
        $this->chat
            ->markdown(
                "⚙️ *Настройки*\n\nДействие: настройки.\nПодсказка: раздел в разработке.",
            )
            ->keyboard(function ($keyboard) {
                $keyboard->button("🏠 На главную")->action("start");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();
    }

    public function subscription(): void
    {
        $this->chat
            ->markdown(
                "💳 *Подписка*\n\nДействие: управление подпиской.\nПодсказка: раздел в разработке.",
            )
            ->keyboard(function ($keyboard) {
                $keyboard->button("🏠 На главную")->action("start");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();
    }

    public function weather(): void
    {
        $this->handleGetWeather();
    }

    /**
     * Обработка нажатия на кнопку "Получить погоду"
     */
    public function handleGetWeather(): void
    {
        $message = "🌤️ *Как получить погоду?*\n\n";
        $message .= "Действие: выберите способ.\n";
        $message .= "Подсказка: по локации или по городу.";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard
                    ->button("📍 По локации")
                    ->action("get_weather_location");
                $keyboard->button("🏙️ По городу")->action("get_weather_city");
                $keyboard->button("🏠 На главную")->action("start");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();
    }

    /**
     * Обработка текстовых сообщений
     */
    protected function handleChatMessage(Stringable $text): void
    {
        // Если сообщение содержит локацию — запрашиваем прогноз и отправляем результат
        if ($this->message?->location() !== null) {
            $location = $this->message->location();
            $lat = $location->latitude();
            $lon = $location->longitude();

            Log::debug("handleChatMessage: location received", [
                "chat_id" => $this->chat->chat_id ?? null,
                "lat" => $lat,
                "lon" => $lon,
            ]);

            try {
                if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                    throw new \InvalidArgumentException("Invalid coordinates");
                }

                /** @var WeatherService $weatherService */
                $weatherService = app(WeatherService::class);
                $weather = $weatherService->getByCoordinates($lat, $lon);

                $text =
                    "📍 Локация получена.\n\n" .
                    ($weather["text"] ?? "Погода недоступна.");
                $this->sendWeatherResponse($text, null, true);
            } catch (\Throwable $e) {
                Log::error("WeatherService error", ["exception" => $e]);
                $this->chat
                    ->html(
                        "❌ Не удалось получить погоду по локации.\n\nПопробуйте позже.",
                    )
                    ->removeReplyKeyboard()
                    ->send();
            }

            return;
        }

        // ...existing code...

        // Если это команда
        if ($text->startsWith("/")) {
            parent::handleChatMessage($text);
            return;
        }

        if ($this->isCancel($text->toString())) {
            $this->start();
            return;
        }

        // Попытка интерпретировать текст как город
        try {
            $input = trim($text->toString());
            if (mb_strlen($input) < 2 || !preg_match("/[\\p{L}]/u", $input)) {
                throw new \InvalidArgumentException("Not a city name");
            }

            /** @var WeatherService $weatherService */
            $weatherService = app(WeatherService::class);
            $validation = $weatherService->validateCity($input);
            $weather = $weatherService->getByCityName(
                $validation["normalized"],
            );

            $responseText = $weather["text"] ?? "Погода недоступна.";
            $this->sendWeatherResponse($responseText, null, false);

            return;
        } catch (\Throwable $e) {
            $this->chat
                ->markdown(
                    "❌ *Город не найден.*\n\nДействие: попробуйте еще раз.\nПодсказка: можно отправить *Отмена*.",
                )
                ->keyboard(function ($keyboard) {
                    $keyboard->button("Отмена")->action("start");
                    $keyboard->chunk(2);
                    return $keyboard;
                })
                ->send();
            return;
        }

        // Обработка обычного текста
        $message = "🌤️ *Погодный бот*\n\n";
        $message .= "Действие: получить погоду.\n";
        $message .= "Подсказка: можно выбрать по городу или локации.";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🌤️ Получить погоду")->action("get_weather");
                $keyboard->button("❓ Помощь")->action("help");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();
    }

    /**
     * Обработка callback query (нажатие на кнопки)
     */
    protected function handleCallbackQuery(): void
    {
        // Инициализируем данные callback (messageId, callbackQueryId, originalKeyboard, data)
        $this->extractCallbackQueryData();
        // Получаем данные callback
        $callbackData = $this->callbackQuery?->data();

        if (!$callbackData) {
            return;
        }

        $this->ackCallbackQuery();

        $action = $this->extractActionFromJson($callbackData);

        switch ($action) {
            case "get_weather":
                $this->handleGetWeather();
                break;
            case "get_weather_city":
                $this->get_weather_city();
                break;
            case "get_weather_location":
                $this->get_weather_location();
                break;
            case "help":
                $this->help();
                break;
            case "start":
                $this->start();
                break;
            case "subscription":
                $this->subscription();
                break;
            case "setting":
                $this->setting();
                break;
            default:
                $this->chat->html("Действие: {$callbackData}")->send();
        }
    }

    private function sendCityPrompt(): void
    {
        $message = "🏙️ *Погода по городу*\n\n";
        $message .= "Действие: введите название города.\n";
        $message .= "Подсказка: *Москва* или *Санкт-Петербург*.\n";
        $message .= "Для отмены отправьте: *Отмена*.";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("Отмена")->action("start");
                $keyboard->chunk(2);
                return $keyboard;
            })
            ->send();
    }

    private function isCancel(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        return in_array($normalized, self::CANCEL_KEYWORDS, true);
    }

    private function sendWeatherResponse(
        string $text,
        ?string $image,
        bool $removeReplyKeyboard,
    ): void {
        try {
            if ($image) {
                $send = $this->chat->photo($image)->html($text);
            } else {
                $send = $this->chat->html($text);
            }

            if ($removeReplyKeyboard) {
                $send->removeReplyKeyboard();
            }

            $send->send();
        } catch (\Throwable $e) {
            Log::warning("Weather response send failed", [
                "exception" => $e->getMessage(),
            ]);

            $send = $this->chat->html($text);
            if ($removeReplyKeyboard) {
                $send->removeReplyKeyboard();
            }
            $send->send();
        }
    }

    private function ackCallbackQuery(string $message = ""): void
    {
        if (isset($this->callbackQueryId) && $this->callbackQueryId) {
            $this->bot->replyWebhook($this->callbackQueryId, $message)->send();
        }
    }

    /**
     * Извлекаем action из данных callback.
     * Поддерживаем несколько форматов: Collection (ключ 'action') или JSON/string.
     */
    private function extractActionFromJson(mixed $data): string
    {
        // Если это коллекция с ключом 'action' — используем его
        if (is_object($data) && method_exists($data, "get")) {
            try {
                $action = $data->get("action");

                if (is_string($action) && $action !== "") {
                    return $action;
                }
            } catch (\Throwable $e) {
                // fallthrough
            }
        }

        // Если это массив
        if (is_array($data) && isset($data["action"])) {
            return (string) $data["action"];
        }

        // Если это объект CallbackQuery->data() возвращает Collection, но на всякий случай
        if (is_string($data)) {
            // Поддержка формата {"action":"value"} или просто 'action:value' и т.п.
            $json = trim($data);

            // Попробуем декодировать JSON
            if (str_starts_with($json, "{")) {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && isset($decoded["action"])) {
                    return (string) $decoded["action"];
                }
            }

            // Простая обработка вида {"action":"value"}
            $action = str_replace('{"action":"', "", $json);
            $action = str_replace('"}', "", $action);
            $action = trim($action, '"\'');

            if ($action !== "") {
                return $action;
            }
        }

        // В крайнем случае — пустая строка
        return "";
    }
}
