# X-Bot v2: описание проекта

## Кратко
X-Bot v2 — Laravel 12 приложение для управления несколькими Telegram-ботами через единую админ-панель на Filament 4 и единый webhook-роут. В проекте уже реализованы три сценария ботов:
- погодный бот;
- VIN-декодер;
- бот-предсказатель.

Боты хранятся в БД, выбирают обработчик по полю `handler_class`, а часть интеграционных параметров задается не через `.env`, а через таблицу `bot_settings`.

## Технологии и зависимости
- Backend: Laravel 12
- Admin UI: Filament 4.x
- Telegram: `defstudio/telegraph` `^1.66`
- Frontend: Vite 7, Tailwind CSS 4
- База данных: MySQL или SQLite
- Очереди/кэш/сессии: поддерживаются штатными драйверами Laravel, в `.env.example` по умолчанию выставлены database-драйверы

## Точки входа и маршруты
- Вебхук Telegram:
  - `POST /telegram/{token}`
  - Контроллер: `App\Http\Controllers\TelegramWebhookController`
  - Особенности:
    - CSRF отключен для `/telegram/*`
    - опциональная проверка заголовка `X-Telegram-Bot-Api-Secret-Token`
    - защита от повторной обработки `update_id` через cache TTL
    - логирование входящих сообщений в `chat_logs`
- Публичная панель Filament:
  - `/`
  - Провайдер: `App\Providers\Filament\MainPanelProvider`
- Админ-панель Filament:
  - `/admin`
  - Провайдер: `App\Providers\Filament\AdminPanelProvider`
- Health-check Laravel:
  - `/up`

