<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use UnitEnum;

final class ContentReviewExceptions extends Page
{
    protected string $view = 'filament.pages.content-review-exceptions';

    protected static ?string $slug = 'content-review-exceptions';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'استثناءات المراجعة',
            'fr' => 'Exceptions de révision',
            default => 'Review Exceptions',
        };
    }

    public static function getNavigationSort(): int
    {
        return 40;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->text(
            'مساحة فرز تشغيلية مبنية فقط على نتائج التحقق والـdry-run والحقوق وقرارات المراجعة والأخطاء المحفوظة في الخلفية.',
            'Operational triage derived only from persisted validation, dry-run, rights, review and processing signals.',
            'Triage opérationnel dérivé uniquement des signaux persistés de validation, dry-run, droits, révision et traitement.',
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
            'total_attention' => $this->attentionQuery()->count(),
            'rights_pending' => DB::table('preparation_imports')
                ->whereNotIn('status', ['published', 'superseded'])
                ->where('rights_review_status', '!=', 'approved')
                ->count(),
            'review_pending' => DB::table('preparation_imports')
                ->where('status', 'validated')
                ->whereNull('review_decision')
                ->count(),
            'review_exception' => DB::table('preparation_imports')
                ->whereIn('review_decision', ['rejected', 'request_fix'])
                ->whereNotIn('status', ['published', 'superseded'])
                ->count(),
            'processing_blocked' => DB::table('preparation_imports')
                ->whereIn('operation_state', ['blocked', 'failed'])
                ->whereNotIn('status', ['published', 'superseded'])
                ->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function exceptions(): array
    {
        return $this->attentionQuery()
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get([
                'id',
                'preparation_request_id',
                'pack_id',
                'status',
                'operation_state',
                'operation_checkpoint',
                'last_error_code',
                'rights_review_status',
                'rights_basis',
                'rights_evidence_reference',
                'review_decision',
                'review_reason',
                'updated_at',
            ])
            ->map(function (object $row): array {
                $classification = $this->classify($row);

                return [
                    'id' => (string) $row->id,
                    'preparation_request_id' => is_string($row->preparation_request_id) ? $row->preparation_request_id : null,
                    'pack_id' => is_string($row->pack_id) ? $row->pack_id : null,
                    'status' => (string) $row->status,
                    'operation_state' => (string) $row->operation_state,
                    'operation_checkpoint' => is_string($row->operation_checkpoint) ? $row->operation_checkpoint : null,
                    'last_error_code' => is_string($row->last_error_code) ? $row->last_error_code : null,
                    'rights_review_status' => (string) $row->rights_review_status,
                    'rights_basis' => is_string($row->rights_basis) ? $row->rights_basis : null,
                    'rights_evidence_reference' => is_string($row->rights_evidence_reference) ? $row->rights_evidence_reference : null,
                    'review_decision' => is_string($row->review_decision) ? $row->review_decision : null,
                    'review_reason' => is_string($row->review_reason) ? $row->review_reason : null,
                    'updated_at' => (string) $row->updated_at,
                    ...$classification,
                ];
            })
            ->all();
    }

    /** @return array<string, mixed> */
    public function dryRunOutcomes(): array
    {
        $outcomes = [
            'imports' => 0,
            'publishable' => 0,
            'blocked' => 0,
            'question_create' => 0,
            'question_reuse' => 0,
            'question_conflict' => 0,
            'blocking_codes' => [],
        ];

        $rows = DB::table('preparation_imports')
            ->whereNotNull('dry_run_summary')
            ->get(['dry_run_summary']);

        foreach ($rows as $row) {
            if (! is_string($row->dry_run_summary) || $row->dry_run_summary === '') {
                continue;
            }
            $summary = json_decode($row->dry_run_summary, true);
            if (! is_array($summary)) {
                continue;
            }

            $outcomes['imports']++;
            if (($summary['publishable'] ?? false) === true) {
                $outcomes['publishable']++;
            } else {
                $outcomes['blocked']++;
            }

            $questionCounts = is_array($summary['counts']['questions'] ?? null) ? $summary['counts']['questions'] : [];
            $outcomes['question_create'] += (int) ($questionCounts['create'] ?? 0);
            $outcomes['question_reuse'] += (int) ($questionCounts['reuse'] ?? 0);
            $outcomes['question_conflict'] += (int) ($questionCounts['conflict'] ?? 0);

            $blockingCodes = is_array($summary['blocking_codes'] ?? null) ? $summary['blocking_codes'] : [];
            foreach ($blockingCodes as $code) {
                if (! is_string($code) || $code === '') {
                    continue;
                }
                $outcomes['blocking_codes'][$code] = (int) ($outcomes['blocking_codes'][$code] ?? 0) + 1;
            }
        }

        arsort($outcomes['blocking_codes']);

        return $outcomes;
    }

    /** @return array{classification: string, code: string, message: string} */
    public function automatedConfidenceStatus(): array
    {
        return [
            'classification' => 'deferred_disabled',
            'code' => 'CONTENT_CONFIDENCE_BACKEND_CONTRACT_MISSING',
            'message' => $this->text(
                'لا يوجد حاليًا عقد خلفي لدرجة ثقة آلية. تعتمد هذه الصفحة على إشارات تحقق ومراجعة حقيقية فقط ولا تخترع درجة ثقة.',
                'No automated confidence-scoring Backend contract exists yet. This page uses real validation/review signals only and does not invent a confidence score.',
                'Aucun contrat Backend de score de confiance automatisé n’existe encore. Cette page utilise uniquement des signaux réels et n’invente aucun score.',
            ),
        ];
    }

    private function attentionQuery(): Builder
    {
        return DB::table('preparation_imports')
            ->whereNotIn('status', ['published', 'superseded'])
            ->where(function (Builder $query): void {
                $query->whereIn('operation_state', ['blocked', 'failed'])
                    ->orWhere('rights_review_status', '!=', 'approved')
                    ->orWhereIn('review_decision', ['rejected', 'request_fix'])
                    ->orWhere(function (Builder $review): void {
                        $review->where('status', 'validated')->whereNull('review_decision');
                    });
            });
    }

    /** @return array{category: string, severity: string, label: string, next_url: string, next_label: string} */
    private function classify(\stdClass $row): array
    {
        $operationState = (string) $row->operation_state;
        $rightsStatus = (string) $row->rights_review_status;
        $decision = is_string($row->review_decision) ? $row->review_decision : null;

        if ($operationState === 'failed') {
            return $this->classification(
                'processing_failure',
                'danger',
                $this->text('فشل معالجة', 'Processing failure', 'Échec de traitement'),
                ContentIngestionOperations::getUrl(),
                $this->text('فتح الاستيعاب', 'Open ingestion', 'Ouvrir l’ingestion'),
            );
        }
        if ($rightsStatus !== 'approved') {
            return $this->classification(
                'rights_review',
                $rightsStatus === 'rejected' ? 'danger' : 'warning',
                $this->text('بوابة الحقوق', 'Rights gate', 'Contrôle des droits'),
                ContentRightsReview::getUrl(),
                $this->text('فتح مراجعة الحقوق', 'Open rights review', 'Ouvrir la révision des droits'),
            );
        }
        if ($decision === 'rejected') {
            return $this->classification(
                'review_rejected',
                'danger',
                $this->text('مرفوض بالمراجعة', 'Review rejected', 'Révision rejetée'),
                ContentReviewQueue::getUrl(),
                $this->text('فتح قائمة المراجعة', 'Open review queue', 'Ouvrir la file de révision'),
            );
        }
        if ($decision === 'request_fix') {
            return $this->classification(
                'review_fix_requested',
                'warning',
                $this->text('مطلوب إصلاح', 'Fix requested', 'Correction demandée'),
                ContentReviewQueue::getUrl(),
                $this->text('فتح قائمة المراجعة', 'Open review queue', 'Ouvrir la file de révision'),
            );
        }
        if ($operationState === 'blocked') {
            return $this->classification(
                'dry_run_blocked',
                'warning',
                $this->text('حاجز معالجة', 'Processing blocker', 'Blocage de traitement'),
                ContentIngestionOperations::getUrl(),
                $this->text('فتح الاستيعاب', 'Open ingestion', 'Ouvrir l’ingestion'),
            );
        }

        return $this->classification(
            'review_pending',
            'info',
            $this->text('بانتظار قرار', 'Decision pending', 'Décision en attente'),
            ContentReviewQueue::getUrl(),
            $this->text('فتح قائمة المراجعة', 'Open review queue', 'Ouvrir la file de révision'),
        );
    }

    /** @return array{category: string, severity: string, label: string, next_url: string, next_label: string} */
    private function classification(string $category, string $severity, string $label, string $url, string $nextLabel): array
    {
        return [
            'category' => $category,
            'severity' => $severity,
            'label' => $label,
            'next_url' => $url,
            'next_label' => $nextLabel,
        ];
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
