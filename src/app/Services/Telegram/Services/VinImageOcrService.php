<?php

namespace App\Services\Telegram\Services;

class VinImageOcrService
{
    private const VIN_CHARACTERS = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';

    public function recognize(): string
    {
        $vin = '';
        $lastIndex = strlen(self::VIN_CHARACTERS) - 1;

        for ($index = 0; $index < 17; $index++) {
            $vin .= self::VIN_CHARACTERS[random_int(0, $lastIndex)];
        }

        return $vin;
    }
}
