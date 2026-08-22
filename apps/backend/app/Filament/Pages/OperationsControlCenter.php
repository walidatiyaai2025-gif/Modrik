<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\AdminOperationsOverviewService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class OperationsControlCenter extends Page
{
    protected string $view = 'filament.pages.operations-control-center';

    protected static ?string $slug = 'operations-control-center';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Operations;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && (string) $user->role === 'admin'
            && (string) $user->account_status === 'active'
            && $user->deleted_at === null;
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مركز التحكم التشغيلي',
            'fr' => 'Centre de contrôle opérationnel',
            default => 'Operations Control Center',
        };
    }

    public static function getNavigationSort(): int
    {
        return 5;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'ملخص آمن لحالة الخادم وقاعدة البيانات والطوابير والتخزين والتكاملات مع روابط للأدوات التشغيلية المعتمدة.',
            'fr' => 'Résumé sûr de l’état du Backend, de la base, des files, du stockage et des intégrations avec liens vers les outils opérationnels autorisés.',
            default => 'Safe Backend, database, queue, storage and integration health summary with links to authorized operational tools.',
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
            'overview' => app(AdminOperationsOverviewService::class)->overview(),
            'runtimeInspectorEnabled' => (bool) config('observability.inspector_enabled', false),
        ];
    }
}
