# X-Bot v2.0
## Платформа интеллектуальных Telegram-ботов

Экосистема интеллектуальных Telegram-ботов для автоматизации бизнеса и повседневных задач. Каждый бот разработан с нуля с учетом надежности, производительности и удобства использования.

---

## 📋 Содержание

- [Описание](#описание)
- [Возможности](#возможности)
- [Требования](#требования)
- [Установка](#установка)
- [Конфигурация](#конфигурация)
- [Запуск](#запуск)
- [Тестирование](#тестирование)
- [Структура проекта](#структура-проекта)
- [Решение проблем](#решение-проблем)

---

## 📝 Описание

X-Bot v2.0 — это приложение, построенное на **Laravel 12** и **Filament PHP 4.0**, предназначенное для управления и развертывания умных Telegram-ботов.

Приложение включает:
- Полнофункциональную административную панель на базе Filament
- Управление ботами Telegram через Telegraph
- Современный стек веб-технологий (Laravel + Filament)
- Docker поддержку для быстрого развертывания

---

## ✨ Возможности

- 🤖 Создание и управление Telegram-ботами
- 🎛️ Административная панель (Filament PHP)
- 💾 SQLite и MySQL базы данных
- 🐳 Docker Compose конфигурация
- 👥 Система управления пользователями
- 📊 Управление чатами и сообщениями
- ⚙️ Расширяемая архитектура

---

## 🔧 Требования

- **PHP**: 8.2 или выше
- **Composer**: последняя версия
- **Node.js**: 18.x или выше (для Vite)
- **Docker**: 20.10+ (опционально)
- **Docker Compose**: 2.0+ (опционально)
- **MySQL**: 8.4 или SQLite (встроена)

---

## 📦 Установка

### Без Docker

```bash
# Клонируйте репозиторий
git clone <repo-url>
cd x-bot-v2

# Установите PHP зависимости
cd src
composer install

# Установите Node.js зависимости
npm install

# Скопируйте .env файл
cp .env.example .env
```

### С Docker

```bash
# Разверните контейнеры
docker-compose up -d

# Установите зависимости внутри контейнера PHP
docker-compose exec php composer install
docker-compose exec php npm install
```

---

## ⚙️ Конфигурация

### Переменные окружения

Отредактируйте файл `.env` в директории `src/`:

```env
APP_NAME=X-Bot
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# База данных (SQLite или MySQL)
DB_CONNECTION=sqlite
# или для MySQL:
# DB_CONNECTION=mysql
# DB_HOST=localhost
# DB_PORT=3306
# DB_DATABASE=xbotv2
# DB_USERNAME=root
# DB_PASSWORD=password

# Telegraph (Telegram Bot API)
TELEGRAPH_BOT_TOKEN=ваш_bot_token_здесь
TELEGRAPH_BOT_ID=ваш_bot_id_здесь
```

### Миграции БД

```bash
# Запустите миграции
php artisan migrate

# С сидированием (опционально)
php artisan migrate:seed
```

---

## 🚀 Запуск

### Локальная разработка

```bash
cd src

# Запустите Laravel dev сервер
php artisan serve

# В отдельном терминале: запустите Vite для фронтенда
npm run dev

# Откройте в браузере
http://localhost:8000
```

### Из Docker

```bash
# Откройте браузер
http://localhost

# Просмотр логов
docker-compose logs -f php
```

### Консольные команды

```bash
# Tinker интерактивная оболочка
php artisan tinker

# Очистить кэш
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## 🧪 Тестирование

```bash
cd src

# Запустите тесты PHPUnit
php artisan test

# Запустите тесты с отчетом об охвате
php artisan test --coverage
```

---

## 📂 Структура проекта

```
x-bot-v2/
├── src/                    # Основной код приложения Laravel
│   ├── app/
│   │   ├── Models/         # Модели БД
│   │   ├── Http/           # Контроллеры
│   │   ├── Services/       # Бизнес-логика
│   │   ├── Console/        # Artisan команды
│   │   └── Filament/       # Панель администратора
│   ├── config/             # Конфигурационные файлы
│   ├── database/
│   │   ├── migrations/     # Миграции БД
│   │   └── factories/      # Фабрики для тестов
│   ├── routes/             # Маршруты приложения
│   ├── resources/          # Views и стили
│   ├── storage/            # Хранилище логов и файлов
│   ├── tests/              # Тесты
│   ├── composer.json       # PHP зависимости
│   └── package.json        # Node.js зависимости
├── .docker/                # Docker конфигурации
│   ├── php/                # Dockerfile для PHP
│   └── nginx/              # Конфигурация Nginx
├── docker-compose.yml      # Docker Compose конфигурация
└── README.md               # Этот файл
```

---

## 🔍 Главные компоненты

### Models (Модели)
- **User** — пользователи приложения
- **TelegramBot** — информация о Telegram-ботах
- **TelegraphChat** — чаты и беседы

### Services
- **Telegram Service** — интеграция с Telegram API через Telegraph

### Filament
- Административная панель для управления ботами и пользователями

---

## ❓ Решение проблем

### Ошибка: "База данных заблокирована"
```bash
# Переинициализируйте БД
rm src/database/database.sqlite
php artisan migrate
```

### Ошибка: "Composer конфликт зависимостей"
```bash
cd src
composer install --no-cache
```

### Docker контейнеры не запускаются
```bash
# Проверьте логи
docker-compose logs

# Пересоберите образы
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Нет доступа к административной панели
- По умолчанию панель доступна по адресу `/admin`
- Создайте пользователя через консоль: `php artisan tinker`

---

## 📚 Документация

- [Laravel Documentation](https://laravel.com/docs)
- [Filament Documentation](https://filamentphp.com)
- [Telegraph Documentation](https://github.com/defstudio/telegraph)

---

## 📄 Лицензия

Проект лицензирован под лицензией MIT. Подробнее см. в файле `LICENSE`.

---

## 👥 Контакты и поддержка

Для вопросов, предложений и багрепортов, пожалуйста:
- Создайте Issue в репозитории
- Свяжитесь с командой разработчиков

---

**X-Bot v2.0** © 2026 | Построено на Laravel и Filament