## Конфигурация и переменные окружения
Основные `.env` параметры:
- `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
- `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE`
- `TELEGRAM_WEBHOOK_DEDUP_TTL_MINUTES`
- `TELEGRAPH_WEBHOOK_DOMAIN`
- `TELEGRAPH_WEBHOOK_URL`
- `TELEGRAPH_REPORT_UNKNOWN_COMMANDS`
- `TELEGRAPH_WEBHOOK_SECRET`
- `TELEGRAPH_WEBHOOK_MAX_CONNECTIONS`
- `TELEGRAPH_WEBHOOK_DEBUG`
- `TELEGRAPH_HTTP_TIMEOUT`
- `TELEGRAPH_HTTP_PROXY`
- `TELEGRAPH_HTTP_CONNECTION_TIMEOUT`
- `WEATHER_HTTP_TIMEOUT_SECONDS`
- `WEATHER_HTTP_RETRIES`
- `WEATHER_HTTP_RETRY_SLEEP_MS`
- `WEATHER_SETTINGS_CACHE_TTL_MINUTES`
- `WEATHER_CACHE_TTL_MINUTES`
- `WEATHER_CITY_MIN_LENGTH`
- `WEATHER_GEO_SEARCH_LIMIT`
- `VIN_API_TIMEOUT_SECONDS`
- `VIN_API_RETRIES`
- `VIN_API_RETRY_SLEEP_MS`
- `VIN_CACHE_TTL_MINUTES`
- `VIN_SETTINGS_CACHE_TTL_MINUTES`
- `VIN_LENGTH`
- `PUBLIC_ASSETS_PATH`
- `HOSTING`

Важно:
- в `config/telegraph.php` кастомный webhook handler задан как `App\Services\Telegram\Handlers\Handler`;
- модели Telegraph переопределены на `App\Models\TelegramBot` и `App\Models\TelegraphChat`;
- реальные ключи для OpenWeather и Vincario VIN API ожидаются в таблице `bot_settings`, а не в `config/services.php`.

## Структура проекта
```text
x-bot-v2/
├── src/
│   ├── app/
│   │   ├── Console/Commands/
│   │   ├── Filament/
│   │   │   ├── Main/
│   │   │   ├── Pages/
│   │   │   └── Resources/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   ├── Providers/
│   │   └── Services/Telegram/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   ├── factories/
│   │   └── seeders/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   └── tests/
├── docs/
├── scripts/
├── .docker/
├── .github/workflows/
├── docker-compose.yml
└── README.md
```

## Модели

### `App\Models\User`
- Таблица: `users`
- Назначение: авторизация в админ-панели Filament

### `App\Models\TelegramBot`
- Наследуется от `DefStudio\Telegraph\Models\TelegraphBot`
- Таблица: `telegraph_bots`
- Поля:
  - `token`
  - `name`
  - `handler_class`
  - `webhook_url`
  - `settings`
- Связи:
  - `hasMany(BotSetting::class)`
  - `hasMany(ChatLog::class)`
  - `hasMany(SubscriptionPlan::class)`

### `App\Models\SubscriptionPlan`
- Таблица: `subscription_plans`
- Назначение: универсальные тарифные планы для любого бота
- Поля:
  - `telegraph_bot_id` - бот, к которому относится план, или `null` для общего плана
  - `name`
  - `report_count`
  - `price_rub`
  - `description`
  - `is_active`
  - `sort_order`

### `App\Models\TelegraphChat`
- Наследуется от `DefStudio\Telegraph\Models\TelegraphChat`
- Таблица: `telegraph_chats`
- Базовые поля:
  - `chat_id`
  - `name`
  - `telegraph_bot_id`
- Дополнительное поле для VIN-бота:
  - `full_reports_remaining` - счетчик полных отчетов на чат
- Погодные поля:
  - `weather_city`
  - `weather_city_lat`
  - `weather_city_lon`
  - `weather_response_mode`
  - `weather_units`
  - `weather_auto_save_location_city`
  - `weather_notifications_enabled`
  - `weather_notification_time`
  - `weather_notification_timezone`
  - `weather_notification_mode`
  - `weather_last_notification_date`
  - `last_weather_query_type`
  - `last_weather_query_city`
  - `last_weather_query_lat`
  - `last_weather_query_lon`
- Связи:
  - `belongsTo(TelegramBot::class, 'telegraph_bot_id')`
  - `hasMany(ChatLog::class, 'telegraph_chat_id')`

### `App\Models\BotSetting`
- Таблица: `bot_settings`
- Назначение: настройки конкретного бота
- Поддерживаемые ключи:
  - `OPENWEATHER_API_KEY`
  - `OPENWEATHER_API_URL`
  - `WEATHER_NOTIFICATION_RUN_INTERVAL_MINUTES`
  - `VIN_API_BASE_URL`
  - `VIN_API_KEY`
  - `VIN_API_SECRET_KEY`
  - `VIN_API_DECODE_URL` (legacy)

### `App\Models\SubscriptionPlan`
- Таблица: `subscription_plans`
- Назначение: тарифы и пакеты отчетов для VIN-бота
- Основные поля:
  - `telegraph_bot_id`
  - `name`
  - `report_count`
  - `price_rub`
  - `description`
  - `is_active`
  - `sort_order`

### `App\Models\SubscriptionTransaction`
- Таблица: `subscription_transactions`
- Назначение: журнал покупок и списаний VIN-отчетов
- Основные поля:
  - `telegraph_bot_id`
  - `telegraph_chat_id`
  - `subscription_plan_id`
  - `transaction_type`
  - `reports_before`
  - `reports_delta`
  - `reports_after`
  - `amount_rub`
  - `status`
  - `comment`
  - `meta`

### `App\Models\AppSetting`
- Таблица: `app_settings`
- Назначение: глобальные настройки интерфейсов Filament
- Поддерживаемые ключи:
  - `ADMIN_PANEL_PRIMARY_COLOR`
  - `ADMIN_PANEL_NAVIGATION_LAYOUT`
  - `MAIN_PANEL_PRIMARY_COLOR`
  - `MAIN_PANEL_NAVIGATION_LAYOUT`

### `App\Models\ChatLog`
- Таблица: `chat_logs`
- Назначение: журнал входящих и исходящих Telegram-сообщений
- Поля:
  - `telegraph_bot_id`
  - `telegraph_chat_id`
  - `direction`
  - `event_type`
  - `update_id`
  - `telegram_chat_id`
  - `telegram_user_id`
  - `command`
  - `callback_action`
  - `message_text`
  - `meta`

## Контроллеры

### `App\Http\Controllers\TelegramWebhookController`
- Метод: `handleWebhook(Request $request, string $token)`
- Что делает:
  - валидирует секрет вебхука, если он задан;
  - ищет бота по токену в `telegraph_bots`;
  - отбрасывает дубли `update_id` через cache;
  - пишет inbound-событие через `ChatLogger`;
  - передает обработку в `App\Services\Telegram\Handlers\Handler`;
  - возвращает JSON `{"ok": true}` или ошибку `403/404/500`.

## Handlers

### `App\Services\Telegram\Handlers\Handler`
- Центральный роутер Telegram-обработчиков
- Наследуется от `DefStudio\Telegraph\Handlers\WebhookHandler`
- Берет карту обработчиков из `config/bots.php`
- Поддерживаемые ключи:
  - `weather`
  - `vin`
  - `divination`

### `App\Services\Telegram\Handlers\WeatherBotHandler`
- Погодный Telegram-бот
- Поддерживает:
  - `/start`
  - `/help`
  - `/weather`
  - `/setting`
  - `/subscription`
- Возможности:
  - погода по городу;
  - погода по геолокации;
  - использование сохраненного города;
  - краткий и подробный режим ответа;
  - переключение единиц `metric/imperial`;
  - автосохранение города после отправки локации;
  - запоминание последнего погодного запроса;
  - ежедневные уведомления по сохраненным координатам;
  - inline/reply клавиатуры и callback-действия.
- Архитектура:
  - `WeatherBotHandler` принимает Telegram events;
  - `WeatherConversationService` управляет состояниями, кешем ожиданий и параметрами чата;
  - `WeatherMessageBuilder` формирует тексты и кнопки;
  - `WeatherDomainService` ходит в погодный сервис и делает доменную оркестрацию;
  - все сервисы работают через `BotContext`, а не через прямой доступ к `bot`.

### `App\Services\Telegram\Handlers\VinBotHandler`
- VIN-бот
- Поддерживает:
  - `/start`
  - `/help`
  - `/vin`
  - `/subscription`
- Возможности:
  - проверка формата VIN;
  - нормализация ввода;
  - запрос к внешнему VIN API через `VinService`;
  - бесплатный режим показывает только `VIN`, `Марка`, `Модель`, `Год модели`;
  - остальные поля маскируются `**********`;
  - полный отчет доступен по счетчику `full_reports_remaining` на чат;
  - краткий отчет содержит кнопку перехода в раздел подписки;
  - раздел подписки работает через `VinSubscriptionService`;
  - списание полного отчета выполняется атомарно через `ReportBalanceService` с `DB::transaction()` и `lockForUpdate()`;
  - операции покупки и списания пишутся в `subscription_transactions`;
  - обработка ошибок API.
- Архитектура:
  - `VinBotHandler` принимает Telegram events;
  - `VinMessageBuilder` формирует тексты и кнопки;
  - `VinSubscriptionService` управляет тарифами и покупкой отчетов;
  - `ReportBalanceService` отвечает за атомарное списание баланса;
  - общий support-слой содержит `BotContext`, `CallbackAction`, `TelegramResponder`.

### `App\Services\Telegram\Handlers\DivinationBotHandler`
- Бот-предсказатель
- Поддерживает:
  - `/start`
  - `/help`
  - `/predict`
  - `/card`
- Возможности:
  - генерация случайного предсказания из встроенного набора фраз;
  - генерация случайной карты предсказания из встроенного набора карт;
  - отправка локального изображения карты из каталога `public/divination/cards`;
  - вывод названия карты, краткого значения и текстового описания;
  - обновление предсказания и карты по inline-кнопкам.
- Особенности хранения:
  - у каждой карты есть `local_image`, заданный относительно Laravel `public/`;
  - если публичная директория отделена от Laravel-кода, базовый путь задается через `PUBLIC_ASSETS_PATH` и не должен быть пустым;
  - текущая реализация предполагает обязательное наличие файла изображения для каждой карты.

## Services

### `App\Services\Telegram\Services\WeatherService`
- Работает с OpenWeather API
- Получает API-ключ и URL из `bot_settings`
- Методы:
  - `getByCoordinates(int $botId, float $lat, float $lon, array $options = [])`
  - `getByCityName(int $botId, string $cityName, array $options = [])`
  - `validateCity(int $botId, string $city)`
  - `resolveNotificationTimezoneByCoordinates(int $botId, float $lat, float $lon)`
- Особенности:
  - кэширование погодных ответов;
  - геокодинг города;
  - загрузка текущей погоды и прогноза;
  - локализация ответов на русский язык;
  - формирование payload для форматтера сообщений.

### `App\Services\Telegram\Services\WeatherMessageFormatter`
- Форматирует погодный ответ в HTML
- Поддерживает:
  - краткий режим;
  - подробный режим;
  - прогноз на 3 дня;
  - почасовые блоки прогноза;
  - локальное время города.

### `App\Services\Telegram\Services\VinService`
- Декодирует VIN через Vincario VIN Decode API
- Собирает URL формата `/3.2/{API_KEY}/{CONTROL_SUM}/decode/{VIN}.json`
- `CONTROL_SUM` считается как первые 10 символов SHA1 от `VIN|decode|API_KEY|SECRET_KEY`
- Base URL, API key и secret key берутся из `bot_settings`
- Имеет кэширование, timeout и retry-настройки

### `App\Services\Telegram\ChatLogger`
- Логирует входящие и исходящие события в `chat_logs`
- Определяет тип события:
  - `update`
  - `message`
  - `command`
  - `callback`
  - `response`

## Консоль и планировщик

### `App\Console\Commands\SendWeatherNotificationsCommand`
- Команда: `php artisan weather:send-notifications`
- Назначение: отправка запланированных погодных уведомлений
- Используется через scheduler каждую минуту
- Логика:
  - выбирает чаты с включенными уведомлениями;
  - проверяет время отправки и timezone;
  - ограничивает повторную обработку бота по интервалу из `bot_settings`;
  - отправляет погоду по сохраненным координатам;
  - сохраняет дату последней отправки.

### `routes/console.php`
- Запланировано:
  - `Schedule::command(SendWeatherNotificationsCommand::class)->everyMinute();`

## Filament

### Панели

#### `App\Providers\Filament\AdminPanelProvider`
- URL: `/admin`
- Требует аутентификацию
- Динамически настраивает:
  - `primary` цвет;
  - верхнее или левое меню;
- Подключает виджеты:
  - `BotsCountWidget`
  - `ChatsCountWidget`
  - `ClientMessagesCountWidget`

#### `App\Providers\Filament\MainPanelProvider`
- URL: `/`
- Публичная панель без auth
- Динамически настраивает:
  - `primary` цвет;
  - верхнее или левое меню;
- Показывает:
  - `Dashboard`
  - `BotsShowcaseWidget`

### Ресурсы админ-панели
- `TelegramBotResource` — CRUD ботов
- `TelegraphChatResource` — просмотр чатов
- `ChatLogResource` — просмотр логов чатов
- `BotSettingResource` — CRUD настроек конкретных ботов
- `SubscriptionPlanResource` — CRUD универсальных подписок для ботов
- `AppSettingResource` — управление глобальными настройками интерфейса

## Миграции БД
Ключевые таблицы:
- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `telegraph_bots`
- `telegraph_chats`
- `bot_settings`
- `subscription_plans`
- `chat_logs`
- `app_settings`

Основные этапы развития схемы:
- создание ботов и чатов Telegraph;
- добавление `handler_class`, `webhook_url`, `settings` в `telegraph_bots`;
- создание `bot_settings` и исправление поля `volue` -> `value`;
- создание `subscription_plans` для универсальных тарифов ботов;
- создание `chat_logs`;
- создание `app_settings`;
- расширение `telegraph_chats` погодными настройками, координатами и уведомлениями.

## Тесты
- В проекте есть только базовые примерные тесты Laravel:
  - `tests/Feature/ExampleTest.php`
  - `tests/Unit/ExampleTest.php`
- Покрытия бизнес-логики ботов, webhook-обработки и сервисов пока нет.

## Docker и деплой
- Локальный Docker Compose поднимает:
  - `nginx`
  - `php`
  - `mysql`
  - `phpmyadmin`
- В репозитории есть:
  - `.github/workflows/deploy.yml` — инкрементальный деплой по SSH/rsync
  - `scripts/deploy.sh` — release-based deploy script
  - `docs/deploy-server-checklist.md` — чеклист серверного деплоя

## Особенности и замечания
- `config/services.php` сейчас не хранит OpenWeather настройки, несмотря на старую документацию.
- Вебхук проектно идет через `TelegramWebhookController`, хотя в `telegraph.php` также указан handler.
- Для корректной работы weather-бота на каждого бота должны быть заведены `OPENWEATHER_API_KEY` и `OPENWEATHER_API_URL` в `bot_settings`.
- Для VIN-бота на каждого бота должны быть заданы `VIN_API_BASE_URL`, `VIN_API_KEY` и `VIN_API_SECRET_KEY` в `bot_settings`.
- Публичная страница и публичная Filament main-панель описывают три бота: погода, VIN и оракул.
- В боте-оракуле есть два сценария: короткое предсказание и карта предсказания с обязательным локальным изображением и толкованием.
