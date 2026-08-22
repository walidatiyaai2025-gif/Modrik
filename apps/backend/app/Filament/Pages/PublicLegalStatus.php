<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class PublicLegalStatus extends Page
{
    protected string $view = 'filament.pages.public-legal-status';

    protected static ?string $slug = 'public-legal-status';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::GovernanceSettings;

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
            'ar' => 'الحالة العامة والقانونية',
            'fr' => 'Public, juridique et aide',
            default => 'Public, Legal & Help',
        };
    }

    public static function getNavigationSort(): int
    {
        return 70;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'رؤية تشغيلية للصفحات العامة ونماذج السياسات الحالية. هذه الشاشة لا تنشئ حقائق قانونية ولا تمنح صلاحية نشر قانوني غير موجودة في الـBackend.',
            'fr' => 'Vue opérationnelle des pages publiques et modèles de politiques actuels. Cette page n’invente aucun fait juridique et n’ajoute aucune autorité de publication absente du Backend.',
            default => 'Operational visibility for current public pages and policy templates. This page does not invent legal facts or add publication authority that the Backend does not have.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return list<array{key: string, slug: string, kind: string, template: bool, indexable: bool}> */
    public function publicPages(): array
    {
        return [
            ['key' => 'landing', 'slug' => '/landing', 'kind' => 'public_information', 'template' => false, 'indexable' => true],
            ['key' => 'help', 'slug' => '/help', 'kind' => 'help', 'template' => false, 'indexable' => true],
            ['key' => 'adminGuide', 'slug' => '/admin-guide', 'kind' => 'help', 'template' => false, 'indexable' => true],
            ['key' => 'about', 'slug' => '/about', 'kind' => 'public_information', 'template' => false, 'indexable' => true],
            ['key' => 'goal', 'slug' => '/goal', 'kind' => 'public_information', 'template' => false, 'indexable' => true],
            ['key' => 'vision', 'slug' => '/vision', 'kind' => 'public_information', 'template' => false, 'indexable' => true],
            ['key' => 'mission', 'slug' => '/mission', 'kind' => 'public_information', 'template' => false, 'indexable' => true],
            ['key' => 'disclaimer', 'slug' => '/disclaimer', 'kind' => 'legal_template', 'template' => true, 'indexable' => false],
            ['key' => 'privacy', 'slug' => '/privacy', 'kind' => 'legal_template', 'template' => true, 'indexable' => false],
            ['key' => 'terms', 'slug' => '/terms', 'kind' => 'legal_template', 'template' => true, 'indexable' => false],
            ['key' => 'safety', 'slug' => '/safety', 'kind' => 'legal_template', 'template' => true, 'indexable' => false],
            ['key' => 'cookies', 'slug' => '/cookies', 'kind' => 'legal_template', 'template' => true, 'indexable' => false],
            ['key' => 'contentPolicy', 'slug' => '/content-policy', 'kind' => 'legal_template', 'template' => true, 'indexable' => false],
            ['key' => 'accountDeletion', 'slug' => '/account-deletion', 'kind' => 'account_help', 'template' => true, 'indexable' => false],
            ['key' => 'support', 'slug' => '/support', 'kind' => 'help', 'template' => true, 'indexable' => false],
            ['key' => 'contact', 'slug' => '/contact', 'kind' => 'help', 'template' => true, 'indexable' => false],
        ];
    }

    /** @return list<string> */
    public function legalBlockers(): array
    {
        return [
            'LEGAL_ENTITY_CONTROLLER',
            'PUBLIC_CONTACT',
            'JURISDICTION',
            'PROCESSING_BASES',
            'VENDOR_INVENTORY',
            'INTERNATIONAL_TRANSFERS',
            'RETENTION_SCHEDULE',
            'AGE_GUARDIAN_POLICY',
            'SAFETY_ESCALATION_CONTACT',
            'COPYRIGHT_TAKEDOWN_CONTACT',
            'SUPPORT_CHANNEL_HOURS',
            'POLICY_EFFECTIVE_DATE',
            'POLICY_VERSION',
        ];
    }

    /** @return array{locales: list<string>, rtl_locale: string, source: string, mutation_contract: string, legal_approval: string} */
    public function contractStatus(): array
    {
        return [
            'locales' => ['ar', 'en', 'fr'],
            'rtl_locale' => 'ar',
            'source' => 'apps/web/src/public-site/content.ts',
            'mutation_contract' => 'not_implemented',
            'legal_approval' => 'blocked_pending_owner_legal_inputs',
        ];
    }
}
