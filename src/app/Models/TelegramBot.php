<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphBot;

class TelegramBot extends TelegraphBot
{
    protected $table = 'telegraph_bots';
    
    protected $fillable = [
        'name',
        'token',
        'handler_class',
      //  'webhook_url',
      //  'settings',
    ];
    
    protected $casts = [
        'settings' => 'array',
    ];
    
    /**
     * Получить класс хендлера для этого бота
     */
  /*  public function getHandlerClass(): ?string
    {
        // Если класс явно указан в БД - используем его
        if ($this->handler_class && class_exists($this->handler_class)) {
            return $this->handler_class;
        }
    }*/
    
}