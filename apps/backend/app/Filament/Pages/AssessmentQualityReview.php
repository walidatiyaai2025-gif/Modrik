<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use UnitEnum;

final class AssessmentQualityReview extends Page
{
    protected string $view = 'filament.pages.assessment-quality-review';

    protected static ?string $slug = 'assessment-quality-review';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Assessment;

    public string $statusFilter = 'all';

    public string $metadataFilter = 'all';

    public string $shuffleFilter = 'all';

    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مراجعة جودة الأسئلة',
            'fr' => 'Qualité des questions',
            default => 'Question Quality Review',
        };
    }

    public static function getNavigationSort(): int
    {
        return 15;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->translate(
            'Review persisted assessment metadata, effective option-order safety and historical snapshot usage without changing the canonical bank or any student attempt.',
            'راجع بيانات التقييم المحفوظة وسلامة ترتيب الاختيارات الفعلية واستخدام اللقطات التاريخية دون تغيير بنك الأسئلة أو أي محاولة لطالب.',
            'Contrôlez les métadonnées persistées, la sûreté effective de l’ordre des options et l’usage des snapshots historiques sans modifier la banque ni aucune tentative.',
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
    public function rows(): array
    {
        $query = DB::table('questions')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'questions.curriculum_node_id')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->orderByDesc('questions.updated_at')
            ->limit(500);

        if ($this->statusFilter !== 'all') {
            $query->where('questions.status', $this->statusFilter);
        }
        if ($this->metadataFilter === 'present') {
            $query->whereNotNull('questions.assessment_metadata');
        } elseif ($this->metadataFilter === 'missing') {
            $query->whereNull('questions.assessment_metadata');
        }
        if (trim($this->search) !== '') {
            $needle = '%'.trim($this->search).'%';
            $query->where(function ($subquery) use ($needle): void {
                $subquery->where('questions.prompt', 'like', $needle)
                    ->orWhere('curriculum_nodes.title', 'like', $needle)
                    ->orWhere('curriculum_nodes.code', 'like', $needle);
            });
        }
        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        $records = $query->get([
            'questions.id',
            'questions.prompt',
            'questions.type',
            'questions.status',
            'questions.content_version',
            'questions.maximum_score',
            'questions.assessment_metadata',
            'questions.option_shuffle_safe',
            'questions.updated_at',
            'curriculum_nodes.code as node_code',
            'curriculum_nodes.title as node_title',
            'academic_tracks.title as track_title',
            'academic_tracks.year_level',
            'academic_tracks.is_fixture',
        ]);

        if ($records->isEmpty()) {
            return [];
        }

        $questionIds = array_values($records->pluck('id')->map(static fn ($value): string => (string) $value)->all());
        $membershipCounts = DB::table('quiz_questions')
            ->whereIn('question_id', $questionIds)
            ->selectRaw('question_id, COUNT(*) as aggregate_count')
            ->groupBy('question_id')
            ->pluck('aggregate_count', 'question_id');
        $snapshotCounts = DB::table('attempt_questions')
            ->whereIn('question_id', $questionIds)
            ->selectRaw('question_id, COUNT(*) as aggregate_count')
            ->groupBy('question_id')
            ->pluck('aggregate_count', 'question_id');

        $rows = $records->map(function (object $record) use ($membershipCounts, $snapshotCounts): array {
            $questionId = (string) $record->id;
            $metadata = $record->assessment_metadata === null
                ? []
                : $this->decodeMap((string) $record->assessment_metadata);
            $concepts = $metadata['concepts'] ?? [];
            if (! is_array($concepts)) {
                $concepts = [];
            }
            $concepts = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                $concepts,
            )));

            $unsafeReasons = [];
            $semantics = $metadata['option_order_semantics'] ?? null;
            if (is_string($semantics) && in_array($semantics, ['fixed', 'sequence', 'ordered', 'image_letter', 'all_none'], true)) {
                $unsafeReasons[] = $semantics;
            }
            foreach (['sequence_sensitive', 'image_letter_mapping', 'all_none_semantics'] as $flag) {
                if (($metadata[$flag] ?? false) === true) {
                    $unsafeReasons[] = $flag;
                }
            }
            $unsafeReasons = array_values(array_unique($unsafeReasons));
            $explicitShuffleSafe = (bool) $record->option_shuffle_safe;
            $effectiveShuffleSafe = $explicitShuffleSafe && $unsafeReasons === [];

            return [
                'id' => $questionId,
                'prompt' => $this->localizedJson((string) $record->prompt),
                'type' => (string) $record->type,
                'status' => (string) $record->status,
                'content_version' => (int) $record->content_version,
                'maximum_score' => (string) $record->maximum_score,
                'metadata_present' => $metadata !== [],
                'section' => is_string($metadata['section'] ?? null) ? (string) $metadata['section'] : null,
                'difficulty' => is_string($metadata['difficulty'] ?? null) ? (string) $metadata['difficulty'] : null,
                'concepts' => $concepts,
                'explicit_shuffle_safe' => $explicitShuffleSafe,
                'effective_shuffle_safe' => $effectiveShuffleSafe,
                'unsafe_reasons' => $unsafeReasons,
                'membership_count' => (int) ($membershipCounts[$questionId] ?? 0),
                'snapshot_count' => (int) ($snapshotCounts[$questionId] ?? 0),
                'track_title' => $this->localizedJson((string) $record->track_title),
                'year_level' => (string) $record->year_level,
                'node_title' => $this->localizedJson((string) $record->node_title),
                'node_code' => (string) $record->node_code,
                'is_fixture' => (bool) $record->is_fixture,
                'updated_at' => (string) $record->updated_at,
            ];
        });

        if ($this->shuffleFilter === 'safe') {
            $rows = $rows->filter(static fn (array $row): bool => (bool) $row['effective_shuffle_safe']);
        } elseif ($this->shuffleFilter === 'fixed') {
            $rows = $rows->filter(static fn (array $row): bool => ! (bool) $row['effective_shuffle_safe']);
        }

        return array_values($rows->values()->all());
    }

    /** @return array{questions: int, metadata_present: int, shuffle_safe: int, historical_snapshots: int} */
    public function metrics(): array
    {
        $rows = $this->rows();

        return [
            'questions' => count($rows),
            'metadata_present' => count(array_filter($rows, static fn (array $row): bool => (bool) $row['metadata_present'])),
            'shuffle_safe' => count(array_filter($rows, static fn (array $row): bool => (bool) $row['effective_shuffle_safe'])),
            'historical_snapshots' => array_sum(array_map(static fn (array $row): int => (int) $row['snapshot_count'], $rows)),
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

    private function translate(string $en, string $ar, string $fr): string
    {
        return match (App::getLocale()) {
            'ar' => $ar,
            'fr' => $fr,
            default => $en,
        };
    }
}
