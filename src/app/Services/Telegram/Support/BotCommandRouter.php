<?php

namespace App\Services\Telegram\Support;

class BotCommandRouter
{
    /**
     * @param array<string, callable> $handlers
     */
    public function dispatch(string $action, array $handlers, ?callable $fallback = null): void
    {
        if (isset($handlers[$action])) {
            $handlers[$action]();
            return;
        }

        if ($fallback !== null) {
            $fallback($action);
        }
    }
}
