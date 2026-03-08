<?php

namespace App\Providers\Filament;

use App\Filament\Main\Pages\Dashboard;
use App\Filament\Main\Widgets\BotsShowcaseWidget;
use App\Models\AppSetting;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
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
        $navigationLayout = strtolower(
            (string) AppSetting::getValue(
                AppSetting::KEY_MAIN_PANEL_NAVIGATION_LAYOUT,
                AppSetting::NAVIGATION_TOP,
            ),
        );
        $isTopNavigation = $navigationLayout !== AppSetting::NAVIGATION_LEFT;

        $panel = $panel
            ->id("main")
            ->path("/")
            ->homeUrl(env("APP_URL"))
            ->brandName("X-Bot")
            ->favicon(asset("favicon.png"))
            ->colors([
                "primary" => $this->resolvePrimaryColor(),
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
            ->widgets([BotsShowcaseWidget::class])
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

        if ($isTopNavigation) {
            $panel = $panel->topNavigation();
        }

        return $panel;
    }

    private function resolvePrimaryColor(): string
    {
        $defaultColor = "#6366F1";
        $value = (string) AppSetting::getValue(
            AppSetting::KEY_MAIN_PANEL_PRIMARY_COLOR,
            $defaultColor,
        );

        if (preg_match("/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/", $value) !== 1) {
            return $defaultColor;
        }

        return strtoupper($value);
    }
}
