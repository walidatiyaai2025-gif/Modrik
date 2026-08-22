<?php

namespace App\Filament\Support;

use BackedEnum;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;

enum AdminNavigationGroup implements HasLabel, HasIcon
{
    case Overview;
    case Academic;
    case Content;
    case Assessment;
    case UsersAccess;
    case Engagement;
    case Integrations;
    case Operations;
    case GovernanceSettings;

    public function getLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => match ($this) {
                self::Overview => 'نظرة عامة',
                self::Academic => 'الهيكل الأكاديمي',
                self::Content => 'المحتوى',
                self::Assessment => 'التقييم والاختبارات',
                self::UsersAccess => 'المستخدمون والصلاحيات',
                self::Engagement => 'التفاعل والإشعارات',
                self::Integrations => 'التكاملات',
                self::Operations => 'التشغيل والمراقبة',
                self::GovernanceSettings => 'الحوكمة والإعدادات',
            },
            'fr' => match ($this) {
                self::Overview => 'Vue d’ensemble',
                self::Academic => 'Structure académique',
                self::Content => 'Contenu',
                self::Assessment => 'Évaluations',
                self::UsersAccess => 'Utilisateurs et accès',
                self::Engagement => 'Engagement et notifications',
                self::Integrations => 'Intégrations',
                self::Operations => 'Opérations',
                self::GovernanceSettings => 'Gouvernance et paramètres',
            },
            default => match ($this) {
                self::Overview => 'Overview',
                self::Academic => 'Academic',
                self::Content => 'Content',
                self::Assessment => 'Assessment',
                self::UsersAccess => 'Users & Access',
                self::Engagement => 'Engagement',
                self::Integrations => 'Integrations',
                self::Operations => 'Operations',
                self::GovernanceSettings => 'Governance & Settings',
            },
        };
    }

    public function getIcon(): string | BackedEnum | Htmlable | null
    {
        return match ($this) {
            self::Overview => Heroicon::OutlinedHome,
            self::Academic => Heroicon::OutlinedAcademicCap,
            self::Content => Heroicon::OutlinedDocumentText,
            self::Assessment => Heroicon::OutlinedClipboardDocumentCheck,
            self::UsersAccess => Heroicon::OutlinedUsers,
            self::Engagement => Heroicon::OutlinedBell,
            self::Integrations => Heroicon::OutlinedPuzzlePiece,
            self::Operations => Heroicon::OutlinedServerStack,
            self::GovernanceSettings => Heroicon::OutlinedCog6Tooth,
        };
    }
}
