<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use UnitEnum;

final class ContentTraceability extends Page
{
    protected string $view = 'filament.pages.content-traceability';

    protected static ?string $slug = 'content-traceability';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'التتبع والإصدارات',
            'fr' => 'Traçabilité et versions',
            default => 'Traceability & Versions',
        };
    }

    public static function getNavigationSort(): int
    {
        return 50;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->text(
            'تتبع سلسلة الطلب والملف والحقوق والمراجعة والنشر والإصدارات القانونية بدون كشف محتوى الأرشيف الخام أو تغيير التاريخ.',
            'Trace request, archive, rights, review, publication and canonical version history without exposing raw archive content or rewriting history.',
            'Tracez la demande, l’archive, les droits, la révision, la publication et les versions canoniques sans exposer le contenu brut ni réécrire l’historique.',
        );
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string, int> */
    public function metrics(): array
    {
        return [
            'requests' => DB::table('preparation_requests')->count(),
            'superseded_requests' => DB::table('preparation_requests')->whereNotNull('superseded_by_request_id')->count(),
            'imports' => DB::table('preparation_imports')->count(),
            'published_imports' => DB::table('preparation_imports')->where('status', 'published')->count(),
            'lessons' => DB::table('lessons')->count(),
            'questions' => DB::table('questions')->count(),
            'quizzes' => DB::table('quizzes')->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function records(): array
    {
        return DB::table('preparation_imports as imports')
            ->leftJoin('preparation_requests as requests', 'requests.id', '=', 'imports.preparation_request_id')
            ->leftJoin('content_publications as publications', 'publications.preparation_import_id', '=', 'imports.id')
            ->orderByDesc('imports.updated_at')
            ->limit(100)
            ->get([
                'imports.id as import_id',
                'imports.preparation_request_id',
                'imports.pack_id',
                'imports.status as import_status',
                'imports.archive_hash',
                'imports.content_hash',
                'imports.dry_run_hash',
                'imports.rights_review_status',
                'imports.rights_basis',
                'imports.rights_evidence_reference',
                'imports.review_decision',
                'imports.reviewed_at',
                'imports.operation_checkpoint',
                'requests.schema_version',
                'requests.settings_hash',
                'requests.status as request_status',
                'requests.superseded_by_request_id',
                'requests.superseded_at',
                'publications.id as publication_id',
                'publications.status as publication_status',
                'publications.checkpoint as publication_checkpoint',
                'publications.attempt_count as publication_attempts',
                'publications.published_at as publication_published_at',
                'imports.updated_at',
            ])
            ->map(fn (object $row): array => [
                'import_id' => $this->shortId((string) $row->import_id),
                'request_id' => is_string($row->preparation_request_id) ? $this->shortId($row->preparation_request_id) : null,
                'pack_id' => is_string($row->pack_id) ? $this->shortId($row->pack_id) : null,
                'import_status' => (string) $row->import_status,
                'request_status' => is_string($row->request_status) ? $row->request_status : null,
                'schema_version' => is_string($row->schema_version) ? $row->schema_version : null,
                'settings_hash' => $this->shortHash($row->settings_hash),
                'archive_hash' => $this->shortHash($row->archive_hash),
                'content_hash' => $this->shortHash($row->content_hash),
                'dry_run_hash' => $this->shortHash($row->dry_run_hash),
                'rights_review_status' => (string) $row->rights_review_status,
                'rights_basis' => is_string($row->rights_basis) ? $row->rights_basis : null,
                'rights_evidence_reference' => is_string($row->rights_evidence_reference) ? $row->rights_evidence_reference : null,
                'review_decision' => is_string($row->review_decision) ? $row->review_decision : null,
                'reviewed_at' => $row->reviewed_at === null ? null : (string) $row->reviewed_at,
                'operation_checkpoint' => is_string($row->operation_checkpoint) ? $row->operation_checkpoint : null,
                'superseded_by_request_id' => is_string($row->superseded_by_request_id) ? $this->shortId($row->superseded_by_request_id) : null,
                'superseded_at' => $row->superseded_at === null ? null : (string) $row->superseded_at,
                'publication_id' => is_string($row->publication_id) ? $this->shortId($row->publication_id) : null,
                'publication_status' => is_string($row->publication_status) ? $row->publication_status : null,
                'publication_checkpoint' => is_string($row->publication_checkpoint) ? $row->publication_checkpoint : null,
                'publication_attempts' => $row->publication_attempts === null ? 0 : (int) $row->publication_attempts,
                'publication_published_at' => $row->publication_published_at === null ? null : (string) $row->publication_published_at,
                'updated_at' => (string) $row->updated_at,
            ])
            ->all();
    }

    /** @return array{lessons: array<int, array<string, mixed>>, questions: array<int, array<string, mixed>>, quizzes: array<int, array<string, mixed>>} */
    public function canonicalVersions(): array
    {
        $lessons = DB::table('lessons')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'lessons.curriculum_node_id')
            ->orderByDesc('lessons.updated_at')
            ->limit(50)
            ->get(['lessons.slug', 'lessons.content_version', 'lessons.title', 'lessons.status', 'lessons.published_at', 'curriculum_nodes.code as node_code'])
            ->map(fn (object $row): array => [
                'name' => $this->localizedJsonText($row->title),
                'reference' => (string) $row->slug,
                'node' => (string) $row->node_code,
                'version' => (int) $row->content_version,
                'status' => (string) $row->status,
                'published_at' => $row->published_at === null ? null : (string) $row->published_at,
            ])
            ->all();

        $questions = DB::table('questions')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'questions.curriculum_node_id')
            ->orderByDesc('questions.updated_at')
            ->limit(50)
            ->get(['questions.id', 'questions.type', 'questions.content_version', 'questions.status', 'questions.maximum_score', 'curriculum_nodes.code as node_code'])
            ->map(fn (object $row): array => [
                'reference' => $this->shortId((string) $row->id),
                'type' => (string) $row->type,
                'node' => (string) $row->node_code,
                'version' => (int) $row->content_version,
                'status' => (string) $row->status,
                'maximum_score' => (float) $row->maximum_score,
            ])
            ->all();

        $quizzes = DB::table('quizzes')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'quizzes.curriculum_node_id')
            ->orderByDesc('quizzes.updated_at')
            ->limit(50)
            ->get(['quizzes.title', 'quizzes.kind', 'quizzes.blueprint_version', 'quizzes.status', 'curriculum_nodes.code as node_code'])
            ->map(fn (object $row): array => [
                'name' => $this->localizedJsonText($row->title),
                'kind' => (string) $row->kind,
                'node' => (string) $row->node_code,
                'version' => (int) $row->blueprint_version,
                'status' => (string) $row->status,
            ])
            ->all();

        return compact('lessons', 'questions', 'quizzes');
    }

    /** @return array<int, array<string, mixed>> */
    public function supersessionHistory(): array
    {
        return DB::table('preparation_requests')
            ->whereNotNull('superseded_by_request_id')
            ->orderByDesc('superseded_at')
            ->limit(50)
            ->get(['id', 'superseded_by_request_id', 'schema_version', 'settings_hash', 'superseded_at'])
            ->map(fn (object $row): array => [
                'from' => $this->shortId((string) $row->id),
                'to' => $this->shortId((string) $row->superseded_by_request_id),
                'schema_version' => (string) $row->schema_version,
                'settings_hash' => $this->shortHash($row->settings_hash),
                'superseded_at' => $row->superseded_at === null ? null : (string) $row->superseded_at,
            ])
            ->all();
    }

    /** @return array{classification: string, code: string, message: string} */
    public function rebuildStatus(): array
    {
        return [
            'classification' => 'deferred_disabled',
            'code' => 'CONTENT_REBUILD_BACKEND_CONTRACT_MISSING',
            'message' => $this->text(
                'لا يوجد حاليًا عقد Backend معتمد لمعاينة/تنفيذ rebuild أو rollback للمنهج. التاريخ الحالي محمي عبر الإصدارات وsupersession، لذلك لا يظهر زر إعادة بناء أو إرجاع وهمي.',
                'No approved Backend contract currently exists for curriculum rebuild preview/execution or rollback. Existing version and supersession history remains protected, so no fake rebuild/rollback action is exposed.',
                'Aucun contrat Backend approuvé n’existe encore pour prévisualiser/exécuter une reconstruction ou un rollback. L’historique de versions et de supersession reste protégé ; aucune fausse action n’est exposée.',
            ),
        ];
    }

    private function shortId(string $value): string
    {
        return $value === '' ? '—' : substr($value, 0, 12);
    }

    private function shortHash(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 12);
    }

    private function localizedJsonText(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return '—';
        }
        $locale = App::getLocale();
        foreach ([$locale, 'en', 'ar', 'fr'] as $candidate) {
            if (is_string($decoded[$candidate] ?? null) && trim($decoded[$candidate]) !== '') {
                return (string) $decoded[$candidate];
            }
        }

        return '—';
    }

    private function text(string $ar, string $en, string $fr): string
    {
        return match (App::getLocale()) {
            'ar' => $ar,
            'fr' => $fr,
            default => $en,
        };
    }
}
