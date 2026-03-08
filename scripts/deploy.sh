#!/usr/bin/env bash

set -Eeuo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/x-bot-v2}"
SOURCE_DIR="${SOURCE_DIR:-$DEPLOY_PATH/source}"
RELEASES_DIR="${RELEASES_DIR:-$DEPLOY_PATH/releases}"
SHARED_DIR="${SHARED_DIR:-$DEPLOY_PATH/shared}"
CURRENT_LINK="${CURRENT_LINK:-$DEPLOY_PATH/current}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
BUILD_FRONTEND="${BUILD_FRONTEND:-false}"
NPM_BIN="${NPM_BIN:-npm}"

APP_ENV_FILE="${APP_ENV_FILE:-$SHARED_DIR/.env}"
STORAGE_SHARED_DIR="${STORAGE_SHARED_DIR:-$SHARED_DIR/storage}"
RELOAD_COMMAND="${RELOAD_COMMAND:-}"
APP_SUBDIR="${APP_SUBDIR:-src}"

RELEASE_ID="$(date +%Y%m%d%H%M%S)"
NEW_RELEASE_DIR="$RELEASES_DIR/$RELEASE_ID"

echo "[deploy] Start release: $RELEASE_ID"

mkdir -p "$RELEASES_DIR" "$SHARED_DIR" "$STORAGE_SHARED_DIR"

if [[ ! -f "$APP_ENV_FILE" ]]; then
  echo "[deploy] ERROR: missing env file: $APP_ENV_FILE"
  exit 1
fi

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "[deploy] ERROR: missing source dir: $SOURCE_DIR"
  exit 1
fi

mkdir -p "$NEW_RELEASE_DIR"
rsync -a --delete \
  --exclude ".git" \
  --exclude "node_modules" \
  --exclude "vendor" \
  --exclude "storage" \
  "$SOURCE_DIR/" "$NEW_RELEASE_DIR/"

APP_RELEASE_DIR="$NEW_RELEASE_DIR/$APP_SUBDIR"

if [[ ! -d "$APP_RELEASE_DIR" ]]; then
  echo "[deploy] ERROR: app subdir not found: $APP_RELEASE_DIR"
  exit 1
fi

ln -sfn "$APP_ENV_FILE" "$APP_RELEASE_DIR/.env"
rm -rf "$APP_RELEASE_DIR/storage"
ln -sfn "$STORAGE_SHARED_DIR" "$APP_RELEASE_DIR/storage"
mkdir -p "$APP_RELEASE_DIR/bootstrap/cache"

cd "$APP_RELEASE_DIR"

echo "[deploy] Composer install"
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [[ "$BUILD_FRONTEND" == "true" ]]; then
  if command -v "$NPM_BIN" >/dev/null 2>&1; then
    echo "[deploy] Frontend build"
    "$NPM_BIN" ci
    "$NPM_BIN" run build
  else
    echo "[deploy] WARN: npm not found, skip frontend build"
  fi
fi

echo "[deploy] Migrations"
"$PHP_BIN" artisan migrate --force

echo "[deploy] Cache warmup"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "[deploy] Switch current symlink"
ln -sfn "$NEW_RELEASE_DIR" "$CURRENT_LINK"

echo "[deploy] Queue restart"
"$PHP_BIN" artisan queue:restart || true

if [[ -n "$RELOAD_COMMAND" ]]; then
  echo "[deploy] Reload services"
  bash -lc "$RELOAD_COMMAND"
fi

echo "[deploy] Cleanup old releases"
cd "$RELEASES_DIR"
ls -1dt */ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "[deploy] Done release: $RELEASE_ID"
