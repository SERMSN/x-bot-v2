<?php
namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\ChatLogger;
use App\Services\Telegram\Conversations\WeatherConversationService;
use App\Services\Telegram\Domain\WeatherDomainService;
use App\Services\Telegram\Messages\WeatherMessageBuilder;
use App\Services\Telegram\Support\BotContext;
use App\Services\Telegram\Support\CallbackAction;
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
    private const WEATHER_MODE_BRIEF = "brief";
    private const WEATHER_MODE_DETAILED = "detailed";
    private const WEATHER_UNITS_METRIC = "metric";
    private const WEATHER_UNITS_IMPERIAL = "imperial";
    private const NOTIFICATION_MODE_BRIEF = "brief";
    private const NOTIFICATION_MODE_DETAILED = "detailed";
    private const LAST_QUERY_TYPE_COORDINATES = "coordinates";

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
        $message = $this->messages()->startText();

        $this->chat
            ->markdown($message)
            ->removeReplyKeyboard()
            ->keyboard($this->messages()->mainMenuKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "start"]);
    }
    public function help(): void
    {
        $message = $this->messages()->helpText();

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->homeKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "help"]);
    }

    public function get_weather_location(): void
    {
        $message = $this->messages()->locationPromptText();

        $this->chat
            ->markdown($message)
            ->replyKeyboard($this->messages()->locationKeyboard())
            ->send();
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
        $conversation = $this->conversation();
        $savedCity = $conversation->getSavedCityName($this->botContext());
        $preferences = $conversation->getPreferences($this->botContext());
        $savedCityLine = $savedCity !== null
            ? "Сохраненный город: *{$savedCity}*.\n"
            : "Сохраненный город: *не задан*.\n";

        $this->chat
            ->markdown(
                $this->messages()->settingText(
                    $savedCityLine,
                    $this->messages()->weatherModeLabel($preferences["response_mode"]),
                    $this->messages()->weatherUnitsLabel($preferences["units"]),
                    $conversation->isAutoSaveLocationCityEnabled($this->botContext()) ? "вкл" : "выкл",
                    $conversation->isWeatherNotificationsEnabled($this->botContext()) ? "вкл" : "выкл",
                ),
            )
            ->keyboard(
                $this->messages()->settingKeyboard(
                    $this->messages()->weatherModeLabel($preferences["response_mode"]),
                    $this->messages()->shortWeatherUnitsLabel($preferences["units"]),
                    $conversation->isAutoSaveLocationCityEnabled($this->botContext()) ? "вкл" : "выкл",
                    $conversation->isWeatherNotificationsEnabled($this->botContext()) ? "вкл" : "выкл",
                ),
            )
            ->send();

        $this->logOutgoing("⚙️ *Настройки*", ["handler_action" => "setting"]);
    }

    public function subscription(): void
    {
        $this->chat
            ->markdown($this->messages()->subscriptionText())
            ->keyboard($this->messages()->homeKeyboard())
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
        $savedCity = $this->conversation()->getSavedCityName($this->botContext());
        $message = $this->messages()->weatherSelectionText($savedCity);

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->weatherOptionsKeyboard($savedCity))
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

        if (in_array(mb_strtolower(trim($text->toString())), self::CANCEL_KEYWORDS, true)) {
            $this->conversation()->clearAwaitingSavedCity($this->botContext());
            $this->start();
            return;
        }

        if ($this->conversation()->isAwaitingSavedCity($this->botContext())) {
            $this->conversation()->clearAwaitingSavedCity($this->botContext());
            $input = trim($text->toString());

            if (mb_strlen($input) < 2 || !preg_match("/[\\p{L}]/u", $input)) {
                $message = $this->messages()->invalidCityText();
                $this->chat
                    ->markdown($message)
                    ->keyboard($this->messages()->cancelKeyboard())
                    ->send();
                $this->logOutgoing($message, [
                    "handler_action" => "set_default_city_invalid",
                    "is_error" => true,
                ]);
                return;
            }

            try {
                $validation = $this->domain()->validateCity($this->botContext(), $input);
                $timezone = $this->domain()->resolveNotificationTimezoneByCoordinates(
                    $this->botContext(),
                    (float) ($validation["lat"] ?? 0),
                    (float) ($validation["lon"] ?? 0),
                );
                $savedCity = (string) ($validation["display_name"] ?? $validation["name"]);
                $this->conversation()->saveCity(
                    $this->botContext(),
                    $savedCity,
                    (float) ($validation["lat"] ?? 0),
                    (float) ($validation["lon"] ?? 0),
                    $timezone,
                );
                $message = $this->messages()->citySavedText($savedCity);
                $this->chat
                    ->markdown($message)
                    ->keyboard($this->messages()->weatherAndHomeKeyboard())
                    ->send();
                $this->logOutgoing($message, [
                    "handler_action" => "set_default_city_success",
                ]);
            } catch (\Throwable $e) {
                Log::error("Weather city save failed", [
                    "handler_action" => "set_default_city_not_found",
                    "bot_id" => $this->bot->id ?? null,
                    "chat_id" => $this->chat->chat_id ?? null,
                    "input" => $input,
                    "exception" => $e->getMessage(),
                ]);
                $message = $this->messages()->cityNotFoundText();
                $this->chat
                    ->markdown($message)
                    ->keyboard($this->messages()->cancelKeyboard())
                    ->send();
                $this->logOutgoing($message, [
                    "handler_action" => "set_default_city_not_found",
                    "is_error" => true,
                ]);
            }
            return;
        }

        if ($this->conversation()->isAwaitingNotificationTime($this->botContext())) {
            $this->conversation()->clearAwaitingNotificationTime($this->botContext());
            $input = trim($text->toString());

            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $input)) {
                $message = $this->messages()->invalidTimeText();
                $this->chat
                    ->markdown($message)
                    ->keyboard($this->messages()->cancelKeyboard())
                    ->send();
                $this->logOutgoing($message, [
                    "handler_action" => "weather_notification_time_invalid",
                    "is_error" => true,
                ]);
                return;
            }

            $chat = $this->conversation()->resolveChatModel($this->botContext());
            if ($chat === null) {
                return;
            }

            $chat->weather_notification_time = $input;
            $chat->save();

            $preferences = $this->conversation()->getPreferences($this->botContext());
            $message = "✅ *Время уведомлений сохранено*\n\nНовое время: *{$input}*.";
            $this->chat
                ->markdown($message)
                ->keyboard($this->messages()->settingKeyboard(
                    $this->messages()->weatherModeLabel($preferences["response_mode"]),
                    $this->messages()->shortWeatherUnitsLabel($preferences["units"]),
                    $this->conversation()->isAutoSaveLocationCityEnabled($this->botContext()) ? "вкл" : "выкл",
                    $this->conversation()->isWeatherNotificationsEnabled($this->botContext()) ? "вкл" : "выкл",
                ))
                ->send();
            $this->logOutgoing($message, [
                "handler_action" => "weather_notification_time_saved",
            ]);
            return;
        }

        $input = trim($text->toString());
        if (mb_strlen($input) < 2 || !preg_match("/[\\p{L}]/u", $input)) {
            return;
        }

        try {
            $validation = $this->domain()->validateCity($this->botContext(), $input);
            $weather = $this->domain()->requestWeatherByValidatedCity($this->botContext(), $validation);
            $timezone = $this->domain()->resolveNotificationTimezoneByCoordinates(
                $this->botContext(),
                (float) ($validation["lat"] ?? 0),
                (float) ($validation["lon"] ?? 0),
            );
            $savedCity = (string) ($validation["display_name"] ?? $validation["name"]);
            $this->conversation()->saveCity(
                $this->botContext(),
                $savedCity,
                (float) ($validation["lat"] ?? 0),
                (float) ($validation["lon"] ?? 0),
                $timezone,
            );
            $this->conversation()->rememberLastWeatherQuery(
                $this->botContext(),
                self::LAST_QUERY_TYPE_COORDINATES,
                (float) ($validation["lat"] ?? 0),
                (float) ($validation["lon"] ?? 0),
                $savedCity,
            );

            $this->sendWeatherResponse(
                $weather["text"] ?? $this->messages()->weatherNotAvailableText(),
                null,
                false,
            );
        } catch (\Throwable $e) {
            Log::error("Weather city lookup failed", [
                "handler_action" => "city_not_found",
                "bot_id" => $this->bot->id ?? null,
                "chat_id" => $this->chat->chat_id ?? null,
                "input" => $input,
                "exception" => $e->getMessage(),
            ]);

            $message = $this->messages()->retryWeatherFromCityText();
            $this->chat
                ->markdown($message)
                ->keyboard($this->messages()->cancelKeyboard())
                ->send();
            $this->logOutgoing($message, [
                "handler_action" => "city_not_found",
                "is_error" => true,
            ]);
        }
    }

    /**
     * Обработка callback query (нажатие на кнопки)
     */
    protected function handleCallbackQuery(): void
    {
        // Инициализируем данные callback (messageId, callbackQueryId, originalKeyboard, data)
        $this->extractCallbackQueryData();
        $callbackData = $this->callbackQuery?->data();

        if (!$callbackData) {
            return;
        }

        $this->ackCallbackQuery();

        $action = CallbackAction::parse($callbackData);

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
            case "refresh_weather":
                $this->refresh_weather();
                break;
            case "toggle_weather_mode":
                $this->toggle_weather_mode();
                break;
            case "toggle_weather_units":
                $this->toggle_weather_units();
                break;
            case "toggle_auto_save_location_city":
                $this->toggle_auto_save_location_city();
                break;
            case "weather_notifications_menu":
                $this->weather_notifications_menu();
                break;
            case "toggle_weather_notifications":
                $this->toggle_weather_notifications();
                break;
            case "set_weather_notification_time":
                $this->set_weather_notification_time();
                break;
            case "toggle_weather_notification_mode":
                $this->toggle_weather_notification_mode();
                break;
            default:
                $this->chat->html("Действие: {$callbackData}")->send();
                $this->logOutgoing("Действие: {$callbackData}", [
                    "handler_action" => "unknown_callback",
                ]);
        }
    }

    public function weather_notifications_menu(): void
    {
        $conversation = $this->conversation();
        $preferences = $conversation->getPreferences($this->botContext());
        $notificationStatus = $conversation->isWeatherNotificationsEnabled($this->botContext()) ? "вкл" : "выкл";
        $notificationMode = $this->messages()->notificationModeLabel($preferences["notification_mode"]);
        $message = $this->messages()->notificationsText(
            $notificationStatus,
            $preferences["notification_time"],
            $preferences["notification_timezone"],
            $notificationMode,
        );

        $this->chat
            ->markdown($message)
            ->keyboard(
                $this->messages()->notificationKeyboard(
                    $notificationStatus,
                    $preferences["notification_time"],
                    $notificationMode,
                ),
            )
            ->send();

        $this->logOutgoing($message, [
            "handler_action" => "weather_notifications_menu",
        ]);
    }

    private function sendCityPrompt(): void
    {
        $message = $this->messages()->cityPromptText();

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->cancelKeyboard())
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
            $weather = $this->domain()->requestWeatherByCoordinates($this->botContext(), $lat, $lon);
            if ($this->conversation()->isAutoSaveLocationCityEnabled($this->botContext())) {
                $this->conversation()->rememberLastWeatherQuery(
                    $this->botContext(),
                    self::LAST_QUERY_TYPE_COORDINATES,
                    $lat,
                    $lon,
                    (string) ($weather["city"]["name"] ?? null),
                );
            }
            $this->sendWeatherResponse(
                $weather["text"] ?? $this->messages()->weatherNotAvailableText(),
                null,
                true,
            );
        } catch (\Throwable $e) {
            Log::error("WeatherService location error", ["exception" => $e]);
            $this->sendWeatherFailure(
                $this->messages()->retryWeatherText(),
                "get_weather_location",
                true,
            );
        }
    }

    public function set_default_city(): void
    {
        $this->conversation()->setAwaitingSavedCity($this->botContext());
        $message = $this->messages()->cityPromptText();

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->cancelKeyboard())
            ->send();

        $this->logOutgoing($message, ["handler_action" => "set_default_city"]);
    }

    public function get_weather_saved_city(): void
    {
        $savedCity = $this->conversation()->getSavedCity($this->botContext());

        if ($savedCity === null) {
            $message = $this->messages()->savedCityMissingText();
        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->settingsAndHomeKeyboard())
            ->send();
            $this->logOutgoing($message, [
                "handler_action" => "get_weather_saved_city",
                "is_error" => true,
            ]);
            return;
        }

        try {
            $weather = $this->domain()->requestWeatherByCoordinates(
                $this->botContext(),
                (float) $savedCity["lat"],
                (float) $savedCity["lon"],
            );
            $this->conversation()->rememberLastWeatherQuery(
                $this->botContext(),
                self::LAST_QUERY_TYPE_COORDINATES,
                (float) $savedCity["lat"],
                (float) $savedCity["lon"],
                (string) $savedCity["name"],
            );
            $this->sendWeatherResponse(
                $weather["text"] ?? $this->messages()->weatherNotAvailableText(),
                null,
                false,
            );
        } catch (\Throwable $e) {
            Log::error("WeatherService saved city error", [
                "exception" => $e,
            ]);
            $this->sendWeatherFailure(
                $this->messages()->retrySavedCityWeatherText(),
                "get_weather_saved_city",
            );
        }
    }

    public function refresh_weather(): void
    {
        $lastQuery = $this->conversation()->getLastWeatherQuery($this->botContext());
        if ($lastQuery === null) {
            $message = $this->messages()->noLastQueryText();
            $this->chat->html($message)->send();
            $this->logOutgoing($message, [
                "handler_action" => "refresh_weather",
                "is_error" => true,
            ]);
            return;
        }

        try {
            $weather = $this->domain()->requestWeatherByCoordinates(
                $this->botContext(),
                (float) $lastQuery["lat"],
                (float) $lastQuery["lon"],
            );
            $this->sendWeatherResponse(
                $weather["text"] ?? $this->messages()->weatherNotAvailableText(),
                null,
                false,
            );
        } catch (\Throwable $e) {
            Log::error("WeatherService refresh error", ["exception" => $e]);
            $this->sendWeatherFailure(
                $this->messages()->retryRefreshText(),
                "refresh_weather",
            );
        }
    }

    public function toggle_weather_mode(): void
    {
        $chat = $this->conversation()->resolveChatModel($this->botContext());
        if ($chat === null) {
            return;
        }

        $currentMode = $this->conversation()->normalizeWeatherMode($chat->weather_response_mode);
        $chat->weather_response_mode =
            $currentMode === self::WEATHER_MODE_DETAILED
                ? self::WEATHER_MODE_BRIEF
                : self::WEATHER_MODE_DETAILED;
        $chat->save();

        $this->setting();
    }

    public function toggle_weather_units(): void
    {
        $chat = $this->conversation()->resolveChatModel($this->botContext());
        if ($chat === null) {
            return;
        }

        $currentUnits = $this->conversation()->normalizeWeatherUnits($chat->weather_units);
        $chat->weather_units =
            $currentUnits === self::WEATHER_UNITS_METRIC
                ? self::WEATHER_UNITS_IMPERIAL
                : self::WEATHER_UNITS_METRIC;
        $chat->save();

        $this->setting();
    }

    public function toggle_auto_save_location_city(): void
    {
        $chat = $this->conversation()->resolveChatModel($this->botContext());
        if ($chat === null) {
            return;
        }

        $chat->weather_auto_save_location_city = !$this->conversation()->isAutoSaveLocationCityEnabled($this->botContext());
        $chat->save();

        $this->setting();
    }

    public function toggle_weather_notifications(): void
    {
        $chat = $this->conversation()->resolveChatModel($this->botContext());
        if ($chat === null) {
            return;
        }

        if ($this->conversation()->getSavedCity($this->botContext()) === null) {
            $message = "❌ Сначала сохраните город, затем включайте уведомления.";
            $this->chat->html($message)->send();
            $this->logOutgoing($message, [
                "handler_action" => "toggle_weather_notifications",
                "is_error" => true,
            ]);
            return;
        }

        $chat->weather_notifications_enabled = !$this->conversation()->isWeatherNotificationsEnabled($this->botContext());

        if ($chat->weather_notifications_enabled) {
            $this->ensureNotificationTimezone($chat);

            if ($this->conversation()->normalizeNotificationTimezone($chat->weather_notification_timezone) === null) {
                $chat->weather_notifications_enabled = false;

                $message = "❌ Не удалось определить часовой пояс для сохраненного города.\n\nОбновите сохраненный город или сначала получите погоду по нему.";
                $this->chat->html($message)->send();
                $this->logOutgoing($message, [
                    "handler_action" => "toggle_weather_notifications",
                    "is_error" => true,
                ]);
                return;
            }
        }

        if ($chat->weather_notification_time === null) {
            $chat->weather_notification_time = "08:00";
        }

        $chat->save();
        $this->setting();
    }

    public function set_weather_notification_time(): void
    {
        $this->conversation()->setAwaitingNotificationTime($this->botContext());
        $message = $this->messages()->timePromptText();

        $this->chat
            ->markdown($message)
            ->keyboard($this->messages()->cancelKeyboard())
            ->send();

        $this->logOutgoing($message, [
            "handler_action" => "set_weather_notification_time",
        ]);
    }

    public function toggle_weather_notification_mode(): void
    {
        $chat = $this->conversation()->resolveChatModel($this->botContext());
        if ($chat === null) {
            return;
        }

        $currentMode = $this->conversation()->normalizeNotificationMode($chat->weather_notification_mode);
        $chat->weather_notification_mode =
            $currentMode === self::NOTIFICATION_MODE_DETAILED
                ? self::NOTIFICATION_MODE_BRIEF
                : self::NOTIFICATION_MODE_DETAILED;
        $chat->save();

        $this->setting();
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

            $send = $send->keyboard($this->messages()->weatherAndHomeKeyboard());

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
            if (method_exists($send, "keyboard")) {
                $send = $send->keyboard($this->messages()->weatherAndHomeKeyboard());
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

    private function messages(): WeatherMessageBuilder
    {
        return app(WeatherMessageBuilder::class);
    }

    private function conversation(): WeatherConversationService
    {
        return app(WeatherConversationService::class);
    }

    private function domain(): WeatherDomainService
    {
        return app(WeatherDomainService::class);
    }

    private function botContext(): BotContext
    {
        return new BotContext($this->bot, $this->chat);
    }

    private function ackCallbackQuery(string $message = ""): void
    {
        if (isset($this->callbackQueryId) && $this->callbackQueryId) {
            $this->bot->replyWebhook($this->callbackQueryId, $message)->send();
        }
    }
}
