<?php

namespace App\Providers\Filament;

use App\Filament\Main\Widgets\BotsCountWidget;
use App\Filament\Main\Widgets\ChatsCountWidget;
use App\Filament\Main\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MainPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id("main")
            ->path("/")
            //->homeUrl('/')
            ->homeUrl(env("APP_URL"))
            ->topNavigation()
            ->brandName("X-Bot")
            ->favicon(asset("favicon.png"))
            ->colors([
                "primary" => Color::Indigo,
            ])
            ->discoverResources(
                in: app_path("Filament/Main/Resources"),
                for: "App\Filament\Main\Resources",
            )
            ->discoverPages(
                in: app_path("Filament/Main/Pages"),
                for: "App\Filament\Main\Pages",
            )
            ->pages([Dashboard::class])
            ->discoverWidgets(
                in: app_path("Filament/Main/Widgets"),
                for: "App\Filament\Main\Widgets",
            )
            ->widgets([BotsCountWidget::class, ChatsCountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ]);
        /*  ->authMiddleware([
                Authenticate::class,
            ]);*/
    }
}
