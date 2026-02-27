# Checklist: Timeweb SSH + GitHub Actions (инкрементальный деплой)

Этот чеклист соответствует текущему workflow `.github/workflows/deploy.yml`:
- заливаются только изменённые файлы между прошлым и текущим push в `main`
- `src/*` -> `/home/w/wrcss/x-bot/laravel`
- `src/public/*` -> `/home/w/wrcss/x-bot/public_html`

## 1. Подготовить директории на хостинге

Подключитесь по SSH и выполните:

```bash
mkdir -p /home/w/wrcss/x-bot/laravel
mkdir -p /home/w/wrcss/x-bot/public_html
```

## 2. Проверить окружение PHP/Composer на сервере

```bash
php -v
composer -V
```

Если `composer` отсутствует, установите его через панель Timeweb или вручную в ваш `$PATH`.

## 3. Первичная полная синхронизация (один раз)

Нужна, чтобы на сервере был полный проект до первого инкрементального деплоя.

Запустите локально из корня репозитория `x-bot-v2`:

```bash
rsync -az --delete -e "ssh -p <PORT>" src/ <USER>@<HOST>:/home/w/wrcss/x-bot/laravel/
rsync -az --delete -e "ssh -p <PORT>" src/public/ <USER>@<HOST>:/home/w/wrcss/x-bot/public_html/
```

## 4. Подготовить `.env` на сервере

Файл:
- `/home/w/wrcss/x-bot/laravel/.env`

Минимум:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<ваш-домен>`
- `DB_*`
- `TELEGRAPH_WEBHOOK_SECRET=<secret>` (если используете)

## 5. Первая инициализация Laravel на сервере

```bash
cd /home/w/wrcss/x-bot/laravel
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 6. Права на служебные директории

```bash
cd /home/w/wrcss/x-bot/laravel
chmod -R 775 storage bootstrap/cache
```

## 7. SSH-ключ для GitHub Actions

Локально:

```bash
ssh-keygen -t ed25519 -f github_actions_deploy_key -C "github-actions-deploy"
```

Добавьте `github_actions_deploy_key.pub` в `~/.ssh/authorized_keys` пользователя Timeweb, под которым выполняется SSH.

Права:

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

## 8. GitHub Secrets

Repository -> Settings -> Secrets and variables -> Actions -> Secrets:

- `DEPLOY_SSH_KEY` -> содержимое `github_actions_deploy_key` (приватный)
- `DEPLOY_HOST` -> SSH host Timeweb
- `DEPLOY_PORT` -> SSH port (обычно `22`)
- `DEPLOY_USER` -> SSH user

Важно:
- `DEPLOY_PATH` больше не используется в текущем workflow.

## 9. Первый автодеплой

1. Сделайте commit и push в `main`.
2. Откройте GitHub Actions -> `Deploy Production`.
3. Убедитесь, что шаги прошли:
- `Prepare changed files list`
- `Upload changed files to /laravel`
- `Upload changed files to /public_html`

## 10. Что делать после каждого деплоя

Сейчас workflow только копирует изменённые файлы и удаляет удалённые.  
Если в push были:
- изменения `composer.json` / `composer.lock`
- новые миграции
- изменения в конфиге Laravel

нужно вручную выполнить на сервере:

```bash
cd /home/w/wrcss/x-bot/laravel
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 11. Быстрый чек после деплоя

```bash
cd /home/w/wrcss/x-bot/laravel
php artisan about
php artisan route:list | grep telegram
```

Проверить в браузере:
- сайт
- `/admin`
- webhook-бот (`/start` в Telegram)
