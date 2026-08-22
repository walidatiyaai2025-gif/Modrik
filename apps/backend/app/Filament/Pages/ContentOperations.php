<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class ContentOperations extends Page
{
    protected string $view = 'filament.pages.content-operations';

    protected static ?string $slug = 'content-operations';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'عمليات المحتوى',
            'fr' => 'Opérations de contenu',
            default => 'Content Operations',
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
            'ar' => 'مسار تشغيلي موحّد لإعداد المحتوى والتحقق من الحقوق والمراجعة والنشر الرسمي مع إبقاء الصلاحيات والقرارات النهائية في الخدمات الخلفية.',
            'fr' => 'Un parcours opérationnel unifié pour préparer, vérifier les droits, réviser et publier le contenu officiel tout en conservant l’autorité dans le Backend.',
            default => 'A unified operator journey for preparation, rights verification, review, and official publication while Backend authority remains unchanged.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<int, array{label: string, description: string, url: string, state: string}> */
    public function lifecycle(): array
    {
        $isAr = App::getLocale() === 'ar';
        $isFr = App::getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);

        return [
            [
                'label' => $label('الهيكل الأكاديمي', 'Academic Track', 'Parcours académique'),
                'description' => $label('تحقق من المسار المعتمد قبل إنشاء أي طلب إعداد.', 'Confirm the approved academic track before preparing content.', 'Confirmez le parcours approuvé avant toute préparation.'),
                'url' => AcademicCatalogue::getUrl(),
                'state' => 'required',
            ],
            [
                'label' => $label('الإعداد', 'Preparation', 'Préparation'),
                'description' => $label('أنشئ أو افتح طلب إعداد واحصل على الـPrompt والـBundle.', 'Create or reopen a preparation request and generate its prompt/bundle.', 'Créez ou rouvrez une demande et générez son prompt/bundle.'),
                'url' => ContentPreparationRequests::getUrl(),
                'state' => 'active',
            ],
            [
                'label' => $label('الاستيعاب والمعالجة', 'Ingestion & Processing', 'Ingestion et traitement'),
                'description' => $label('راقب ملفات ZIP المعادة، حالات المعالجة، نقاط الفشل وأعد محاولة الـdry-run بأمان.', 'Monitor returned ZIP imports, processing checkpoints and failures, and safely retry dry-run processing.', 'Surveillez les ZIP retournés, les étapes de traitement et les échecs, puis relancez le dry-run en toute sécurité.'),
                'url' => ContentIngestionOperations::getUrl(),
                'state' => 'active',
            ],
            [
                'label' => $label('الحقوق', 'Rights', 'Droits'),
                'description' => $label('راجع مصدر المحتوى وحقوق استخدامه قبل النشر الرسمي.', 'Review provenance and usage rights before official publication.', 'Vérifiez la provenance et les droits avant publication officielle.'),
                'url' => ContentRightsReview::getUrl(),
                'state' => 'gate',
            ],
            [
                'label' => $label('المراجعة والنشر', 'Review & Publish', 'Révision et publication'),
                'description' => $label('افحص نتائج التحقق والـdry-run واتخذ قرار المراجعة أو النشر المصرح به.', 'Inspect validation/dry-run evidence and take the authorized review or publication action.', 'Inspectez validation/dry-run puis prenez l’action autorisée de révision ou publication.'),
                'url' => ContentReviewQueue::getUrl(),
                'state' => 'gate',
            ],
        ];
    }
}
