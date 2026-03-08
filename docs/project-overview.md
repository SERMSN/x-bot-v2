# X-Bot v2: описание проекта

## Кратко
X-Bot v2 — проект на Laravel 12 с админ-панелью Filament 4.x для управления Telegram-ботами. Интеграция с Telegram реализована через пакет Telegraph. В проекте предусмотрены обработчики команд/вебхуков для конкретных ботов (например, погода и VIN), а также админ-интерфейс для управления ботами и чатами.

## Технологии и зависимости
- Backend: Laravel 12
- Admin UI: Filament 4.x
- Telegram: DefStudio Telegraph
- Weather API: OpenWeather (через `OPENWEATHER_API_KEY`)
- Frontend сборка: Vite (из `package.json`)
- База данных: SQLite или MySQL

## Точки входа и маршруты
- Вебхук Telegram:
  - `POST /telegram/{bot_token}`
  - Контроллер: `App\Http\Controllers\TelegramWebhookController`
  - Логика: ищет бота по токену в `telegraph_bots`, затем делегирует обработку `App\Services\Telegram\Handlers\Handler`.

## Конфигурация и переменные окружения
Ключевые переменные окружения и настройки:
- `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `OPENWEATHER_API_KEY` (используется `WeatherService` через `config/services.php`)
- Telegraph (`config/telegraph.php`):
  - `TELEGRAM_WEBHOOK_DOMAIN`
  - `TELEGRAPH_WEBHOOK_URL`
  - `TELEGRAPH_REPORT_UNKNOWN_COMMANDS`
  - `TELEGRAPH_WEBHOOK_SECRET`
  - `TELEGRAPH_WEBHOOK_MAX_CONNECTIONS`
  - `TELEGRAPH_WEBHOOK_DEBUG`
  - `TELEGRAPH_HTTP_TIMEOUT`
  - `TELEGRAPH_HTTP_PROXY`
  - `TELEGRAPH_HTTP_CONNECTION_TIMEOUT`

Важно:
- В `config/telegraph.php` задан обработчик вебхуков `App\Services\Telegram\Handlers\Handler`.
- Боты хранятся в БД, а не в `.env`.

## Структура проекта
```
x-bot-v2/
├── src/                    # Laravel приложение
│   ├── app/                # Основной код
│   ├── config/             # Конфигурация
│   ├── database/           # Миграции и фабрики
│   ├── routes/             # Маршруты
│   ├── resources/          # Шаблоны и ассеты
│   ├── tests/              # Тесты
│   └── ...
├── docs/                   # Документация (этот файл)
├── docker-compose.yml      # Docker Compose
└── README.md               # Инструкция
```

## Модели

### `App\Models\User`
- Таблица: `users`
- Поля: `name`, `email`, `password`, `email_verified_at`, `remember_token`
- Используется для аутентификации Filament.

### `App\Models\TelegramBot`
- Наследуется от `DefStudio\Telegraph\Models\TelegraphBot`
- Таблица: `telegraph_bots`
- Поля:
  - `token` (unique)
  - `name`
  - `handler_class` (nullable)
  - `webhook_url` (nullable)
  - `settings` (json, nullable)
- Назначение: хранит Telegram-ботов и их настройки/обработчики.

### `App\Models\TelegraphChat`
- Таблица: `telegraph_chats`
- Поля:
  - `chat_id`
  - `name`
  - `telegraph_bot_id` (FK)
- Связи:
  - `belongsTo(TelegramBot::class, 'telegraph_bot_id')`

## Контроллеры

### `App\Http\Controllers\TelegramWebhookController`
- Метод: `handleWebhook(Request $request, string $bot_token)`
- Задачи:
  - Логирует входящий вебхук.
  - Ищет бота по токену.
  - Передает управление центральному хендлеру `App\Services\Telegram\Handlers\Handler`.
  - Возвращает JSON-ответ, корректно обрабатывает ошибки.

### `App\Http\Controllers\Controller`
- Базовый контроллер Laravel (без специфической логики).

## Handlers (обработчики Telegram)

### `App\Services\Telegram\Handlers\Handler`
- Центральный роутер хендлеров.
- Карта обработчиков:
  - `weather` -> `WeatherBotHandler`
  - `vin` -> `VinBotHandler`
- Выбор хендлера идёт по `TelegraphBot::handler_class`.

### `App\Services\Telegram\Handlers\WeatherBotHandler`
- Реализует команды и callback-логику погодного бота.
- Поддержка:
  - `/start`, `/help`, `/weather`
  - Работа с локацией пользователя.
  - Inline-кнопки для действий.
- Использует `WeatherService` для получения погоды по координатам.

### `App\Services\Telegram\Handlers\VinBotHandler`
- Реализует команды и callback-логику VIN-декодера.
- Поддержка:
  - `/start`, `/help`, `/vin`
  - Проверка VIN и тестовый запрос.
- Сейчас отдаёт тестовые данные (заглушка для интеграции с внешним API).

## Services

### `App\Services\Telegram\Services\WeatherService`
- Запросы к OpenWeather API.
- Методы:
  - `getByCoordinates(float $lat, float $lon)`
  - `getByCityName(string $cityName)`
  - `validateCity(string $city)`
- Кэширование результатов через Laravel Cache.
- Форматирует ответ и иконку погоды.

## Filament (админ-панель)

### Панели
- `App\Providers\Filament\AdminPanelProvider`
  - URL: `/admin`
  - Включена аутентификация
  - Ресурсы: боты и чаты
- `App\Providers\Filament\MainPanelProvider`
  - URL: `/`
  - Использует top navigation, без auth
  - Подходит как публичный интерфейс/лендинг

### Ресурсы
- `TelegramBotResource`
  - Управление таблицей `telegraph_bots`
- `TelegraphChatResource`
  - Управление таблицей `telegraph_chats`

## Миграции БД (ключевые)
- `create_telegraph_bots_table`:
  - `token`, `name`
- `add_custom_fields_to_telegraph_bots_table`:
  - `handler_class`, `webhook_url`, `settings`
- `create_telegraph_chats_table`:
  - `chat_id`, `name`, `telegraph_bot_id`
  - unique индекс `chat_id + telegraph_bot_id`

## Особенности и замечания
- В `config/telegraph.php` указано хранение неизвестных чатов в БД (`store_unknown_chats_in_db` = true).
- В `Handler` используются короткие ключи `weather`, `vin`. Это должно совпадать со значением `handler_class` у бота в таблице `telegraph_bots`.
- `WeatherBotHandler` частично использует мок-данные и частично реальные данные через OpenWeather.
- `VinBotHandler` полностью на тестовых данных и готов к подключению внешнего VIN API.
