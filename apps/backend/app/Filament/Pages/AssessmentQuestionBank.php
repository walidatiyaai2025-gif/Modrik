<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use UnitEnum;

final class AssessmentQuestionBank extends Page
{
    protected string $view = 'filament.pages.assessment-question-bank';

    protected static ?string $slug = 'assessment-question-bank';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Assessment;

    public string $trackId = '';

    public string $subjectNodeId = '';

    public string $quizId = '';

    public string $questionType = 'all';

    public string $statusFilter = 'all';

    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'بنك الأسئلة والاختبارات',
            'fr' => 'Banque de questions',
            default => 'Question Bank & Assessments',
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
        return $this->translate(
            'Inspect published questions, approved answers, explanations and quiz membership by academic track. Attempt seed, selected set/order and scoring snapshots remain Backend-owned and are never editable here.',
            'راجع الأسئلة المنشورة والإجابات المعتمدة والشرح وعضوية الاختبارات حسب المسار الأكاديمي. تظل بذرة المحاولة ومجموعة/ترتيب الأسئلة ولقطة التصحيح تحت سلطة الخادم ولا يمكن تعديلها هنا.',
            'Consultez les questions publiées, réponses approuvées, explications et appartenances aux quiz par parcours. La graine, la sélection/l’ordre et le snapshot de notation restent sous l’autorité du Backend.',
        );
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function updatedTrackId(): void
    {
        $this->subjectNodeId = '';
        $this->quizId = '';
    }

    public function updatedSubjectNodeId(): void
    {
        $this->quizId = '';
    }

    /** @return array<string, string> */
    public function trackOptions(): array
    {
        $query = DB::table('academic_tracks')->orderBy('created_at');
        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('is_fixture', false);
        }

        $options = [];
        foreach ($query->get(['id', 'title', 'year_level', 'is_fixture']) as $track) {
            $title = $this->localizedJson((string) $track->title);
            $label = $title !== '' ? $title : $this->translate('Academic track', 'مسار أكاديمي', 'Parcours académique');
            $label .= ' — '.(string) $track->year_level;
            if ((bool) $track->is_fixture) {
                $label .= ' ['.$this->translate('Fixture', 'اختباري', 'Fixture').']';
            }
            $options[(string) $track->id] = $label;
        }

