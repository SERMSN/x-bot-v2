<?php

namespace App\Filament\Resources\SubscriptionTransactions;

use App\Filament\Resources\SubscriptionTransactions\Pages\ListSubscriptionTransactions;
use App\Filament\Resources\SubscriptionTransactions\Pages\ViewSubscriptionTransaction;
use App\Filament\Resources\SubscriptionTransactions\Schemas\SubscriptionTransactionInfolist;
use App\Filament\Resources\SubscriptionTransactions\Tables\SubscriptionTransactionsTable;
use App\Models\SubscriptionTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionTransactionResource extends Resource
{
    protected static ?string $model = SubscriptionTransaction::class;

    protected static string|UnitEnum|null $navigationGroup = "Подписки";

    protected static ?string $navigationLabel = "История подписок";

    protected static ?int $navigationSort = 320;

    protected static ?string $modelLabel = "Транзакция подписки";

    protected static ?string $pluralModelLabel = "История подписок";

    public static function infolist(Schema $schema): Schema
    {
        return SubscriptionTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            "index" => ListSubscriptionTransactions::route("/"),
            "view" => ViewSubscriptionTransaction::route("/{record}"),
        ];
    }
}
