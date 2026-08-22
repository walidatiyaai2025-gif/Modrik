<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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

    /** @return array<string, int> */
    public function metrics(): array
    {
        return [
            'uploads' => DB::table('preparation_imports')->count(),
            'processing' => DB::table('preparation_imports')->whereIn('operation_state', ['processing', 'retrying', 'running'])->count(),
            'failed' => DB::table('preparation_imports')->where('operation_state', 'failed')->count(),
            'review_backlog' => DB::table('preparation_imports')->where('status', 'validated')->whereNull('review_decision')->count(),
            'rights_backlog' => DB::table('preparation_imports')->whereNotIn('status', ['published', 'superseded'])->where('rights_review_status', '!=', 'approved')->count(),
            'published' => DB::table('preparation_imports')->where('status', 'published')->count(),
            'lessons' => DB::table('lessons')->count(),
            'questions' => DB::table('questions')->count(),
        ];
    }

    /** @return array{tracks: int, tracks_with_nodes: int, curriculum_nodes: int, published_nodes: int, nodes_with_lessons: int, nodes_with_questions: int, nodes_with_quizzes: int} */
    public function coverage(): array
    {
        return [
            'tracks' => DB::table('academic_tracks')->count(),
            'tracks_with_nodes' => DB::table('curriculum_nodes')->distinct()->count('academic_track_id'),
            'curriculum_nodes' => DB::table('curriculum_nodes')->count(),
            'published_nodes' => DB::table('curriculum_nodes')->where('status', 'published')->count(),
            'nodes_with_lessons' => DB::table('lessons')->distinct()->count('curriculum_node_id'),
            'nodes_with_questions' => DB::table('questions')->distinct()->count('curriculum_node_id'),
            'nodes_with_quizzes' => DB::table('quizzes')->distinct()->count('curriculum_node_id'),
        ];
    }

    /** @return array{mode: string, provider: string, paid_ai_runtime_enabled: bool, paid_ai_required: bool, zero_paid_fallback: bool, validation_authority: string} */
    public function processingPolicy(): array
    {
        return [
            'mode' => 'deterministic_preparation_bundle_returned_zip',
            'provider' => 'not_backend_selected',
            'paid_ai_runtime_enabled' => (bool) config('modrik.paid_ai.enabled'),
            'paid_ai_required' => false,
            'zero_paid_fallback' => true,
            'validation_authority' => 'backend_content_pack_validator',
        ];
    }

    /** @return array<int, array{label: string, description: string, url: string, state: string}> */
    public function lifecycle(): array
    {
        $isAr = App::getLocale() === 'ar';
        $isFr = App::getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);

        return [
            ['label' => $label('الهيكل الأكاديمي', 'Academic Track', 'Parcours académique'), 'description' => $label('تحقق من المسار المعتمد قبل إنشاء أي طلب إعداد.', 'Confirm the approved academic track before preparing content.', 'Confirmez le parcours approuvé avant toute préparation.'), 'url' => AcademicCatalogue::getUrl(), 'state' => 'required'],
            ['label' => $label('الإعداد', 'Preparation', 'Préparation'), 'description' => $label('أنشئ أو افتح طلب إعداد واحصل على الـPrompt والـBundle.', 'Create or reopen a preparation request and generate its prompt/bundle.', 'Créez ou rouvrez une demande et générez son prompt/bundle.'), 'url' => ContentPreparationRequests::getUrl(), 'state' => 'active'],
            ['label' => $label('الاستيعاب والمعالجة', 'Ingestion & Processing', 'Ingestion et traitement'), 'description' => $label('راقب ملفات ZIP المعادة، حالات المعالجة، نقاط الفشل وأعد محاولة الـdry-run بأمان.', 'Monitor returned ZIP imports, processing checkpoints and failures, and safely retry dry-run processing.', 'Surveillez les ZIP retournés, les étapes de traitement et les échecs, puis relancez le dry-run en toute sécurité.'), 'url' => ContentIngestionOperations::getUrl(), 'state' => 'active'],
            ['label' => $label('الحقوق', 'Rights', 'Droits'), 'description' => $label('راجع مصدر المحتوى وحقوق استخدامه قبل النشر الرسمي.', 'Review provenance and usage rights before official publication.', 'Vérifiez la provenance et les droits avant publication officielle.'), 'url' => ContentRightsReview::getUrl(), 'state' => 'gate'],
            ['label' => $label('المراجعة والنشر', 'Review & Publish', 'Révision et publication'), 'description' => $label('افحص نتائج التحقق والـdry-run واتخذ قرار المراجعة أو النشر المصرح به.', 'Inspect validation/dry-run evidence and take the authorized review or publication action.', 'Inspectez validation/dry-run puis prenez l’action autorisée de révision ou publication.'), 'url' => ContentReviewQueue::getUrl(), 'state' => 'gate'],
        ];
    }

    /** @return array<int, array{label: string, description: string, url: string, state: string}> */
    public function supportingSurfaces(): array
    {
        $isAr = App::getLocale() === 'ar';
        $isFr = App::getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);

        return [
            [
                'label' => $label('استثناءات المراجعة', 'Review Exceptions', 'Exceptions de révision'),
                'description' => $label('فرز الحواجز والفشل والحقوق وطلبات الإصلاح من الإشارات المحفوظة.', 'Triage blockers, failures, rights gates and fix requests from persisted signals.', 'Triez les blocages, échecs, contrôles de droits et demandes de correction depuis les signaux persistés.'),
                'url' => ContentReviewExceptions::getUrl(),
                'state' => 'active',
            ],
            [
                'label' => $label('التتبع والإصدارات', 'Traceability & Versions', 'Traçabilité et versions'),
                'description' => $label('اعرض سلسلة الطلب والملف والحقوق والمراجعة والنشر والإصدارات وsupersession.', 'Inspect request/archive/rights/review/publication traceability, versions and supersession.', 'Inspectez la traçabilité demande/archive/droits/révision/publication, les versions et la supersession.'),
                'url' => ContentTraceability::getUrl(),
                'state' => 'read_only',
            ],
        ];
    }

    /** @return array<int, array{label: string, classification: string, reason: string}> */
    public function deferredCapabilities(): array
    {
        $isAr = App::getLocale() === 'ar';
        $isFr = App::getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);

        return [
            [
                'label' => $label('إعادة بناء المنهج', 'Curriculum rebuild', 'Reconstruction du programme'),
                'classification' => 'deferred_disabled',
                'reason' => $label('لا يوجد Backend contract معتمد للمعاينة/التنفيذ/rollback حتى الآن؛ الإصدارات وsupersession الحالية تبقى محمية.', 'No approved Backend preview/execution/rollback contract exists yet; current versions and supersession history remain protected.', 'Aucun contrat Backend approuvé de prévisualisation/exécution/rollback n’existe encore ; les versions et la supersession restent protégées.'),
            ],
            [
                'label' => $label('درجة ثقة آلية', 'Automated confidence scoring', 'Score de confiance automatisé'),
                'classification' => 'deferred_disabled',
                'reason' => $label('لا يوجد عقد ثقة آلي؛ يتم استخدام نتائج التحقق والـdry-run والمراجعة الحقيقية فقط.', 'No automated confidence contract exists; only real validation, dry-run and review signals are used.', 'Aucun contrat de confiance automatisé n’existe ; seuls les signaux réels de validation, dry-run et révision sont utilisés.'),
            ],
        ];
    }
}
