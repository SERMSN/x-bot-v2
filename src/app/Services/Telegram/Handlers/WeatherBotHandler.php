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
    public function start(): void
    {
        $message = "🌤️ *Добро пожаловать в погодный бот!*\n\n";
        $message .=
            "Я помогу вам узнать погоду в любом городе или по вашей локации!\n\n";
        $message .= "⬇️ *Используйте кнопки ниже:*";

        $this->chat
            ->markdown($message)
            ->keyboard(
                Keyboard::make()
                    ->buttons([
                        Button::make("📍 По локации")->action(
                            "get_weather_location",
                        ),
                        Button::make("🏙️ По городу")->action(
                            "get_weather_city",
                        ),
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
        $message = "📚 *Помощь по погодному боту*\n\n";
        $message .= "*Доступные команды:*\n";
        $message .= "/start - Начало работы\n";
        $message .= "/help - Эта справка\n";
        $message .= "/weather - Получить погоду\n";
        $message .= "/setting - Настройки бота\n";
        $message .= "/subscription - Подписка на дополнительные функции\n\n";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🏠 На главную")->action("start");
                return $keyboard;
            })
            ->send();
    }

    //
    public function get_weather_location(): void
    {
        $message = "🌤️ *Получить погоду по локации*\n\n";
        $message .=
            "⬇️ Нажмите на кнопку ниже, чтобы передать вашу локацию через клиент Telegram";

        // Построим reply-клавиатуру с кнопкой, запрашивающей локацию у клиента.
        $replyKeyboard = function ($keyboard) {
            $keyboard->button("📍Передать локацию")->requestLocation();
            $keyboard->oneTime(true);
            $keyboard->resize(true);
            return $keyboard;
        };

        if (isset($this->messageId) && $this->messageId) {
            // подтвердим callback, чтобы убрать «часики» у кнопки
            if (isset($this->callbackQueryId) && $this->callbackQueryId) {
                $this->bot->replyWebhook($this->callbackQueryId, "")->send();
            }
            // удалим исходное сообщение целиком (включая клавиатуру)
            //  $this->chat->deleteMessage($this->messageId)->send();
            // отправим новое сообщение с reply-клавиатурой (запрос локации)
            $this->chat
                ->markdown($message)
                ->replyKeyboard($replyKeyboard)
                ->send();
            return;
        }
        // Обычный путь — просто отправляем новое сообщение с reply-клавиатурой
        $this->chat->markdown($message)->replyKeyboard($replyKeyboard)->send();
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
        // Демонстрационные данные
        $cities = ["Москва", "Санкт-Петербург", "Новосибирск", "Екатеринбург"];
        $randomCity = $cities[array_rand($cities)];

        $message = "🌤️ *Погода в {$randomCity}*\n\n";
        $message .= "Температура: +" . rand(10, 25) . "°C\n";
        $message .= "Состояние: Солнечно ☀️\n";
        $message .= "Влажность: " . rand(50, 80) . "%\n";
        $message .= "Ветер: " . rand(2, 10) . " м/с\n\n";
        $message .= "Хорошего дня!";

        $callbackQueryId = $this->callbackQuery?->id();

        if ($callbackQueryId) {
            $this->bot
                ->replyWebhook($callbackQueryId, "Обрабатываю запрос...")
                ->send();
        }

        // Если у нас есть messageId — редактируем текущее сообщение (текст + клавиатуру),
        // иначе отправляем новое сообщение как раньше.
        if (isset($this->messageId) && $this->messageId) {
            $this->chat
                ->edit($this->messageId)
                ->markdown($message)
                ->keyboard(function ($keyboard) {
                    $keyboard
                        ->button("🌤️ Получить погоду")
                        ->action("get_weather");
                    $keyboard->button("🏠 На главную")->action("start");
                    return $keyboard;
                })
                ->send();

            return;
        }

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🌤️ Получить погоду")->action("get_weather");
                $keyboard->button("🏠 На главную")->action("start");
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
                /** @var WeatherService $weatherService */
                $weatherService = app(WeatherService::class);
                $weather = $weatherService->getByCoordinates($lat, $lon);

                $text = $weather["text"] ?? "Погода недоступна.";
                $image = $weather["image"] ?? null;

                if ($image) {
                    // Отправляем фото с подписью (HTML) и удаляем reply-клавиатуру
                    $this->chat
                        ->photo($image)
                        ->html($text)
                        ->removeReplyKeyboard()
                        ->send();
                } else {
                    $this->chat->html($text)->removeReplyKeyboard()->send();
                }
            } catch (\Throwable $e) {
                Log::error("WeatherService error", ["exception" => $e]);
                $this->chat
                    ->html("Не удалось получить погоду. Попробуйте позже.")
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

        // Обработка обычного текста
        $message = "🌤️ *Погодный бот*\n\n";
        $message .= "Вы написали: *{$text}*\n\n";
        $message .= "Используйте кнопки ниже для получения погоды!";

        $this->chat
            ->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button("🌤️ Получить погоду")->action("get_weather");
                $keyboard->button("❓ Помощь")->action("help");
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

        $action = $this->extractActionFromJson($callbackData);

        switch ($action) {
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
