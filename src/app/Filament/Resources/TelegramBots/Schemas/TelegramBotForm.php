<?php

namespace App\Filament\Resources\TelegramBots\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Http;

class TelegramBotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make("Основная информация")->schema([
                TextInput::make("token")
                    ->label("Токен бота")
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255),
                TextInput::make("name")
                    ->label("Название бота")
                    ->required()
                    ->maxLength(255),
                Select::make("handler_class")
                    ->label("Хендлер")
                    ->options(function () {
                        $handlers = (array) config("bots.handlers", []);
                        $keys = array_keys($handlers);
                        return array_combine($keys, $keys) ?: [];
                    })
                    ->required()
                    ->helperText(
                        "Выберите тип бота (ключ из config/bots.php).",
                    ),
            ]),
            Section::make("Webhook информация")->schema([
                TextInput::make("webhook_url")
                    ->label("Webhook URL")
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record) {
                            return "Сначала сохраните бота";
                        }
                        return route("webhook.telegram.token", [
                            "token" => $record->token,
                        ]);
                    }),
                TextInput::make("webhook_status")
                    ->label("Статус вебхука")
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record || !$record->token) {
                            return "Не проверен";
                        }

                        try {
                            $telegramApiBase =
                                rtrim(
                                    (string) config(
                                        "telegraph.telegram_api_url",
                                        "https://api.telegram.org/",
                                    ),
                                    "/",
                                ) . "/";
                            $response = Http::get(
                                "{$telegramApiBase}bot{$record->token}/getWebhookInfo",
                            );
                            $data = $response->json();

                            if ($data["ok"] && $data["result"]["url"]) {
                                return "✅ Активен";
                            } else {
                                return "❌ Не установлен";
                            }
                        } catch (\Exception $e) {
                            return "❌ Ошибка проверки";
                        }
                    }),
            ]),
        ]);
    }
}
