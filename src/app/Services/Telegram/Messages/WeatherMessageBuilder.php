<?php

namespace App\Services\Telegram\Messages;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class WeatherMessageBuilder
{
    public function startText(): string
    {
        return "🌤️ *Погодный бот*\n\n" .
            "Действие: получить погоду.\n" .
            "Подсказка: можно выбрать по городу или локации.";
    }

    public function helpText(): string
    {
        return "📚 *Помощь*\n\n" .
            "Действие: команды, сценарии и возможности.\n" .
            "Подсказка: можно отправить город текстом или выбрать локацию.\n\n" .
            "Команды:\n" .
            "/start — Главное меню\n" .
            "/help — Справка\n" .
            "/weather — Получить погоду\n" .
            "/setting — Настройки\n" .
            "/subscription — Подписка\n\n" .
            "Возможности:\n" .
            "• Погода по городу, локации и сохраненному городу\n" .
            "• Кнопка *Обновить* после ответа с погодой\n" .
            "• Формат ответа: *кратко* или *подробно*\n" .
            "• Единицы измерения: *°C, м/с* или *°F, mph*\n" .
            "• Краткий прогноз на 3 дня\n" .
            "• В подробном режиме: почасовой прогноз на сегодня и завтра\n" .
            "• Сохраненный город для быстрого запроса\n" .
            "• Автосохранение города после запроса по локации можно включать и выключать\n\n" .
            "Настройки:\n" .
            "• Сохраненный город\n" .
            "• Формат ответа\n" .
            "• Единицы измерения\n" .
            "• Автосохранение по локации\n\n" .
            "Для отмены отправьте: *Отмена*.";
    }

    public function locationPromptText(): string
    {
        return "📍 *Погода по локации*\n\n" .
            "Действие: отправьте локацию.\n" .
            "Подсказка: можно отменить — *Отмена*.";
    }

    public function cityPromptText(): string
    {
        return "🏙️ *Погода по городу*\n\n" .
            "Действие: введите название города.\n" .
            "Подсказка: *Москва* или *Санкт-Петербург*.\n" .
            "Для отмены отправьте: *Отмена*.";
    }

    public function settingText(string $savedCityLine, string $responseMode, string $units, string $autoSaveLabel, string $notificationsLabel): string
    {
        return "⚙️ *Настройки*\n\n" .
            "Действие: управление погодными настройками.\n" .
            $savedCityLine .
            "Формат: *{$responseMode}*.\n" .
            "Единицы: *{$units}*.\n" .
            "Локация -> сохранить город: *{$autoSaveLabel}*.\n" .
            "Уведомления: *{$notificationsLabel}*.\n\n" .
            "Подсказка: уведомления вынесены в отдельное подменю.";
    }

    public function subscriptionText(): string
    {
        return "💳 *Подписка*\n\nДействие: управление подпиской.\nПодсказка: раздел в разработке.";
    }

    public function weatherSelectionText(?string $savedCity): string
    {
        return "🌤️ *Как получить погоду?*\n\n" .
            "Действие: выберите способ.\n" .
            ($savedCity !== null
                ? "Подсказка: по локации, по городу или из сохраненного."
                : "Подсказка: по локации или по городу.");
    }

    public function notificationsText(string $status, string $time, string $timezone, string $mode): string
    {
        return "🔔 *Уведомления*\n\n" .
            "Действие: управление уведомлениями.\n" .
            "Статус: *{$status}*.\n" .
            "Время: *{$time}*.\n" .
            "Часовой пояс: *{$timezone}*.\n" .
            "Режим: *{$mode}*.\n\n" .
            "Подсказка: сначала сохраните город, затем включайте уведомления.";
    }

    public function citySavedText(string $savedCity): string
    {
        return "✅ *Город сохранен*\n\n" .
            "Сохраненный город: *{$savedCity}*.\n" .
            "Теперь можно получать погоду из сохраненного города.";
    }

    public function savedCityMissingText(): string
    {
        return "⭐ *Сохраненный город не задан*\n\n" .
            "Действие: перейдите в настройки и сохраните город заново.";
    }

    public function weatherByCityText(): string
    {
        return "🏙️ *Погода по городу*\n\n" .
            "Действие: введите название города.\n" .
            "Подсказка: *Москва* или *Санкт-Петербург*.\n" .
            "Для отмены отправьте: *Отмена*.";
    }

