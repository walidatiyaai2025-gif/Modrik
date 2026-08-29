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

final class AcademicCatalogueMetadata extends Page
{
    protected string $view = 'filament.pages.academic-catalogue-metadata';

    protected static ?string $slug = 'academic-catalogue/metadata';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Academic;

    public ?string $yearLevel = null;

    public string $yearLabelAr = '';

    public string $yearLabelEn = '';

    public string $yearLabelFr = '';

    public int $yearDisplayOrder = 0;

    public string $yearReason = '';

    public ?string $trackId = null;

    public int $trackDisplayOrder = 0;

    public string $trackReason = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'ترتيب وبيانات الكتالوج الأكاديمي',
            'fr' => 'Métadonnées du catalogue académique',
            default => 'Academic Catalogue Metadata',
        };
    }

    public static function getNavigationSort(): int
    {
        return 12;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    /** @return list<array{year_level: string, labels: array<mixed>, display_order: int}> */
    public function years(): array
    {
        $rows = DB::table('academic_tracks')
            ->select('academic_tracks.year_level', 'academic_year_metadata.labels', 'academic_year_metadata.display_order')
            ->leftJoin('academic_year_metadata', 'academic_year_metadata.year_level', '=', 'academic_tracks.year_level')
            ->distinct()
            ->orderByRaw('CASE WHEN academic_year_metadata.display_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('academic_year_metadata.display_order')
            ->orderBy('academic_tracks.year_level')
            ->get()
            ->map(function (object $row): array {
                $data = (array) $row;
                $labels = json_decode((string) ($data['labels'] ?? ''), true);

                return [
                    'year_level' => (string) $data['year_level'],
                    'labels' => is_array($labels) ? $labels : [],
                    'display_order' => (int) ($data['display_order'] ?? 0),
                ];
            })
            ->all();

        return array_values($rows);
    }

    /** @return list<array<string, mixed>> */
    public function tracks(): array
    {
        $rows = DB::table('academic_tracks')
            ->orderBy('year_level')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id', 'year_level', 'title', 'display_order'])
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        return array_values($rows);
    }

    public function beginYear(string $yearLevel): void
    {
        if (! DB::table('academic_tracks')->where('year_level', $yearLevel)->exists()) {
            return;
        }

        $metadata = DB::table('academic_year_metadata')->where('year_level', $yearLevel)->first();
        $data = $metadata === null ? [] : (array) $metadata;
        $labels = json_decode((string) ($data['labels'] ?? ''), true);
        $labels = is_array($labels) ? $labels : [];

        $this->yearLevel = $yearLevel;
        $this->yearLabelAr = (string) ($labels['ar'] ?? '');
        $this->yearLabelEn = (string) ($labels['en'] ?? '');
        $this->yearLabelFr = (string) ($labels['fr'] ?? '');
        $this->yearDisplayOrder = (int) ($data['display_order'] ?? 0);
        $this->yearReason = '';
    }

    public function saveYear(): void
    {
        $yearLevel = $this->yearLevel;
        if ($yearLevel === null || ! DB::table('academic_tracks')->where('year_level', $yearLevel)->exists()) {
            return;
        }

        $labels = [
            'ar' => $this->safeLabel($this->yearLabelAr),
            'en' => $this->safeLabel($this->yearLabelEn),
            'fr' => $this->safeLabel($this->yearLabelFr),
        ];
        if (in_array(null, $labels, true)) {
            throw ValidationException::withMessages([
                'yearLabelEn' => $this->translate('Provide safe AR, EN and FR labels (1–160 characters, no markup).', 'أدخل تسميات عربية وإنجليزية وفرنسية آمنة (1–160 حرفًا بدون ترميز).', 'Renseignez des libellés AR, EN et FR sûrs (1–160 caractères, sans balisage).'),
            ]);
        }

        $reason = $this->validatedReason($this->yearReason, 'yearReason');
        DB::transaction(function () use ($yearLevel, $labels, $reason): void {
            if (! DB::table('academic_tracks')->where('year_level', $yearLevel)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['yearReason' => $this->translate('School year no longer exists.', 'السنة الدراسية لم تعد موجودة.', 'L’année scolaire n’existe plus.')]);
            }

            $before = DB::table('academic_year_metadata')->where('year_level', $yearLevel)->lockForUpdate()->first();
            $beforeData = $before === null ? null : (array) $before;
            $now = now();
            $payload = [
                'labels' => json_encode($labels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'display_order' => $this->yearDisplayOrder,
                'updated_at' => $now,
            ];

            if ($before === null) {
                DB::table('academic_year_metadata')->insert(['year_level' => $yearLevel, ...$payload, 'created_at' => $now]);
            } else {
                DB::table('academic_year_metadata')->where('year_level', $yearLevel)->update($payload);
            }

            $after = DB::table('academic_year_metadata')->where('year_level', $yearLevel)->first();
            $this->audit('year', $yearLevel, 'metadata_updated', $beforeData, $after === null ? [] : (array) $after, $reason, $now);
        });

        $this->yearLevel = null;
        $this->yearReason = '';
    }

    public function beginTrack(string $trackId): void
    {
        $track = DB::table('academic_tracks')->where('id', $trackId)->first(['id', 'display_order']);
        if ($track === null) {
            return;
        }

        $data = (array) $track;
        $this->trackId = $trackId;
        $this->trackDisplayOrder = (int) ($data['display_order'] ?? 0);
        $this->trackReason = '';
    }

    public function saveTrack(): void
    {
        $trackId = $this->trackId;
        if ($trackId === null) {
            return;
        }

        $reason = $this->validatedReason($this->trackReason, 'trackReason');
        DB::transaction(function () use ($trackId, $reason): void {
            $before = DB::table('academic_tracks')->where('id', $trackId)->lockForUpdate()->first(['id', 'year_level', 'display_order']);
            if ($before === null) {
                throw ValidationException::withMessages(['trackReason' => $this->translate('Track no longer exists.', 'المسار لم يعد موجودًا.', 'Le parcours n’existe plus.')]);
            }

            $beforeData = (array) $before;
            $now = now();
            DB::table('academic_tracks')->where('id', $trackId)->update([
                'display_order' => $this->trackDisplayOrder,
                'updated_at' => $now,
            ]);
            $after = DB::table('academic_tracks')->where('id', $trackId)->first(['id', 'year_level', 'display_order']);
            $this->audit('track', $trackId, 'display_order_updated', $beforeData, $after === null ? [] : (array) $after, $reason, $now);
        });

        $this->trackId = null;
        $this->trackReason = '';
    }

    private function validatedReason(string $value, string $field): string
    {
        $reason = trim($value);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500 || strip_tags($reason) !== $reason || preg_match('/[\p{Cc}\p{Cf}]/u', $reason) === 1) {
            throw ValidationException::withMessages([
                $field => $this->translate('Enter a safe operator reason between 8 and 500 characters.', 'اكتب سببًا تشغيليًا آمنًا بين 8 و500 حرف.', 'Saisissez un motif opérateur sûr de 8 à 500 caractères.'),
            ]);
        }

        return $reason;
    }

    /**
     * @param  null|array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(string $targetType, string $targetKey, string $action, ?array $before, array $after, string $reason, mixed $now): void
    {
        DB::table('academic_catalogue_metadata_audits')->insert([
            'id' => (string) Str::ulid(),
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'actor_id' => auth()->id(),
            'action' => $action,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'after' => json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'reason' => $reason,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function safeLabel(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 160 || strip_tags($value) !== $value || preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            return null;
        }

        return $value;
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
