<?php

namespace App\Filament\Main\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = "Инфопанель";

    public function getHeading(): string|null
    {
        return null;
    }
}