    public function timePromptText(): string
    {
        return "🕒 *Время уведомлений*\n\n" .
            "Действие: введите время в формате *HH:MM*.\n" .
            "Подсказка: например *08:30*.\n" .
            "Для отмены отправьте: *Отмена*.";
    }

    public function invalidCityText(): string
    {
        return "❌ *Некорректное название города.*\n\n" .
            "Действие: попробуйте еще раз.\n" .
            "Подсказка: можно отправить *Отмена*.";
    }

    public function invalidTimeText(): string
    {
        return "❌ *Некорректное время.*\n\n" .
            "Действие: введите время в формате *HH:MM*.\n" .
            "Подсказка: например *08:30*.";
    }

    public function cityNotFoundText(): string
    {
        return "❌ *Город не найден.*\n\n" .
            "Действие: попробуйте еще раз.\n" .
            "Подсказка: можно отправить *Отмена*.";
    }

    public function retryWeatherText(): string
    {
        return "❌ Не удалось получить погоду по локации.\n\nПопробуйте позже.";
    }

    public function retrySavedCityWeatherText(): string
    {
        return "❌ Не удалось получить погоду по сохраненному городу.\n\nПопробуйте позже.";
    }

    public function retryRefreshText(): string
    {
        return "❌ Не удалось обновить прогноз.\n\nПопробуйте позже.";
    }

    public function retryWeatherFromCityText(): string
    {
        return "❌ Город не найден.\n\nДействие: попробуйте еще раз.\nПодсказка: можно отправить *Отмена*.";
    }

    public function noLastQueryText(): string
    {
        return "❌ Нет последнего запроса для обновления.\n\nСначала запросите погоду.";
    }

    public function weatherNotAvailableText(): string
    {
        return "Погода недоступна.";
    }

    public function mainMenuKeyboard(): Keyboard
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

    public function homeKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([Button::make("🏠 На главную")->action("start")])
            ->chunk(2);
    }

    public function locationKeyboard(): callable
    {
        return function ($keyboard) {
            $keyboard->button("📍 Передать локацию")->requestLocation();
            $keyboard->oneTime(true);
            $keyboard->resize(true);
            return $keyboard;
        };
    }

    public function settingKeyboard(string $responseMode, string $units, string $autoSaveLabel, string $notificationsLabel): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🏙️ Сохранить город")->action("set_default_city"),
                Button::make("📝 Формат: {$responseMode}")->action("toggle_weather_mode"),
                Button::make("🌡 Единицы: {$units}")->action("toggle_weather_units"),
                Button::make("📍 Локация: {$autoSaveLabel}")->action("toggle_auto_save_location_city"),
                Button::make("🔔 Уведомления: {$notificationsLabel}")->action("weather_notifications_menu"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    public function notificationKeyboard(string $status, string $time, string $mode): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔔 Статус: {$status}")->action("toggle_weather_notifications"),
                Button::make("🕒 Время: {$time}")->action("set_weather_notification_time"),
                Button::make("📝 Режим: {$mode}")->action("toggle_weather_notification_mode"),
                Button::make("⚙ Назад")->action("setting"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    public function weatherOptionsKeyboard(?string $savedCity): Keyboard
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

    public function cancelKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([Button::make("Отмена")->action("start")])
            ->chunk(2);
    }

    public function settingsAndHomeKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("⚙ Настройка")->action("setting"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    public function weatherAndHomeKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("🔄 Обновить")->action("refresh_weather"),
                Button::make("🌤️ Погода")->action("get_weather"),
                Button::make("🏠 На главную")->action("start"),
            ])
            ->chunk(2);
    }

    public function shortWeatherUnitsLabel(string $units): string
    {
        return $units === "imperial" ? "°F" : "°C";
    }

    public function weatherModeLabel(string $mode): string
    {
        return $mode === "brief" ? "кратко" : "подробно";
    }

    public function weatherUnitsLabel(string $units): string
    {
        return $units === "imperial" ? "°F, mph" : "°C, м/с";
    }

    public function notificationModeLabel(string $mode): string
    {
        return $mode === "detailed" ? "подробно" : "кратко";
    }
}
