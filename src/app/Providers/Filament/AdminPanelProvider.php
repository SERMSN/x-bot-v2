<?php

namespace App\Providers\Filament;

use App\Models\AppSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $navigationLayout = strtolower(
            (string) AppSetting::getValue(
                AppSetting::KEY_ADMIN_PANEL_NAVIGATION_LAYOUT,
                AppSetting::NAVIGATION_TOP,
            ),
        );
        $isTopNavigation = $navigationLayout !== AppSetting::NAVIGATION_LEFT;

        $panel = $panel
            ->default()
            ->id("admin")
            ->path("admin")
            ->homeUrl(env("APP_URL") . "/admin")
            ->brandName("Admin")
            ->favicon(asset("favicon.png"))
            ->login()
            ->colors([
                "primary" => $this->resolvePrimaryColor(),
            ])
            ->discoverResources(
                in: app_path("Filament/Resources"),
                for: "App\Filament\Resources",
            )
            ->discoverPages(
                in: app_path("Filament/Pages"),
                for: "App\Filament\Pages",
            )
            ->pages([Dashboard::class])
            ->discoverWidgets(
                in: app_path("Filament/Widgets"),
                for: "App\Filament\Widgets",
            )
            ->widgets([AccountWidget::class, FilamentInfoWidget::class])
            ->navigationGroups([
                NavigationGroup::make("Настройки")->icon(
                    "heroicon-o-cog-8-tooth",
                ),
            ])
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
            ])
            ->authMiddleware([Authenticate::class]);

        if ($isTopNavigation) {
            $panel = $panel->topNavigation();
        }

        return $panel;
    }

    private function resolvePrimaryColor(): string
    {
        $defaultColor = "#3B82F6";
        $value = (string) AppSetting::getValue(
            AppSetting::KEY_ADMIN_PANEL_PRIMARY_COLOR,
            $defaultColor,
        );

        if (preg_match("/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/", $value) !== 1) {
            return $defaultColor;
        }

        return strtoupper($value);
    }
}
