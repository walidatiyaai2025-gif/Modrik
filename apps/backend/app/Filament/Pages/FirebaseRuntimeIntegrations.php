<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\IntegrationStatusService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use UnitEnum;

final class FirebaseRuntimeIntegrations extends Page
{
    protected string $view = 'filament.pages.firebase-runtime-integrations';

    protected static ?string $slug = 'firebase-runtime';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Integrations;

    public string $targetType = 'test_user';

    public string $targetReference = '';

    public string $lastTestCode = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'Firebase والتشغيل',
            'fr' => 'Firebase et exécution',
            default => 'Firebase & Runtime',
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
            'ar' => 'Firebase خدمة مساعدة فقط؛ قاعدة البيانات والمصادقة الأساسية تظل داخل MODRIK.',
            'fr' => 'Firebase reste auxiliaire ; la base produit et l’authentification restent sous l’autorité MODRIK.',
            default => 'Firebase remains auxiliary; product data and authentication remain MODRIK-owned.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function testPush(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && (string) $user->role === 'admin', 403);

        try {
            $result = app(IntegrationStatusService::class)->firebaseTestPushBoundary(
                app()->environment(),
                $this->targetType,
                trim($this->targetReference),
            );
            $this->lastTestCode = $result['code'];
            $this->resetErrorBag('targetReference');
        } catch (InvalidArgumentException $exception) {
            $this->lastTestCode = '';
            $this->addError('targetReference', $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'environment' => app()->environment(),
            'status' => app(IntegrationStatusService::class)->firebase(app()->environment()),
        ];
    }
}
