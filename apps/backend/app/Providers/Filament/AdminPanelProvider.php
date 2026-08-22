<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Dashboard;
use App\Http\Middleware\RequireAdminPanelRole;
use App\Http\Middleware\SetAdminLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(config('brand.name'))
            ->brandLogo(asset('brand/logo-horizontal.svg'))
            ->brandLogoHeight('2.15rem')
            ->favicon(asset('favicon.png'))
            ->login()
            ->font((string) config('brand.typography.latin', 'Poppins'))
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('18rem')
            ->colors([
                'primary' => Color::hex((string) config('brand.colors.primary')),
                'info' => Color::hex((string) config('brand.colors.info')),
                'success' => Color::hex((string) config('brand.colors.success')),
                'warning' => Color::hex((string) config('brand.colors.warning')),
                'danger' => Color::hex((string) config('brand.colors.danger')),
                'gray' => Color::Slate,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): string => $this->releaseBadge(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => $this->releaseBadge(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.admin.topbar-context')->render(),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                SetAdminLocale::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireAdminPanelRole::class,
            ]);
    }

    private function releaseBadge(): string
    {
        return view('filament.release-badge', [
            'release' => $this->releaseVersion(),
        ])->render();
    }

    private function releaseVersion(): string
    {
        $path = storage_path('app/modrik-release.txt');
        if (! is_readable($path)) {
            return 'dev';
        }

        $release = trim((string) file_get_contents($path));

        return preg_match('/^[0-9a-f]{40}$/i', $release) === 1 ? strtolower($release) : 'dev';
    }
}
