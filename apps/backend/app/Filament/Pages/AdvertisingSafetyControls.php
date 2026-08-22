<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\IntegrationStatusService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class AdvertisingSafetyControls extends Page
{
    protected string $view = 'filament.pages.advertising-safety-controls';

    protected static ?string $slug = 'advertising-safety';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::GovernanceSettings;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'الإعلانات والأمان',
            'fr' => 'Publicité et sécurité',
            default => 'Advertising & Safety',
        };
    }

    public static function getNavigationSort(): int
    {
        return 20;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مفتاح إيقاف إضافي وحالة السياسة مع مناطق منع إعلانات ثابتة لا يمكن للوحة الإدارة إضعافها.',
            'fr' => 'Coupe-circuit opérateur supplémentaire et état de la politique avec zones sans publicité immuables.',
            default => 'An additional operator kill switch and policy status with immutable no-ad zones that Admin cannot weaken.',
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
            'status' => app(IntegrationStatusService::class)->advertising(app()->environment()),
        ];
    }
}
