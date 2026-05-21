# Checklist: VPS (SSH по паролю) + GitHub Actions

Актуальные вводные:
- SSH хост: задается в GitHub Secret `DEPLOY_HOST`
- SSH пользователь: задается в GitHub Secret `DEPLOY_USER`
- Авторизация: логин/пароль (без SSH-ключей)
- Ветка деплоя: `master`
- Тип деплоя: инкрементальный (только измененные файлы между push)
- Пути на сервере:
`src/*` -> `/var/www/x-bot.su/src`
`src/public/*` -> `/var/www/x-bot.su/public_html`

Важно:
- SSH пароль не хранить в репозитории и документации, только в GitHub Secrets.
- Если пароль уже был опубликован, смените его в панели хостинга и обновите Secret `DEPLOY_PASSWORD`.

## 1. Проверить доступ по SSH вручную

На локальной машине:

```bash
ssh <DEPLOY_USER>@<DEPLOY_HOST>
```

Если вход успешный, продолжаем настройку CI.

## 2. Подготовить директории на сервере

После входа на сервер:

```bash
mkdir -p /var/www/x-bot.su/src
mkdir -p /var/www/x-bot.su/public_html
```

## 3. Первичная полная заливка (один раз)

Инкрементальный workflow не заменяет первый полный деплой.  
Из корня репозитория `x-bot-v2` на локальной машине:

```bash
rsync -az --delete -e "ssh -p 22" src/ <DEPLOY_USER>@<DEPLOY_HOST>:/var/www/x-bot.su/src/
rsync -az --delete -e "ssh -p 22" src/public/ <DEPLOY_USER>@<DEPLOY_HOST>:/var/www/x-bot.su/public_html/
```

## 4. Первая инициализация Laravel на сервере

На сервере:

```bash
cd /var/www/x-bot.su/src
cp .env.example .env
nano .env
```

Минимальные параметры `.env`:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<ваш-домен>`
- `PUBLIC_ASSETS_PATH=/var/www/x-bot.su/public_html`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD="..."` (в кавычках, если есть спецсимволы, например `#`)
- `TELEGRAPH_WEBHOOK_SECRET=<secret>` (если используется)

Инициализация:

```bash
cd /var/www/x-bot.su/src
/opt/php84/bin/php /opt/php84/bin/composer install --no-dev --optimize-autoloader
/opt/php84/bin/php artisan key:generate
/opt/php84/bin/php artisan migrate --force
/opt/php84/bin/php artisan optimize:clear
/opt/php84/bin/php artisan config:cache
/opt/php84/bin/php artisan route:cache
/opt/php84/bin/php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

## 5. Проверить workflow `.github/workflows/deploy.yml`

В текущем `deploy.yml` уже реализовано:
- установка `sshpass` на GitHub runner;
- вычисление измененных файлов между `github.event.before` и `github.sha`;
- раздельная выгрузка:
  - `src/*` в `/var/www/x-bot.su/src`
  - `src/public/*` в `/var/www/x-bot.su/public_html`;
- удаление удаленных из git файлов на сервере.

Что должно остаться в workflow:
1. `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`, `DEPLOY_PASSWORD` берутся только из Secrets.
2. Все `ssh` и `rsync` команды вызываются через `sshpass -p "$DEPLOY_PASSWORD"`.
3. Триггер только на `push` в `master`.

## 6. Настроить GitHub Secrets

GitHub -> Repository -> Settings -> Secrets and variables -> Actions -> Secrets:

- `DEPLOY_HOST` = `<VPS host или IP>`
- `DEPLOY_PORT` = `22`
- `DEPLOY_USER` = `<SSH user>`
- `DEPLOY_PASSWORD` = `<текущий SSH пароль>`

Примечания:
- `DEPLOY_SSH_KEY` в этом варианте не используется.
- После смены пароля на хостинге сразу обновляйте `DEPLOY_PASSWORD`.

## 7. Первый автодеплой через GitHub Actions

1. Сделать commit.
2. Выполнить `git push origin master`.
3. Открыть GitHub Actions -> workflow `Deploy Production`.
4. Проверить, что прошли шаги:
- `Install sshpass`
- `Prepare changed files list`
- `Ensure remote dirs`
- `Upload changed files to Laravel src`
- `Upload changed public files to public_html`
- `Delete removed files on server`

## 8. Как проходит каждое следующее обновление (push в `master`)

При каждом `push` в `master` workflow автоматически:
1. Считает разницу файлов между предыдущим и текущим commit.
2. Загружает только измененные файлы в нужные директории.
3. Удаляет на сервере файлы, удаленные в git.

Это быстро, но не выполняет автоматически post-deploy команды Laravel.

## 9. Что выполнять вручную после деплоя (когда нужно)

Если в push были изменения в:
- `composer.json` / `composer.lock`
- `database/migrations/*`
- `.env`-зависимой конфигурации
- маршрутах/шаблонах/кэше

выполнить на сервере:

```bash
cd /var/www/x-bot.su/src
/opt/php84/bin/php /opt/php84/bin/composer install --no-dev --optimize-autoloader
/opt/php84/bin/php artisan migrate --force
/opt/php84/bin/php artisan optimize:clear
/opt/php84/bin/php artisan config:cache
/opt/php84/bin/php artisan route:cache
/opt/php84/bin/php artisan view:cache
```

## 10. Быстрая проверка после каждого деплоя

На сервере:

```bash
cd /var/www/x-bot.su/src
/opt/php84/bin/php artisan about
/opt/php84/bin/php artisan route:list | grep telegram
```

Проверить вручную:
- главную страницу сайта;
- `/admin`;
- Telegram-бота командой `/start`.
