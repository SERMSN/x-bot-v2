<?php

namespace App\Services\Telegram\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;

class VinBotHandler extends WebhookHandler
{
    /**
     * Команда /start
     */
    public function start(): void
    {
        $message = "🚗 *Добро пожаловать в VIN-декодер!*\n\n";
        $message .= "Я помогу вам получить информацию об автомобиле по VIN-номеру.\n";
        $message .= "Используйте кнопки ниже:";
        
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button('🔍 Проверить VIN')->action('check_vin');
                $keyboard->button('❓ Помощь')->action('help');
                return $keyboard;
            })
            ->send();
    }
    
    /**
     * Команда /help
     */
    public function help(): void
    {
        $message = "📚 *Помощь по VIN-декодеру*\n\n";
        $message .= "Доступные команды:\n";
        $message .= "/start - Начало работы\n";
        $message .= "/help - Эта справка\n";
        $message .= "/vin - Проверить VIN\n\n";
        $message .= "Просто нажмите кнопку 'Проверить VIN' и введите номер!";
        
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button('🔍 Проверить VIN')->action('check_vin');
                $keyboard->button('🏠 На главную')->action('start');
                return $keyboard;
            })
            ->send();
    }
    
    /**
     * Команда /vin
     */
    public function vin(): void
    {
        $this->handleCheckVin();
    }
    
    /**
     * Обработка нажатия на кнопку "Проверить VIN"
     */
    public function handleCheckVin(): void
    {
        $message = "🚗 *Проверка VIN-номера*\n\n";
        $message .= "Введите VIN-номер автомобиля (17 символов):\n";
        $message .= "Например: *JHMCM56557C404453*\n\n";
        $message .= "Или нажмите кнопку для тестового запроса:";
        
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button('📋 Тестовый VIN')->action('test_vin');
                $keyboard->button('🔙 Назад')->action('start');
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
        
        $vin = $text->toString();
        
        // Проверяем, похоже ли на VIN
        if (strlen($vin) >= 10) {
            $this->processVin($vin);
        } else {
            $this->chat->html("🔍 *VIN-декодер*\n\nВведите VIN-номер (минимум 10 символов):")
                ->keyboard(function ($keyboard) {
                    $keyboard->button('📋 Тестовый VIN')->action('test_vin');
                    $keyboard->button('❓ Помощь')->action('help');
                    return $keyboard;
                })
                ->send();
        }
    }
    
    /**
     * Обработка callback query
     */
    protected function handleCallbackQuery(): void
    {
        $callbackData = $this->callbackQuery?->getData();
        
        if (!$callbackData) {
            return;
        }
        
        switch ($callbackData) {
            case 'check_vin':
                $this->handleCheckVin();
                break;
                
            case 'test_vin':
                $this->processVin('JHMCM56557C404453');
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
     * Обработка VIN-номера
     */
    private function processVin(string $vin): void
    {
        // Тестовые данные
        $carData = [
            'vin' => $vin,
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => '2007',
            'engine' => '2.4L L4',
            'transmission' => 'Automatic',
            'color' => 'Silver',
        ];
        
        $message = "🚗 *Результаты проверки VIN*\n\n";
        $message .= "VIN: *{$carData['vin']}*\n";
        $message .= "Марка: *{$carData['make']}*\n";
        $message .= "Модель: *{$carData['model']}*\n";
        $message .= "Год: *{$carData['year']}*\n";
        $message .= "Двигатель: *{$carData['engine']}*\n";
        $message .= "Коробка: *{$carData['transmission']}*\n";
        $message .= "Цвет: *{$carData['color']}*\n\n";
        $message .= "✅ Проверка завершена!";
        
        $this->chat->markdown($message)
            ->keyboard(function ($keyboard) {
                $keyboard->button('🔍 Проверить еще')->action('check_vin');
                $keyboard->button('🏠 На главную')->action('start');
                return $keyboard;
            })
            ->send();
    }
    
    /**
     * Обработка неизвестной команды
     */
    public function handleUnknownCommand(Stringable $text): void
    {
        $this->chat->html("❌ Неизвестная команда в VIN-декодере: *{$text}*\n\nИспользуйте /help для справки.")
            ->keyboard(function ($keyboard) {
                $keyboard->button('🔍 Проверить VIN')->action('check_vin');
                $keyboard->button('❓ Помощь')->action('help');
                return $keyboard;
            })
            ->send();
    }
}