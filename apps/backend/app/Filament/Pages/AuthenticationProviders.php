<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\IntegrationStatusService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class AuthenticationProviders extends Page
{
    protected string $view = 'filament.pages.authentication-providers';

    protected static ?string $slug = 'authentication-providers';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Integrations;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مزودو تسجيل الدخول',
            'fr' => 'Fournisseurs d’authentification',
            default => 'Authentication Providers',
        };
    }

    public static function getNavigationSort(): int
    {
        return 10;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'حالة البريد وGoogle وApple بدون كشف أي أسرار أو مفاتيح خاصة.',
            'fr' => 'État de l’e-mail, Google et Apple sans exposer de secret ni de clé privée.',
            default => 'Email, Google and Apple status without exposing secrets or private keys.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'environment' => app()->environment(),
            'providers' => app(IntegrationStatusService::class)->authentication(app()->environment()),
        ];
    }
}
