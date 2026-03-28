<?php
namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\ChatLogger;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use App\Models\TelegraphChat;
use App\Services\Telegram\Services\WeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherBotHandler extends WebhookHandler
{
    private const CANCEL_KEYWORDS = ["отмена", "cancel", "stop", "выход"];

    protected function handleMessage(): void
    {
        if ($this->message?->location() !== null) {
            $this->handleLocationMessage();
            return;
        }

        parent::handleMessage();
    }

    public function start(): void
    {
        $message = "🌤️ *Погодный бот*\n\n";
        $message .= "Действие: получить погоду.\n";
        $message .= "Подсказка: можно выбрать по городу или локации.";

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard($this->mainMenuKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "start"]);
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
            ->keyboard($this->homeKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "help"]);
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
        $this->logOutgoing($message, [
            "handler_action" => "get_weather_location",
        ]);
    }

    public function get_weather_city(): void
    {
        $this->sendCityPrompt();
    }

    public function setting(): void
    {
        $savedCity = $this->getSavedCityName();
        $savedCityLine =
            $savedCity !== null
                ? "Сохраненный город: *{$savedCity}*.\n"
                : "Сохраненный город: *не задан*.\n";

        $this->chat
            ->markdown(
                "⚙️ *Настройки*\n\n" .
                    "Действие: управление настройками.\n" .
                    $savedCityLine .
                    "Подсказка: можно сохранить город для быстрого прогноза.",
            )
            ->keyboard($this->settingsKeyboard())
            ->send();

        $this->logOutgoing("⚙️ *Настройки*", ["handler_action" => "setting"]);
    }

    public function subscription(): void
    {
        $this->chat
            ->markdown(
                "💳 *Подписка*\n\nДействие: управление подпиской.\nПодсказка: раздел в разработке.",
            )
            ->keyboard($this->homeKeyboard())
            ->send();

        $this->logOutgoing("💳 *Подписка*", [
            "handler_action" => "subscription",
        ]);
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
        $savedCity = $this->getSavedCityName();

        $message = "🌤️ *Как получить погоду?*\n\n";
        $message .= "Действие: выберите способ.\n";
        $message .=
            $savedCity !== null
                ? "Подсказка: по локации, по городу или из сохраненного."
                : "Подсказка: по локации или по городу.";

        $this->chat
            ->markdown($message)
            ->keyboard($this->weatherOptionsKeyboard($savedCity))
            ->send();

        $this->logOutgoing($message, ["handler_action" => "get_weather"]);
    }

    /**
     * Обработка текстовых сообщений
     */
    protected function handleChatMessage(Stringable $text): void
    {
        // Если это команда
        if ($text->startsWith("/")) {
            parent::handleChatMessage($text);
            return;
        }

        if ($this->isCancel($text->toString())) {
            $this->clearAwaitingSavedCity();
            $this->start();
            return;
        }

        if ($this->isAwaitingSavedCity()) {
            $this->handleSaveCityInput($text->toString());
            return;
        }

        $this->handleCityWeather($text->toString());
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
            case "get_weather_saved_city":
                $this->get_weather_saved_city();
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
            case "set_default_city":
                $this->set_default_city();
                break;
            default:
                $this->chat->html("Действие: {$callbackData}")->send();
                $this->logOutgoing("Действие: {$callbackData}", [
                    "handler_action" => "unknown_callback",
                ]);
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
            ->keyboard($this->cancelKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "get_weather_city"]);
    }

    private function handleLocationMessage(): void
    {
        $location = $this->message?->location();
        if ($location === null) {
            return;
        }

        $lat = $location->latitude();
        $lon = $location->longitude();

        Log::debug("handleLocationMessage: location received", [
            "chat_id" => $this->chat->chat_id ?? null,
            "lat" => $lat,
            "lon" => $lon,
        ]);

        try {
            $weather = $this->requestWeatherByCoordinates($lat, $lon);
            $this->persistCityFromWeather($weather, $lat, $lon);
            $this->sendWeatherResponse(
                $weather["text"] ?? "Погода недоступна.",
                null,
                true,
            );
        } catch (\Throwable $e) {
            Log::error("WeatherService location error", ["exception" => $e]);
            $this->sendWeatherFailure(
                "❌ Не удалось получить погоду по локации.\n\nПопробуйте позже.",
                "get_weather_location",
                true,
            );
        }
    }

    public function set_default_city(): void
    {
        $this->setAwaitingSavedCity();

        $message = "🏙️ *Сохранить город*\n\n";
        $message .= "Действие: введите название города.\n";
        $message .= "Подсказка: *Москва* или *Санкт-Петербург*.\n";
        $message .= "Для отмены отправьте: *Отмена*.";

        $this->chat
            ->markdown($message)
            ->keyboard($this->cancelKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "set_default_city"]);
    }

    public function get_weather_saved_city(): void
    {
        $savedCity = $this->getSavedCity();

        if ($savedCity === null) {
            $this->sendSavedCityMissingMessage();
            return;
        }

        try {
            $weather = $this->requestWeatherByCoordinates(
                (float) $savedCity["lat"],
                (float) $savedCity["lon"],
            );
            $this->sendWeatherResponse(
                $weather["text"] ?? "Погода недоступна.",
                null,
                false,
            );
        } catch (\Throwable $e) {
            Log::error("WeatherService saved city error", [
                "exception" => $e,
            ]);
            $this->sendWeatherFailure(
                "❌ *Не удалось получить погоду по сохраненному городу.*",
                "get_weather_saved_city",
            );
        }
    }

    private function isCancel(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        return in_array($normalized, self::CANCEL_KEYWORDS, true);
    }

    private function handleSaveCityInput(string $input): void
    {
        $this->clearAwaitingSavedCity();
        $input = trim($input);

        if (mb_strlen($input) < 2 || !preg_match("/[\\p{L}]/u", $input)) {
            $this->sendRetryableCityError(
                "❌ *Некорректное название города.*\n\nДействие: попробуйте еще раз.\nПодсказка: можно отправить *Отмена*.",
                "set_default_city_invalid",
            );
            return;
        }

        try {
            $validation = $this->validateCityInput($input);
            $savedCity = $this->persistValidatedCity($validation);
            $this->sendCitySavedMessage($savedCity);
        } catch (\Throwable $e) {
            Log::error("WeatherService save city error", ["exception" => $e]);
            $this->sendRetryableCityError(
                "❌ *Город не найден.*\n\nДействие: попробуйте еще раз.\nПодсказка: можно отправить *Отмена*.",
                "set_default_city_not_found",
            );
        }
    }

    private function handleCityWeather(string $input): void
    {
        $input = trim($input);

        try {
            if (mb_strlen($input) < 2 || !preg_match("/[\\p{L}]/u", $input)) {
                throw new \InvalidArgumentException("Not a city name");
            }

            $validation = $this->validateCityInput($input);
            $weather = $this->requestWeatherByValidatedCity($validation);
            $this->persistValidatedCity($validation);

            $this->sendWeatherResponse(
                $weather["text"] ?? "Погода недоступна.",
                null,
                false,
            );
        } catch (\Throwable $e) {
            $this->sendRetryableCityError(
                "❌ *Город не найден.*\n\nДействие: попробуйте еще раз.\nПодсказка: можно отправить *Отмена*.",
                "city_not_found",
            );
        }
    }

    private function validateCityInput(string $input): array
    {
        /** @var WeatherService $weatherService */
        $weatherService = app(WeatherService::class);

        return $weatherService->validateCity((int) $this->bot->id, $input);
    }

    private function requestWeatherByValidatedCity(array $validation): array
    {
        /** @var WeatherService $weatherService */
        $weatherService = app(WeatherService::class);

        return $weatherService->getByCityName(
            (int) $this->bot->id,
            (string) $validation["normalized"],
        );
    }

    private function requestWeatherByCoordinates(float $lat, float $lon): array
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException("Invalid coordinates");
        }

        /** @var WeatherService $weatherService */
        $weatherService = app(WeatherService::class);

        return $weatherService->getByCoordinates(
            (int) $this->bot->id,
            $lat,
            $lon,
        );
    }

    private function persistValidatedCity(array $validation): string
    {
        $savedCity =
            (string) ($validation["display_name"] ?? $validation["name"]);

        $this->saveCity(
            $savedCity,
            (float) ($validation["lat"] ?? 0),
            (float) ($validation["lon"] ?? 0),
        );

        return $savedCity;
    }

    private function persistCityFromWeather(
        array $weather,
        float $lat,
        float $lon,
    ): void {
        $cityName = trim((string) ($weather["city"]["name"] ?? ""));
        if ($cityName === "") {
            return;
        }

        $this->saveCity($cityName, $lat, $lon);
    }

    private function sendWeatherFailure(
        string $message,
        string $handlerAction,
        bool $removeReplyKeyboard = false,
    ): void {
        $send = $this->chat->html($message);
        if ($removeReplyKeyboard) {
            $send->removeReplyKeyboard();
        }

        $send->send();
        $this->logOutgoing($message, [
            "handler_action" => $handlerAction,
            "is_error" => true,
        ]);
    }

    private function sendRetryableCityError(
        string $message,
        string $handlerAction,
    ): void {
        $this->chat
            ->markdown($message)
            ->keyboard($this->cancelKeyboard())
            ->send();

        $this->logOutgoing($message, [
            "handler_action" => $handlerAction,
            "is_error" => true,
        ]);
    }

    private function sendSavedCityMissingMessage(): void
    {
        $message = "⭐ *Сохраненный город не задан*\n\n";
        $message .= "Действие: перейдите в настройки и сохраните город заново.";

        $this->chat
            ->markdown($message)
            ->keyboard($this->settingsAndHomeKeyboard())
            ->send();

        $this->logOutgoing($message, [
            "handler_action" => "get_weather_saved_city",
            "is_error" => true,
        ]);
    }

    private function sendCitySavedMessage(string $savedCity): void
    {
        $message = "✅ *Город сохранен*\n\n";
        $message .= "Сохраненный город: *{$savedCity}*.\n";
        $message .= "Теперь можно получать погоду из сохраненного города.";

        $this->chat
            ->markdown($message)
            ->keyboard($this->weatherAndHomeKeyboard())
            ->send();

        $this->logOutgoing($message, [
            "handler_action" => "set_default_city_success",
        ]);
    }

    private function resolveChatModel(): ?TelegraphChat
    {
        $telegramChatId = isset($this->chat->chat_id)
            ? (string) $this->chat->chat_id
            : null;

        if ($telegramChatId === null) {
            return null;
        }

        return TelegraphChat::query()->firstOrCreate(
            [
                "telegraph_bot_id" => (int) $this->bot->id,
                "chat_id" => $telegramChatId,
            ],
            [
                "name" => $this->chat->name ?? null,
            ],
        );
    }

    private function getSavedCity(): ?array
    {
        $chat = $this->resolveChatModel();
        if ($chat === null) {
            return null;
        }

        $savedCity = $chat->weather_city;
        if (
            !is_string($savedCity) ||
            $savedCity === "" ||
            !is_numeric($chat->weather_city_lat) ||
            !is_numeric($chat->weather_city_lon)
        ) {
            return null;
        }

        return [
            "name" => $savedCity,
            "lat" => (float) $chat->weather_city_lat,
            "lon" => (float) $chat->weather_city_lon,
        ];
    }

    private function getSavedCityName(): ?string
    {
        $savedCity = $this->getSavedCity();
        if ($savedCity === null) {
            return null;
        }

        return $savedCity["name"];
    }

    private function saveCity(string $city, float $lat, float $lon): void
    {
        $chat = $this->resolveChatModel();
        if ($chat === null) {
            return;
        }

        $chat->weather_city = $city;
        $chat->weather_city_lat = $lat;
        $chat->weather_city_lon = $lon;
        $chat->save();
    }

    private function awaitingSavedCityCacheKey(): ?string
    {
        $telegramChatId = isset($this->chat->chat_id)
            ? (string) $this->chat->chat_id
            : null;
        if ($telegramChatId === null) {
            return null;
        }

        return "weather_bot_saved_city_pending_{$this->bot->id}_{$telegramChatId}";
    }

    private function setAwaitingSavedCity(): void
    {
        $key = $this->awaitingSavedCityCacheKey();
        if ($key === null) {
            return;
        }

        Cache::put($key, true, now()->addMinutes(10));
    }

    private function clearAwaitingSavedCity(): void
    {
        $key = $this->awaitingSavedCityCacheKey();
        if ($key === null) {
            return;
        }

        Cache::forget($key);
    }

    private function isAwaitingSavedCity(): bool
    {
        $key = $this->awaitingSavedCityCacheKey();
        if ($key === null) {
            return false;
        }

        return (bool) Cache::get($key, false);
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
            $this->logOutgoing($text, [
                "handler_action" => "send_weather_response",
                "has_image" => $image !== null,
                "remove_reply_keyboard" => $removeReplyKeyboard,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Weather response send failed", [
                "exception" => $e->getMessage(),
            ]);

            $fallbackText = trim(html_entity_decode(strip_tags($text)));
            $send = $this->chat->message($fallbackText);
            if (
                $removeReplyKeyboard &&
                method_exists($send, "removeReplyKeyboard")
            ) {
                $send->removeReplyKeyboard();
            }
            $send->send();
            $this->logOutgoing($fallbackText, [
                "handler_action" => "send_weather_response_fallback",
                "has_image" => false,
                "remove_reply_keyboard" => $removeReplyKeyboard,
                "send_error" => $e->getMessage(),
            ]);
        }
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

    private function mainMenuKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🌤️ Погода")->action("get_weather"),
                Button::make("❓ Помощь")->action("help"),
                Button::make("⚙ Настройка")->action("setting"),
                Button::make("💳 Подписка")->action("subscription"),
            ])
            ->chunk(2);
    }

    private function homeKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([Button::make("🏠 На главную")->action("start")])
            ->chunk(2);
    }

    private function settingsKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🏙️ Сохранить город")->action("set_default_city"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    private function weatherOptionsKeyboard(?string $savedCity): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("📍 По локации")->action("get_weather_location"),
                Button::make("🏙️ По городу")->action("get_weather_city"),
                Button::make(
                    $savedCity !== null
                        ? "⭐ В городе {$savedCity}"
                        : "⭐ Сохраненный город",
                )->action("get_weather_saved_city"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    private function cancelKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([Button::make("Отмена")->action("start")])
            ->chunk(2);
    }

    private function settingsAndHomeKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("⚙ Настройка")->action("setting"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    private function weatherAndHomeKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🌤️ Погода")->action("get_weather"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
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
