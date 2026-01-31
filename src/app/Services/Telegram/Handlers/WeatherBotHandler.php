<?php

namespace App\Services\Telegram\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Stringable;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;


class WeatherBotHandler extends WebhookHandler
{
    
    public function start(): void
    {
        $message = "🌤️ *Добро пожаловать в погодный бот!*\n\n";
        $message .= "Я помогу вам узнать погоду в любом городе или по вашей локации!\n\n";
        $message .= "⬇️ *Используйте кнопки ниже:*";
        /*
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard
                    ->button('📍 По локации')->action('get_weather_location')
                    ->button('🏙️ По городу')->action('get_weather_city')
                    ->button('❓ Помощь')->action('help')
                    ->button('⚙ Настройка')->action('setting');       
                $keyboard->chunk(2); 
                
                return $keyboard;
            })
            ->send();
            */
            $this->chat->message('Это сообщение с кнопками. Выбери действие:')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('🗑️ Удалить сообщение')->action('delete'),
                Button::make('📖 Поделиться мудростью')->action('read'),
                Button::make('👀 Открыть ссылку')->url('https://timeweb.cloud/'),
            ])->chunk(2))->send();
    }
    
    public function help(): void
    {
        $message = "📚 *Помощь по погодному боту*\n\n";
        $message .= "Доступные команды:\n";
        $message .= "/start - Начало работы\n";
        $message .= "/help - Эта справка\n";
        $message .= "/weather - Получить погоду\n\n";
        $message .= "Просто нажмите кнопку 'Получить погоду'!";
        
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button('🌤️ Получить погоду')->action('get_weather');
                $keyboard->button('🏠 На главную')->action('start');
                return $keyboard;
            })
            ->send();
    }

    /** * Обрабатывает нажатие на кнопку с action("delete") */
    public function delete(): void {
        $this->reply('Сообщение удалено');
        $this->chat->deleteMessage($this->messageId)->send();
    }
    
    public function weather(): void
    {
        $this->handleGetWeather();
    }
    
    /**
     * Обработка нажатия на кнопку "Получить погоду"
     */
    public function handleGetWeather(): void
    {
        // Демонстрационные данные
        $cities = ['Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург'];
        $randomCity = $cities[array_rand($cities)];
        
        $message = "🌤️ *Погода в {$randomCity}*\n\n";
        $message .= "Температура: +" . rand(10, 25) . "°C\n";
        $message .= "Состояние: Солнечно ☀️\n";
        $message .= "Влажность: " . rand(50, 80) . "%\n";
        $message .= "Ветер: " . rand(2, 10) . " м/с\n\n";
        $message .= "Хорошего дня!";

        $callbackQueryId = $this->callbackQuery?->id;
    
    if ($callbackQueryId) {
        \DefStudio\Telegraph\Facades\Telegraph::answerCallbackQuery($callbackQueryId)
            ->text('Обрабатываю запрос...')
            ->send();
    }

        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button('🌤️ Получить погоду')->action('get_weather');
                $keyboard->button('🏠 На главную')->action('start');
                return $keyboard;
            })
            ->send();
    }
    
    /**
     * Обработка текстовых сообщений
     */
    protected function handleChatMessage(Stringable $text): void
    {
        // Если это команда
        if ($text->startsWith('/')) {
            parent::handleChatMessage($text);
            return;
        }
        
        // Обработка обычного текста
        $message = "🌤️ *Погодный бот*\n\n";
        $message .= "Вы написали: *{$text}*\n\n";
        $message .= "Используйте кнопки ниже для получения погоды!";
        
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button('🌤️ Получить погоду')->action('get_weather');
                $keyboard->button('❓ Помощь')->action('help');
                return $keyboard;
            })
            ->send();
    }
    
    /**
     * Обработка callback query (нажатие на кнопки)
     * ВНИМАНИЕ: метод должен быть без параметров, как в родительском классе!
     */
    protected function handleCallbackQuery(): void
    {
        // Получаем данные callback из свойства родительского класса
        $callbackData = $this->callbackQuery?->data();
        
        if (!$callbackData) {
            return;
        }

        $action = $this->extractActionFromJson($callbackData);
        
        switch ($action) {
            case 'get_weather_city':
                $this->handleGetWeather();
                //$this->handleGetWeatherWay();
                break;
                
            case 'help':
                $this->help();
                break;
                
            case 'start':
                $this->start();
                break;
                
            default:
                $this->chat->html("Действие: {$callbackData}")->send();
        }
    }

    /**
     * Меню выбора способа получения по геолокации или введя город
     */
     protected function handleGetWeatherWay(): void
    {

        $message = "⬇️ *Выбери способ получения:*";
            
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                    $keyboard->button('🏙️ По названию города')->action('get_weather');
                    $keyboard->button('📍 По локации')->action('help');
                    $keyboard->button('🏠 На главную')->action('start');
                    return $keyboard;
            })
            ->send();
    }
    /**
     * Извлекаем action из JSON строки {"action":"value"}
     */
    private function extractActionFromJson(string $jsonString): string
    {
        $action = str_replace('{"action":"', '', $jsonString);
        $action = str_replace('"}', '', $action);
        $action = trim($action, '"\'');
        return $action;
    }
}