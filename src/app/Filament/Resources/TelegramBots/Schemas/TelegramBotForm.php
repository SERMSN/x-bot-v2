<?php

namespace App\Filament\Resources\TelegramBots\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Http;

class TelegramBotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
                    ->schema([
                        Section::make('Основная информация')
                            ->schema([
                                TextInput::make('token')
                                            ->label('Токен бота')
                                            ->required()
                                            ->maxLength(255),
                                TextInput::make('name')
                                            ->label('Название бота')
                                            ->required()
                                            ->maxLength(255),
                        ]),
                        Section::make('Webhook информация')
                            ->schema([                
                                TextInput::make('webhook_url')
                                            ->label('Webhook URL')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$record) return 'Сначала сохраните бота';
                                                return route('webhook.telegram.token', ['bot_token' => $record->token]);
                                            }),
                                TextInput::make('webhook_status')
                                            ->label('Статус вебхука')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$record || !$record->token) return 'Не проверен';
                                                
                                                try {
                                                    $response = Http::get("https://api.telegram.org/bot{$record->token}/getWebhookInfo");
                                                    $data = $response->json();
                                                    
                                                    if ($data['ok'] && $data['result']['url']) {
                                                        return '✅ Активен';
                                                    } else {
                                                        return '❌ Не установлен';
                                                    }
                                                } catch (\Exception $e) {
                                                    return '❌ Ошибка проверки';
                                                }
                                            })
                        ]),
                ]) ; 
    }
}
