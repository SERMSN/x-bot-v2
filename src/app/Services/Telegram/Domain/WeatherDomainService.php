<?php

namespace App\Services\Telegram\Domain;

use App\Services\Telegram\Conversations\WeatherConversationService;
use App\Services\Telegram\Services\WeatherService;
use App\Services\Telegram\Support\BotContext;

class WeatherDomainService
{
    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly WeatherConversationService $conversationService,
    ) {}

    public function validateCity(BotContext $context, string $input): array
    {
        return $this->weatherService->validateCity($context->botId(), $input);
    }

    public function requestWeatherByValidatedCity(BotContext $context, array $validation): array
    {
        return $this->weatherService->getByCityName(
            $context->botId(),
            (string) $validation["normalized"],
            $this->conversationService->getPreferences($context),
        );
    }

    public function requestWeatherByCoordinates(BotContext $context, float $lat, float $lon): array
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException("Invalid coordinates");
        }

        return $this->weatherService->getByCoordinates(
            $context->botId(),
            $lat,
            $lon,
            $this->conversationService->getPreferences($context),
        );
    }

    public function resolveNotificationTimezoneByCoordinates(BotContext $context, float $lat, float $lon): ?string
    {
        return $this->conversationService->normalizeNotificationTimezone(
            $this->weatherService->resolveNotificationTimezoneByCoordinates(
                $context->botId(),
                $lat,
                $lon,
            ),
        );
    }
}
