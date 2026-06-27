<?php

namespace App\Filament\Resources\SubscriptionPlans;

use App\Filament\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Filament\Resources\SubscriptionPlans\Pages\ViewSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Schemas\SubscriptionPlanForm;
use App\Filament\Resources\SubscriptionPlans\Schemas\SubscriptionPlanInfolist;
use App\Filament\Resources\SubscriptionPlans\Tables\SubscriptionPlansTable;
use App\Models\SubscriptionPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static string|UnitEnum|null $navigationGroup = "Подписки";

    protected static ?string $navigationLabel = "Планы подписки";

    protected static ?int $navigationSort = 310;

    protected static ?string $modelLabel = "План подписки";

    protected static ?string $pluralModelLabel = "Планы подписки";

    public static function form(Schema $schema): Schema
    {
        return SubscriptionPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SubscriptionPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            "index" => ListSubscriptionPlans::route("/"),
            "create" => CreateSubscriptionPlan::route("/create"),
            "view" => ViewSubscriptionPlan::route("/{record}"),
            "edit" => EditSubscriptionPlan::route("/{record}/edit"),
        ];
    }
}
