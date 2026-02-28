# Checklist: Timeweb (SSH по паролю) + GitHub Actions

Текущие вводные:
- SSH хост: `vh358.timeweb.ru`
- SSH пользователь: `wrcss`
- Авторизация: логин/пароль (без SSH-ключей)
- Инкрементальный деплой: только изменённые файлы между push в `main`
- Пути выгрузки:
`src/*` -> `~/x-bot2/src`
`src/public/*` -> `~/x-bot2/public_html`

## 1. Проверить доступ по SSH вручную

Локально:

```bash
ssh wrcss@vh358.timeweb.ru
```

Если вход успешный, можно настраивать CI.

## 2. Подготовить директории на хостинге

После входа по SSH:

```bash
mkdir -p ~/x-bot2/src
mkdir -p ~/x-bot2/public_html
```

## 3. Первичная полная заливка (один раз)

Инкрементальный workflow не заменяет первую полную синхронизацию.

Локально из корня репозитория `x-bot-v2`:

```bash
rsync -az --delete -e "ssh -p 22" src/ wrcss@vh358.timeweb.ru:~/x-bot2/src/
rsync -az --delete -e "ssh -p 22" src/public/ wrcss@vh358.timeweb.ru:~/x-bot2/public_html/
```

## 4. Подготовить `.env` и выполнить первую инициализацию Laravel

На сервере:

```bash
cd ~/x-bot2/src
cp .env.example .env
nano .env
```

Минимум в `.env`:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<ваш-домен>`
- `DB_*`
- `TELEGRAPH_WEBHOOK_SECRET=<secret>` (если используете)

Далее:

```bash
cd ~/x-bot2/src
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

## 5. Изменить workflow на вход по паролю

Так как ключи не используем, в `.github/workflows/deploy.yml` нужно применять `sshpass`.

Логика:
1. Установить `sshpass` в раннере.
2. Все `ssh` и `rsync` запускать через `sshpass -p "$DEPLOY_PASSWORD"`.

Пример шага:

```bash
sudo apt-get update
sudo apt-get install -y sshpass

sshpass -p "$DEPLOY_PASSWORD" ssh -o StrictHostKeyChecking=no -p "${DEPLOY_PORT:-22}" "${DEPLOY_USER}@${DEPLOY_HOST}" "mkdir -p ~/x-bot2/src ~/x-bot2/public_html"

sshpass -p "$DEPLOY_PASSWORD" rsync -az --files-from=/tmp/upload_laravel.txt \
  -e "ssh -o StrictHostKeyChecking=no -p ${DEPLOY_PORT:-22}" \
  src/ "${DEPLOY_USER}@${DEPLOY_HOST}:~/x-bot2/src/"
```

## 6. GitHub Secrets

Repository -> Settings -> Secrets and variables -> Actions -> Secrets:

- `DEPLOY_HOST` = `vh358.timeweb.ru`
- `DEPLOY_PORT` = `22`
- `DEPLOY_USER` = `wrcss`
- `DEPLOY_PASSWORD` = `<SSH пароль>`

Важно:
- `DEPLOY_SSH_KEY` в этом варианте не нужен.
- Пароль хранить только в Secrets.

## 7. Первый автодеплой через GitHub

1. Сделайте commit и push в `main`.
2. Откройте GitHub Actions -> `Deploy Production`.
3. Убедитесь, что прошли шаги:
- построение списка изменённых файлов
- выгрузка в `~/x-bot2/src`
- выгрузка в `~/x-bot2/public_html`
- удаление удалённых файлов

## 8. Что делать после каждого деплоя

Workflow копирует изменённые файлы, но не всегда запускает post-deploy команды Laravel.  
Если были изменения зависимостей/миграций/конфига, на сервере вручную:

```bash
cd ~/x-bot2/src
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 9. Быстрый чек после деплоя

```bash
cd ~/x-bot2/src
php artisan about
php artisan route:list | grep telegram
```

Проверить:
- сайт
- `/admin`
- бот в Telegram (`/start`)
