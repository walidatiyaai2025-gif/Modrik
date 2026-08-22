<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use UnitEnum;

final class AssessmentOperations extends Page
{
    protected string $view = 'filament.pages.assessment-operations';

    protected static ?string $slug = 'assessment-operations';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Assessment;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'عمليات التقييم',
            'fr' => 'Opérations d’évaluation',
            default => 'Assessment Operations',
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
        return $this->translate(
            'Inspect assessment catalogue, blueprint constraints and immutable attempt-snapshot impact. Lifecycle, availability and blueprint mutation remain read-only until an authoritative Backend mutation contract exists.',
            'راجع كتالوج التقييم وقيود المخطط وتأثير لقطات المحاولات غير القابلة للتغيير. تظل إدارة دورة الحياة والإتاحة وتعديل المخطط للقراءة فقط حتى يتوفر عقد تعديل موثوق في الخادم.',
            'Inspectez le catalogue, les contraintes de blueprint et l’impact des snapshots immuables. Les mutations de cycle de vie, disponibilité et blueprint restent en lecture seule tant qu’aucun contrat Backend autoritatif n’existe.',
        );
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function questionBankUrl(): string
    {
        return AssessmentQuestionBank::getUrl();
    }

    /** @return list<array<string, mixed>> */
    public function assessmentRows(): array
    {
        $query = DB::table('quizzes')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'quizzes.curriculum_node_id')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->orderByDesc('quizzes.updated_at')
            ->limit(250);

        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        $records = $query->get([
            'quizzes.id',
            'quizzes.kind',
            'quizzes.blueprint_version',
            'quizzes.blueprint',
            'quizzes.title',
            'quizzes.status',
            'quizzes.created_at',
            'quizzes.updated_at',
            'curriculum_nodes.id as node_id',
            'curriculum_nodes.code as node_code',
            'curriculum_nodes.type as node_type',
            'curriculum_nodes.title as node_title',
            'academic_tracks.id as track_id',
            'academic_tracks.title as track_title',
            'academic_tracks.year_level',
            'academic_tracks.is_fixture',
        ]);

        if ($records->isEmpty()) {
            return [];
        }

        $quizIds = array_values($records->pluck('id')->map(static fn ($value): string => (string) $value)->all());
        $questionCounts = DB::table('quiz_questions')
            ->whereIn('quiz_id', $quizIds)
            ->selectRaw('quiz_id, COUNT(*) as aggregate_count')
            ->groupBy('quiz_id')
            ->pluck('aggregate_count', 'quiz_id');

        $attemptStats = [];
        foreach (DB::table('attempts')
            ->whereIn('quiz_id', $quizIds)
            ->get(['quiz_id', 'status', 'blueprint_version']) as $attempt) {
            $quizId = (string) $attempt->quiz_id;
            $attemptStats[$quizId] ??= [
                'total' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'blueprint_versions' => [],
            ];
            $attemptStats[$quizId]['total']++;
            if ((string) $attempt->status === 'in_progress') {
                $attemptStats[$quizId]['in_progress']++;
            }
            if ((string) $attempt->status === 'completed') {
                $attemptStats[$quizId]['completed']++;
            }
            $attemptStats[$quizId]['blueprint_versions'][] = (int) $attempt->blueprint_version;
        }

        $rows = [];
        foreach ($records as $record) {
            $quizId = (string) $record->id;
            $stats = $attemptStats[$quizId] ?? [
                'total' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'blueprint_versions' => [],
            ];
            $versions = array_values(array_unique(array_map('intval', $stats['blueprint_versions'])));
            sort($versions);
            $blueprint = $this->blueprintSummary($record->blueprint === null ? null : (string) $record->blueprint);

            $rows[] = [
                'id' => $quizId,
                'title' => $this->localizedJson((string) $record->title),
                'kind' => (string) $record->kind,
                'kind_label' => $this->quizKindLabel((string) $record->kind),
                'status' => (string) $record->status,
                'blueprint_version' => (int) $record->blueprint_version,
                'blueprint' => $blueprint,
                'question_count' => (int) ($questionCounts[$quizId] ?? 0),
                'attempt_count' => (int) $stats['total'],
                'in_progress_attempt_count' => (int) $stats['in_progress'],
                'completed_attempt_count' => (int) $stats['completed'],
                'snapshot_blueprint_versions' => $versions,
                'snapshot_protected' => (int) $stats['total'] > 0,
                'track_title' => $this->localizedJson((string) $record->track_title),
                'year_level' => (string) $record->year_level,
                'node_title' => $this->localizedJson((string) $record->node_title),
                'node_type' => (string) $record->node_type,
                'node_code' => (string) $record->node_code,
                'is_fixture' => (bool) $record->is_fixture,
                'created_at' => (string) $record->created_at,
                'updated_at' => (string) $record->updated_at,
            ];
        }