        return $options;
    }

    /** @return array<string, string> */
    public function subjectOptions(): array
    {
        $query = DB::table('curriculum_nodes')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->where('curriculum_nodes.type', 'subject')
            ->orderBy('curriculum_nodes.created_at');

        if ($this->trackId !== '') {
            $query->where('academic_tracks.id', $this->trackId);
        }
        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        $options = [];
        foreach ($query->get([
            'curriculum_nodes.id',
            'curriculum_nodes.title',
            'academic_tracks.title as track_title',
        ]) as $subject) {
            $subjectTitle = $this->localizedJson((string) $subject->title);
            $trackTitle = $this->localizedJson((string) $subject->track_title);
            $label = $subjectTitle !== '' ? $subjectTitle : $this->translate('Subject', 'مادة', 'Matière');
            if ($this->trackId === '' && $trackTitle !== '') {
                $label .= ' — '.$trackTitle;
            }
            $options[(string) $subject->id] = $label;
        }

        return $options;
    }

    /** @return array<string, string> */
    public function quizOptions(): array
    {
        $query = DB::table('quizzes')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'quizzes.curriculum_node_id')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->orderBy('quizzes.created_at');

        if ($this->trackId !== '') {
            $query->where('academic_tracks.id', $this->trackId);
        }
        if ($this->subjectNodeId !== '') {
            $nodeIds = $this->descendantNodeIds($this->subjectNodeId);
            if ($nodeIds === []) {
                return [];
            }
            $query->whereIn('quizzes.curriculum_node_id', $nodeIds);
        }
        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        $options = [];
        foreach ($query->get(['quizzes.id', 'quizzes.title', 'quizzes.kind']) as $quiz) {
            $title = $this->localizedJson((string) $quiz->title);
            $label = $title !== '' ? $title : $this->translate('Assessment', 'تقييم', 'Évaluation');
            $label .= ' — '.$this->quizKindLabel((string) $quiz->kind);
            $options[(string) $quiz->id] = $label;
        }

        return $options;
    }

    /** @return list<array<string, mixed>> */
    public function questionRows(): array
    {
        $query = DB::table('questions')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'questions.curriculum_node_id')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->orderByDesc('questions.updated_at')
            ->limit(500);

        if ($this->trackId !== '') {
            $query->where('academic_tracks.id', $this->trackId);
        }
        if ($this->quizId !== '') {
            $query->whereExists(function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('quiz_questions')
                    ->whereColumn('quiz_questions.question_id', 'questions.id')
                    ->where('quiz_questions.quiz_id', $this->quizId);
            });
        }
        if ($this->questionType !== 'all') {
            $query->where('questions.type', $this->questionType);
        }
        if ($this->statusFilter !== 'all') {
            $query->where('questions.status', $this->statusFilter);
        }
        if (trim($this->search) !== '') {
            $needle = '%'.trim($this->search).'%';
            $query->where(function ($subquery) use ($needle): void {
                $subquery->where('questions.prompt', 'like', $needle)
                    ->orWhere('questions.explanation', 'like', $needle)
                    ->orWhere('curriculum_nodes.title', 'like', $needle)
                    ->orWhere('curriculum_nodes.code', 'like', $needle);
            });
        }
        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        $records = $query->get([
            'questions.id',
            'questions.curriculum_node_id',
            'questions.content_version',
            'questions.type',
            'questions.prompt',
            'questions.options',
            'questions.answer_contract',
            'questions.explanation',
            'questions.maximum_score',
            'questions.status',
            'questions.updated_at',
            'curriculum_nodes.title as node_title',
            'curriculum_nodes.type as node_type',
            'curriculum_nodes.code as node_code',
            'academic_tracks.id as track_id',
            'academic_tracks.title as track_title',
            'academic_tracks.year_level',
        ]);

        if ($records->isEmpty()) {
            return [];
        }

        $trackIds = array_values($records->pluck('track_id')->map(static fn ($value): string => (string) $value)->unique()->values()->all());
        $nodeMap = $this->curriculumNodeMap($trackIds);
        $questionIds = array_values($records->pluck('id')->map(static fn ($value): string => (string) $value)->values()->all());
        $membership = $this->quizMembershipMap($questionIds);

        $rows = [];
        foreach ($records as $record) {
            $subject = $this->subjectForNode((string) $record->curriculum_node_id, $nodeMap);
            if ($this->subjectNodeId !== '' && ($subject['id'] ?? null) !== $this->subjectNodeId) {
                continue;
            }

            $options = $this->decodeList((string) ($record->options ?? ''));
            $answer = $this->decodeMap((string) $record->answer_contract);
            /** @var list<array{id: string, label: string, correct: bool}> $optionRows */
            $optionRows = [];
            $correctOptionId = is_string($answer['correct_option_id'] ?? null) ? $answer['correct_option_id'] : null;
            foreach ($options as $option) {
                if (! is_array($option)) {
                    continue;
                }
                $id = (string) ($option['id'] ?? '');
                $labels = is_array($option['label'] ?? null) ? $option['label'] : [];
                $optionRows[] = [
                    'id' => $id,
                    'label' => $this->localizedArray($labels),
                    'correct' => $correctOptionId !== null && hash_equals($correctOptionId, $id),
                ];
            }

            $questionId = (string) $record->id;
            $rows[] = [
                'id' => $questionId,
                'prompt' => $this->localizedJson((string) $record->prompt),
                'type' => (string) $record->type,
                'type_label' => $this->questionTypeLabel((string) $record->type),
                'status' => (string) $record->status,
                'content_version' => (int) $record->content_version,
                'maximum_score' => (string) $record->maximum_score,
                'explanation' => $this->localizedJson((string) $record->explanation),
                'answer_summary' => $this->answerSummary($answer, $optionRows),
                'answer_contract' => $answer,
                'options' => $optionRows,
                'track_id' => (string) $record->track_id,
                'track_title' => $this->localizedJson((string) $record->track_title),
                'year_level' => (string) $record->year_level,
                'subject_id' => (string) ($subject['id'] ?? ''),
                'subject_title' => (string) ($subject['title'] ?? $this->translate('Unscoped subject', 'مادة غير محددة', 'Matière non déterminée')),
                'node_title' => $this->localizedJson((string) $record->node_title),
                'node_type' => (string) $record->node_type,
                'node_code' => (string) $record->node_code,
                'quizzes' => $membership[$questionId] ?? [],
                'updated_at' => (string) $record->updated_at,
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function quizRows(): array
    {
        $query = DB::table('quizzes')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'quizzes.curriculum_node_id')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->leftJoin('quiz_questions', 'quiz_questions.quiz_id', '=', 'quizzes.id')
            ->groupBy([
                'quizzes.id',
                'quizzes.curriculum_node_id',
                'quizzes.kind',
                'quizzes.blueprint_version',
                'quizzes.title',
                'quizzes.status',
                'quizzes.updated_at',
                'curriculum_nodes.title',
                'curriculum_nodes.type',
                'academic_tracks.id',
                'academic_tracks.title',
                'academic_tracks.year_level',
            ])
            ->orderByDesc('quizzes.updated_at')
            ->limit(200);

        if ($this->trackId !== '') {
            $query->where('academic_tracks.id', $this->trackId);
        }
        if ($this->subjectNodeId !== '') {
            $nodeIds = $this->descendantNodeIds($this->subjectNodeId);
            if ($nodeIds === []) {
                return [];
            }
            $query->whereIn('quizzes.curriculum_node_id', $nodeIds);
        }
        if ($this->quizId !== '') {
            $query->where('quizzes.id', $this->quizId);
        }
        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        return array_values($query->get([
            'quizzes.id',
            'quizzes.curriculum_node_id',
            'quizzes.kind',
            'quizzes.blueprint_version',
            'quizzes.title',
            'quizzes.status',
            'quizzes.updated_at',
            'curriculum_nodes.title as node_title',
            'curriculum_nodes.type as node_type',
            'academic_tracks.id as track_id',
            'academic_tracks.title as track_title',
            'academic_tracks.year_level',
            DB::raw('COUNT(quiz_questions.question_id) as question_count'),
        ])->map(fn (object $record): array => [
            'id' => (string) $record->id,
            'title' => $this->localizedJson((string) $record->title),
            'kind' => (string) $record->kind,
            'kind_label' => $this->quizKindLabel((string) $record->kind),
            'blueprint_version' => (int) $record->blueprint_version,
            'status' => (string) $record->status,
            'node_title' => $this->localizedJson((string) $record->node_title),
            'node_type' => (string) $record->node_type,
            'track_title' => $this->localizedJson((string) $record->track_title),
            'year_level' => (string) $record->year_level,
            'question_count' => (int) $record->question_count,
            'updated_at' => (string) $record->updated_at,
        ])->all());
    }

    /**
     * @param  list<string>  $trackIds
     * @return array<string, array{id: string, parent_id: ?string, type: string, title: string}>
     */
    private function curriculumNodeMap(array $trackIds): array
    {
        $map = [];
        foreach (DB::table('curriculum_nodes')->whereIn('academic_track_id', $trackIds)->get(['id', 'parent_id', 'type', 'title']) as $node) {
            $id = (string) $node->id;
            $map[$id] = [
                'id' => $id,
                'parent_id' => $node->parent_id === null ? null : (string) $node->parent_id,
                'type' => (string) $node->type,
                'title' => $this->localizedJson((string) $node->title),
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array{id: string, parent_id: ?string, type: string, title: string}>  $map
     * @return array{id: string, title: string}|null
     */
    private function subjectForNode(string $nodeId, array $map): ?array
    {
        $seen = [];
        $currentId = $nodeId;
        for ($depth = 0; $depth < 24; $depth++) {
            if (isset($seen[$currentId])) {
                return null;
            }
            $seen[$currentId] = true;
            $node = $map[$currentId] ?? null;
            if ($node === null) {
                return null;
            }
            if ($node['type'] === 'subject') {
                return ['id' => $node['id'], 'title' => $node['title']];
            }
            if ($node['parent_id'] === null) {
                return null;
            }
            $currentId = $node['parent_id'];
        }

        return null;
    }

    /** @return list<string> */
    private function descendantNodeIds(string $subjectNodeId): array
    {
        $subject = DB::table('curriculum_nodes')->where('id', $subjectNodeId)->where('type', 'subject')->first(['id', 'academic_track_id']);
        if ($subject === null) {
            return [];
        }
        if ($this->trackId !== '' && (string) $subject->academic_track_id !== $this->trackId) {
            return [];
        }

        $nodes = DB::table('curriculum_nodes')
            ->where('academic_track_id', (string) $subject->academic_track_id)
            ->get(['id', 'parent_id']);
        $children = [];
        foreach ($nodes as $node) {
            if ($node->parent_id === null) {
                continue;
            }
            $children[(string) $node->parent_id][] = (string) $node->id;
        }

        $result = [];
        $queue = [$subjectNodeId];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (! is_string($id) || in_array($id, $result, true)) {
                continue;
            }
            $result[] = $id;
            foreach ($children[$id] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $questionIds
     * @return array<string, list<array{id: string, title: string, kind: string, status: string}>>
     */
    private function quizMembershipMap(array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $map = [];
        foreach (DB::table('quiz_questions')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_questions.quiz_id')
            ->whereIn('quiz_questions.question_id', $questionIds)
            ->orderBy('quiz_questions.source_position')
            ->get(['quiz_questions.question_id', 'quizzes.id', 'quizzes.title', 'quizzes.kind', 'quizzes.status']) as $row) {
            $map[(string) $row->question_id][] = [
                'id' => (string) $row->id,
                'title' => $this->localizedJson((string) $row->title),
                'kind' => $this->quizKindLabel((string) $row->kind),
                'status' => (string) $row->status,
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $answer
     * @param  list<array{id: string, label: string, correct: bool}>  $options
     */
    private function answerSummary(array $answer, array $options): string
    {
        $correctOptionId = $answer['correct_option_id'] ?? null;
        if (is_string($correctOptionId)) {
            foreach ($options as $option) {
                if ($option['id'] === $correctOptionId) {
                    return $option['label'] !== '' ? $option['label'] : $correctOptionId;
                }
            }

            return $correctOptionId;
        }

        $acceptedAnswers = $answer['accepted_answers'] ?? null;
        if (is_array($acceptedAnswers)) {
            $values = array_values(array_filter(array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $acceptedAnswers)));
            if ($values !== []) {
                return implode(' · ', $values);
            }
        }

        return $this->translate('Structured answer contract — inspect details', 'عقد إجابة منظم — راجع التفاصيل', 'Contrat de réponse structuré — voir les détails');
    }

    /** @return array<string, mixed> */
    private function decodeMap(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<mixed> */
    private function decodeList(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    private function localizedJson(string $json): string
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $this->localizedArray($decoded) : '';
    }

    /** @param array<string|int, mixed> $values */
    private function localizedArray(array $values): string
    {
        $locale = App::getLocale();
        foreach ([$locale, 'en', 'ar', 'fr'] as $key) {
            $value = $values[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function questionTypeLabel(string $type): string
    {
        return match ($type) {
            'single_choice' => $this->translate('Single choice', 'اختيار من متعدد', 'Choix unique'),
            'short_text' => $this->translate('Short text', 'إجابة قصيرة', 'Réponse courte'),
            default => str_replace('_', ' ', $type),
        };
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
