<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class AcademicTrackAvailability extends Page
{
    protected string $view = 'filament.pages.academic-track-availability';

    protected static ?string $slug = 'academic-catalogue/availability';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Academic;

    public ?string $trackId = null;

    public ?string $targetState = null;

    public string $reason = '';

    public bool $confirmHistoricalRetirement = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'إتاحة المسارات الأكاديمية',
            'fr' => 'Disponibilité des parcours',
            default => 'Academic Track Availability',
        };
    }

    public static function getNavigationSort(): int
    {
        return 11;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'انشر أو اسحب المسارات من كتالوج الطالب مع سبب تدقيق صريح وحماية السجل التاريخي.',
            'fr' => 'Publiez ou retirez les parcours du catalogue étudiant avec motif audité et protection de l’historique.',
            default => 'Publish or retire tracks from the learner catalogue with an audited reason and preserved history.',
        };
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        /** @var list<array<string, mixed>> $records */
        $records = DB::table('academic_tracks')
            ->orderBy('created_at')
            ->limit(200)
            ->get(['id', 'title', 'year_level', 'availability_state'])
            ->map(static fn (object $record): array => (array) $record)
            ->values()
            ->all();

        $rows = [];
        foreach ($records as $record) {
            $id = $this->arrayString($record, 'id');

            $rows[] = [
                'id' => $id,
                'title' => $this->localizedTitle($this->arrayString($record, 'title')),
                'year' => $this->humanizeReference($this->arrayString($record, 'year_level')),
                'state' => $this->arrayString($record, 'availability_state', 'draft'),
                'has_history' => $this->hasHistoricalReferences($id),
            ];
        }

        return $rows;
    }

    public function begin(string $id, string $targetState): void
    {
        if (! in_array($targetState, ['published', 'retired'], true)) {
            return;
        }

        $row = DB::table('academic_tracks')->where('id', $id)->first(['id', 'availability_state']);
        if ($row === null) {
            return;
        }

        $data = (array) $row;
        $current = $this->arrayString($data, 'availability_state');
        $allowed = ($targetState === 'published' && $current === 'draft')
            || ($targetState === 'retired' && $current === 'published');
        if (! $allowed) {
            throw ValidationException::withMessages([
                'reason' => $this->translate('That lifecycle transition is not allowed.', 'هذا الانتقال في دورة الحياة غير مسموح.', 'Cette transition de cycle de vie n’est pas autorisée.'),
            ]);
        }

        $this->trackId = $id;
        $this->targetState = $targetState;
        $this->reason = '';
        $this->confirmHistoricalRetirement = false;
    }

    public function cancel(): void
    {
        $this->trackId = null;
        $this->targetState = null;
        $this->reason = '';
        $this->confirmHistoricalRetirement = false;
    }

    public function apply(): void
    {
        $id = $this->trackId;
        $target = $this->targetState;
        $reason = trim($this->reason);

        if ($id === null || $target === null || ! in_array($target, ['published', 'retired'], true)) {
            return;
        }

        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => $this->translate('Enter an operator reason between 8 and 500 characters.', 'اكتب سببًا تشغيليًا بين 8 و500 حرف.', 'Saisissez un motif opérateur de 8 à 500 caractères.'),
            ]);
        }

        DB::transaction(function () use ($id, $target, $reason): void {
            $before = DB::table('academic_tracks')->where('id', $id)->lockForUpdate()->first();
            if ($before === null) {
                throw ValidationException::withMessages(['reason' => $this->translate('Track no longer exists.', 'المسار لم يعد موجودًا.', 'Le parcours n’existe plus.')]);
            }

            $beforeData = (array) $before;
            $current = $this->arrayString($beforeData, 'availability_state');
            $allowed = ($target === 'published' && $current === 'draft')
                || ($target === 'retired' && $current === 'published');
            if (! $allowed) {
                throw ValidationException::withMessages(['reason' => $this->translate('Track state changed. Refresh and try again.', 'تغيرت حالة المسار. حدّث الصفحة وحاول مرة أخرى.', 'L’état du parcours a changé. Actualisez puis réessayez.')]);
            }

            if ($target === 'retired' && $this->hasHistoricalReferences($id) && ! $this->confirmHistoricalRetirement) {
                throw ValidationException::withMessages([
                    'confirmHistoricalRetirement' => $this->translate('Confirm retirement while preserving existing learner and curriculum history.', 'أكد سحب المسار مع الحفاظ على سجل الطلاب والمنهج الحالي.', 'Confirmez le retrait tout en conservant l’historique apprenant et curriculum.'),
                ]);
            }

            $now = now();
            DB::table('academic_tracks')->where('id', $id)->update([
                'availability_state' => $target,
                'updated_at' => $now,
            ]);
            $after = DB::table('academic_tracks')->where('id', $id)->first();
            $afterData = $after === null ? [] : (array) $after;

            DB::table('academic_track_audits')->insert([
                'id' => (string) Str::ulid(),
                'academic_track_id' => $id,
                'actor_id' => auth()->id(),
                'action' => $target === 'published' ? 'published' : 'retired',
                'before' => json_encode($beforeData, JSON_THROW_ON_ERROR),
                'after' => json_encode($afterData, JSON_THROW_ON_ERROR),
                'reason' => $reason,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $this->cancel();
    }

    private function hasHistoricalReferences(string $id): bool
    {
        return DB::table('user_academic_contexts')->where('academic_track_id', $id)->exists()
            || DB::table('curriculum_nodes')->where('academic_track_id', $id)->exists();
    }

    /** @param array<string, mixed> $row */
    private function arrayString(array $row, string $key, string $default = ''): string
    {
        $value = $row[$key] ?? $default;

        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : $default;
    }

    private function localizedTitle(string $encoded): string
    {
        $decoded = json_decode($encoded, true);
        if (! is_array($decoded)) {
            return $this->translate('Academic track', 'مسار أكاديمي', 'Parcours académique');
        }

        $locale = App::getLocale();
        $localized = $decoded[$locale] ?? null;
        if (is_string($localized) && $localized !== '') {
            return $localized;
        }

        $english = $decoded['en'] ?? null;

        return is_string($english) && $english !== ''
            ? $english
            : $this->translate('Academic track', 'مسار أكاديمي', 'Parcours académique');
    }

    private function humanizeReference(string $reference): string
    {
        $segments = preg_split('/[:\\/]+/', trim($reference)) ?: [$reference];
        $firstSegment = $segments[0] ?? null;
        if (is_string($firstSegment) && Str::upper($firstSegment) === 'YEAR') {
            array_shift($segments);
        }

        return Str::headline(str_replace(['-', '_', '.'], ' ', implode(' ', $segments)));
    }

    public function stateLabel(string $state): string
    {
        return match ($state) {
            'draft' => $this->translate('Draft', 'مسودة', 'Brouillon'),
            'published' => $this->translate('Published', 'منشور', 'Publié'),
            'retired' => $this->translate('Retired', 'مسحوب', 'Retiré'),
            default => $state,
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
