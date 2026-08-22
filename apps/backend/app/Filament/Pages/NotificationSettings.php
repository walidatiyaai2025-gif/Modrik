<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\IntegrationStatusService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class NotificationSettings extends Page
{
    protected string $view = 'filament.pages.notification-settings';

    protected static ?string $slug = 'notification-settings';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Engagement;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'الإشعارات والتفاعل',
            'fr' => 'Notifications et engagement',
            default => 'Notifications & Engagement',
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
            'ar' => 'حالة القنوات وساعات الهدوء، مع فصل ما هو منفذ عما يحتاج تنفيذًا لاحقًا.',
            'fr' => 'État des canaux et heures calmes, en distinguant clairement ce qui est implémenté de ce qui reste à réaliser.',
            default => 'Channel and quiet-hours status with implemented and pending capabilities clearly separated.',
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
            'status' => app(IntegrationStatusService::class)->notifications(app()->environment()),
        ];
    }
}
