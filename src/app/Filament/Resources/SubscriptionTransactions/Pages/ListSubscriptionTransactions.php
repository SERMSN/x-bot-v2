<?php

namespace App\Filament\Resources\SubscriptionTransactions\Pages;

use App\Filament\Resources\SubscriptionTransactions\SubscriptionTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionTransactions extends ListRecords
{
    protected static string $resource = SubscriptionTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
