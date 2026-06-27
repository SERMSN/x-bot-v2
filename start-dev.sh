#!/bin/bash

# ============================================
# Файл: ~/start-dev.sh
# Описание: Автоматический запуск всей dev-инфраструктуры
# ============================================

set -e  # Остановка скрипта при любой ошибке

PROJECT_PATH="/home/wrcs/Projects/x-bot-v2"
ENV_FILE="$PROJECT_PATH/src/.env"
NGROK_LOG="/tmp/ngrok.log"

echo "🚀 Запуск dev-окружения..."

# ============================================
# 1. Запуск FlClashX с имитацией нажатия "Старт"
# ============================================
FLCLASH_EXEC=""
if command -v flclashx &> /dev/null; then
    FLCLASH_EXEC="flclashx"
elif command -v flclash &> /dev/null; then
    FLCLASH_EXEC="flclash"
else
    echo "⚠️ FlClashX не найден в системе."
    # Вы можете попробовать запустить по предполагаемому пути (например, ~/FlClash/flclash)
    # FLCLASH_EXEC="/home/wrcs/FlClash/flclash"
fi

if [ -n "$FLCLASH_EXEC" ]; then
    if pgrep -x "$FLCLASH_EXEC" > /dev/null; then
        echo "✅ $FLCLASH_EXEC уже запущен"
    else
        echo "⏳ Запуск $FLCLASH_EXEC..."
        "$FLCLASH_EXEC" &
        sleep 3 # Даем время на загрузку GUI
        echo "✅ $FLCLASH_EXEC запущен."
    fi
fi

# ============================================
# 2. Запуск редактора ZED
# ============================================
echo "⏳ Запуск ZED..."
zed "$PROJECT_PATH" &
sleep 2

# ============================================
# 3. Запуск docker-compose в терминале
# ============================================
echo "⏳ Запуск docker-compose в терминале..."
gnome-terminal -- bash -c "
    cd '$PROJECT_PATH' && 
    echo '📦 Запуск docker-compose...' && 
    docker-compose up -d && 
    echo '✅ Контейнеры запущены' && 
    docker-compose logs --tail=20 && 
    echo '📋 Журнал логов выше' && 
    exec bash
" &

# ============================================
# 4. Запуск ngrok с сохранением URL
# ============================================
echo "⏳ Запуск ngrok..."
# Сначала убиваем старые процессы ngrok
pkill -f ngrok || true

# Запускаем ngrok в фоне
ngrok http 3000 --log=stdout > "$NGROK_LOG" 2>&1 &
NGROK_PID=$!

echo "⏳ Ожидание получения URL от ngrok (до 10 секунд)..."
MAX_ATTEMPTS=20
ATTEMPT=0
NGROK_URL=""

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if [ -f "$NGROK_LOG" ]; then
        # Пытаемся извлечь URL из логов
        NGROK_URL=$(grep -o "https://[a-zA-Z0-9]*\.ngrok\.io" "$NGROK_LOG" | head -1)
        if [ -n "$NGROK_URL" ]; then
            echo "✅ Ngrok URL получен: $NGROK_URL"
            break
        fi
    fi
    sleep 1
    ATTEMPT=$((ATTEMPT + 1))
done

if [ -z "$NGROK_URL" ]; then
    echo "⚠️ Не удалось получить URL от ngrok. Проверьте логи:"
    echo "   cat $NGROK_LOG"
    echo "   Или вручную обновите APP_URL в .env"
    exit 1
fi

# ============================================
# 5. Обновление APP_URL в .env
# ============================================
echo "⏳ Обновление APP_URL в .env файле..."

if [ -f "$ENV_FILE" ]; then
    # Делаем бэкап .env
    cp "$ENV_FILE" "$ENV_FILE.backup"
    
    # Обновляем APP_URL (заменяем строку целиком)
    if grep -q "^APP_URL=" "$ENV_FILE"; then
        sed -i "s|^APP_URL=.*|APP_URL=$NGROK_URL|" "$ENV_FILE"
    else
        echo "APP_URL=$NGROK_URL" >> "$ENV_FILE"
    fi
    
    echo "✅ APP_URL обновлён на: $NGROK_URL"
    
    # Показываем измененную строку
    grep "^APP_URL=" "$ENV_FILE"
else
    echo "⚠️ Файл .env не найден по пути: $ENV_FILE"
fi

# ============================================
# 6. Запуск ngrok в отдельном терминале для видимости
# ============================================
echo "⏳ Открываем терминал с логами ngrok..."
gnome-terminal -- bash -c "
    echo '📡 Логи ngrok:' && 
    tail -f $NGROK_LOG
" &

# ============================================
# 7. Открываем браузер с тестовым URL
# ============================================
echo "⏳ Открываем браузер..."
if [ -n "$NGROK_URL" ]; then
    # Открываем URL в браузере
    if command -v google-chrome &> /dev/null; then
        google-chrome "$NGROK_URL" &
    elif command -v firefox &> /dev/null; then
        firefox "$NGROK_URL" &
    else
        echo "⚠️ Браузер не найден. Откройте вручную: $NGROK_URL"
    fi
fi

# ============================================
# Финальный вывод
# ============================================
echo ""
echo "✅ Все компоненты запущены!"
echo ""
echo "📋 Сводка:"
echo "   - FlClashX: запущен (кнопка Старт нажата)"
echo "   - ZED: открыт в проекте $PROJECT_PATH"
echo "   - Docker-compose: запущен в фоне"
echo "   - Ngrok URL: $NGROK_URL"
echo "   - APP_URL обновлён в .env"
echo ""
echo "📝 Проверить статус:"
echo "   docker-compose -f $PROJECT_PATH/docker-compose.yml ps"
echo "   tail -f $NGROK_LOG"
echo ""
echo "🔄 Для перезапуска ngrok: pkill -f ngrok && ngrok http 3000"