        return $rows;
    }

    /** @return list<array{capability: string, state: string, classification: string, reason: string}> */
    public function contractBoundaries(): array
    {
        return [
            [
                'capability' => $this->translate('Question Bank visibility', 'عرض بنك الأسئلة', 'Visibilité de la banque de questions'),
                'state' => 'present',
                'classification' => 'read_only_operational',
                'reason' => $this->translate('Detailed prompts, approved answers, explanations and quiz membership remain available in Question Bank.', 'تظل نصوص الأسئلة والإجابات المعتمدة والشرح وعضوية الاختبارات متاحة في بنك الأسئلة.', 'Les énoncés, réponses approuvées, explications et appartenances restent disponibles dans la banque de questions.'),
            ],
            [
                'capability' => $this->translate('Assessment lifecycle / availability', 'دورة حياة التقييم / الإتاحة', 'Cycle de vie / disponibilité'),
                'state' => 'backend_contract_missing',
                'classification' => 'read_only_operational',
                'reason' => $this->translate('No authoritative Admin mutation service currently validates publish/disable/archive transitions, stale versions and immutable audit evidence.', 'لا توجد حاليًا خدمة تعديل إدارية موثوقة تتحقق من انتقالات النشر/التعطيل/الأرشفة والإصدارات المتعارضة وسجل التدقيق غير القابل للتغيير.', 'Aucun service de mutation Admin autoritatif ne valide actuellement publication/désactivation/archivage, versions obsolètes et audit immuable.'),
            ],
            [
                'capability' => $this->translate('Blueprint configuration', 'إعداد المخطط', 'Configuration du blueprint'),
                'state' => 'backend_contract_missing',
                'classification' => 'read_only_operational',
                'reason' => $this->translate('Blueprint constraints are visible, but no operator-authorized versioned mutation contract exists. Existing attempt scope snapshots must never be rewritten.', 'قيود المخطط ظاهرة، لكن لا يوجد عقد تعديل مُصدّر ومصرح به للمشغل. يجب ألا يعاد كتابة لقطات نطاق المحاولات الحالية.', 'Les contraintes sont visibles, mais aucun contrat de mutation versionné autorisé n’existe. Les snapshots de tentative existants ne doivent jamais être réécrits.'),
            ],
            [
                'capability' => $this->translate('Authoritative randomization and scoring', 'العشوائية والتصحيح الموثوقان', 'Randomisation et notation autoritatives'),
                'state' => 'present',
                'classification' => 'internal_non_editable',
                'reason' => $this->translate('Seed, selected set/order, resume order and grading snapshots remain Backend-owned and are intentionally absent from Admin controls.', 'تظل البذرة ومجموعة/ترتيب الأسئلة وترتيب الاستكمال ولقطات التصحيح تحت سلطة الخادم وغير موجودة عمدًا ضمن أدوات الإدارة.', 'La graine, la sélection/l’ordre, l’ordre de reprise et les snapshots de notation restent sous l’autorité du Backend et absents des contrôles Admin.'),
            ],
        ];
    }

    /** @return array{configured: bool, question_order: string, slot_count: int, raw: array<string, mixed>} */
    private function blueprintSummary(?string $json): array
    {
        $decoded = $json === null ? [] : $this->decodeMap($json);
        $questionOrder = $decoded['question_order'] ?? 'shuffle';
        if (! is_string($questionOrder) || ! in_array($questionOrder, ['shuffle', 'fixed'], true)) {
            $questionOrder = 'contract-defined';
        }
        $slots = $decoded['slots'] ?? [];

        return [
            'configured' => $json !== null && $decoded !== [],
            'question_order' => $questionOrder,
            'slot_count' => is_array($slots) ? count($slots) : 0,
            'raw' => $decoded,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeMap(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function localizedJson(string $json): string
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return '';
        }

        foreach ([App::getLocale(), 'en', 'ar', 'fr'] as $locale) {
            $value = $decoded[$locale] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function quizKindLabel(string $kind): string
    {
        return match ($kind) {
            'practice', 'practice_quiz' => $this->translate('Practice', 'تدريب', 'Entraînement'),
            'mock_exam' => $this->translate('Mock exam', 'اختبار تجريبي', 'Examen blanc'),
            'exam' => $this->translate('Exam', 'اختبار', 'Examen'),
            default => str_replace('_', ' ', $kind),
        };
    }

    private function translate(string $en, string $ar, string $fr): string
    {
        return match (App::getLocale()) {
            'ar' => $ar,
            'fr' => $fr,
            default => $en,
        };
    }
}
