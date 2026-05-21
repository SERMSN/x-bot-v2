# X-Bot v2

Платформа для нескольких Telegram-ботов на Laravel 12, Filament 4 и DefStudio Telegraph. Проект объединяет админ-панель, публичную витрину ботов и единый webhook-роут для обработки Telegram-обновлений.

## Что есть в проекте
- погодный бот с погодой по городу и геолокации;
- VIN-бот с декодированием VIN через внешний API;
- бот-предсказатель;
- админ-панель Filament для управления ботами, чатами, настройками и логами;
- логирование входящих и исходящих сообщений в БД;
- планировщик погодных уведомлений;
- Docker Compose для локального запуска;
- GitHub Actions workflow для инкрементального деплоя.

## Стек
- PHP 8.2+
- Laravel 12
- Filament 4.x
- DefStudio Telegraph `^1.66`
- Vite 7
- Tailwind CSS 4
- MySQL 8.4 или SQLite

## Структура
```text
x-bot-v2/
├── src/                   # Laravel приложение
├── docs/                  # Документация
├── scripts/               # Скрипты деплоя
├── .docker/               # Docker окружение
├── .github/workflows/     # CI/CD
├── docker-compose.yml
└── README.md
```

## Быстрый старт без Docker
```bash
cd src
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

После этого запустите разработку:

```bash
composer run dev
```

Команда поднимает:
- `php artisan serve`
- `php artisan queue:listen --tries=1`
- `php artisan pail --timeout=0`
- `npm run dev`

## Быстрый старт через Docker
```bash
docker-compose up -d
docker-compose exec php composer install
docker-compose exec php npm install
docker-compose exec php cp .env.example .env
docker-compose exec php php artisan key:generate
docker-compose exec php php artisan migrate
```

Сервисы:
- приложение: `http://localhost`
- phpMyAdmin: `http://localhost:8080`

## Основные маршруты
- `/` — публичная панель Filament
- `/admin` — админ-панель Filament
- `/telegram/{token}` — webhook Telegram
- `/up` — health-check

## Конфигурация
Базовые настройки берутся из `src/.env`. В `.env.example` уже есть:
- подключение к БД;
- database-драйверы для cache, queue и session;
- TTL и timeout-настройки Telegram/weather/VIN интеграций.

Важно понимать разделение настроек:

### Через `.env`
- `APP_*`
- `DB_*`
- `TELEGRAPH_*`
- `TELEGRAM_WEBHOOK_DEDUP_TTL_MINUTES`
- `WEATHER_*`
- `VIN_*`

### Через таблицу `bot_settings`
Настройки задаются отдельно для каждого бота в админке:
- `OPENWEATHER_API_KEY`
- `OPENWEATHER_API_URL`
- `WEATHER_NOTIFICATION_RUN_INTERVAL_MINUTES`
- `VIN_API_DECODE_URL`

### Через таблицу `app_settings`
Глобальные настройки интерфейса:
- цвет `primary` для `/admin` и `/`
- верхнее или левое меню для каждой панели

## Модели и ресурсы

### Основные модели
- `App\Models\TelegramBot`
- `App\Models\TelegraphChat`
- `App\Models\BotSetting`
- `App\Models\AppSetting`
- `App\Models\ChatLog`
- `App\Models\User`

### Filament ресурсы
- `TelegramBotResource`
- `TelegraphChatResource`
- `BotSettingResource`
- `AppSettingResource`
- `ChatLogResource`

## Боты

### Weather bot
Возможности:
- `/start`, `/help`, `/weather`, `/setting`, `/subscription`
- погода по городу;
- погода по геолокации;
- сохраненный город;
- краткий и подробный формат ответа;
- `metric` и `imperial`;
- ежедневные уведомления;
- автосохранение города после локации.

Зависимости:
- `OPENWEATHER_API_KEY`
- `OPENWEATHER_API_URL`

### VIN bot
Возможности:
- `/start`, `/help`, `/vin`
- проверка структуры VIN;
- запрос к внешнему decode API;
- вывод марки, модели, года и других атрибутов.

Зависимости:
- `VIN_API_DECODE_URL`

### Divination bot
Возможности:
- `/start`, `/help`, `/predict`, `/card`
- случайные короткие предсказания через inline-кнопки;
- случайная карта предсказания с локальным изображением, названием, значением и описанием;
- переключение между обычным предсказанием и картой из меню ответа.

Ресурсы карт:
- изображения карт хранятся локально в `src/public/divination/cards/`;
- путь `local_image` в коде задается относительно Laravel `public/`, например `divination/cards/sun.jpg`.
- сценарий карты предполагает обязательное наличие изображения для каждой карты в локальном каталоге.
- если на сервере публичная директория отделена от Laravel-кода, задайте `PUBLIC_ASSETS_PATH`, например `/var/www/x-bot.su/public_html`.

## Планировщик
В `src/routes/console.php` настроена задача:

```php
Schedule::command(SendWeatherNotificationsCommand::class)->everyMinute();
```

Для production нужен работающий scheduler Laravel:

```bash
php artisan schedule:work
```

или cron с `php artisan schedule:run`.

## Тесты
Сейчас в проекте есть только базовые примеры Laravel:

```bash
cd src
php artisan test
```

Отдельных тестов на webhook, обработчики ботов и сервисы пока нет.

## Docker Compose
В `docker-compose.yml` описаны:
- `nginx`
- `php`
- `mysql`
- `phpmyadmin`

По умолчанию MySQL создается с БД `xbotv2`.

## Деплой
В репозитории есть два направления:
- `.github/workflows/deploy.yml` — инкрементальная выгрузка измененных файлов по SSH/rsync;
- `scripts/deploy.sh` — отдельный release-based deploy script.

Дополнительная инструкция:
- [docs/deploy-server-checklist.md](docs/deploy-server-checklist.md)

Замечание: в текущем workflow деплой настроен на ветку `master`, а в чеклисте указана `main`. Перед реальным использованием это нужно привести к одному варианту.

## Полезные команды
```bash
cd src
php artisan migrate
php artisan optimize:clear
php artisan config:cache
php artisan route:list
php artisan about
php artisan weather:send-notifications
php artisan test
```

## Что важно помнить
- боты не настраиваются через `TELEGRAPH_BOT_TOKEN` в `.env`, они хранятся в таблице `telegraph_bots`;
- выбор обработчика идет по полю `handler_class` и ключам из `config/bots.php`;
- webhook защищается секретом `TELEGRAPH_WEBHOOK_SECRET`, если он задан;
- входящие и исходящие события сохраняются в `chat_logs`;
- для `/telegram/*` отключена CSRF-проверка;
- проект уже использует публичную Filament main-панель, а не только статический лендинг.
