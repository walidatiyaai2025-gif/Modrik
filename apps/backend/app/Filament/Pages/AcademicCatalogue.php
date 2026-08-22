<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AcademicCatalogue extends Page
{
    protected string $view = 'filament.pages.academic-catalogue';

    protected static ?string $slug = 'academic-catalogue';

    public string $search = '';

    public string $fixtureFilter = 'all';

    public ?string $editingId = null;

    public ?string $sourceRequestId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '',
        'board_reference' => '',
        'syllabus_version' => '',
        'year_level' => '',
        'title_en' => '',
        'title_ar' => '',
        'title_fr' => '',
        'is_fixture' => false,
        'reason' => '',
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'الكتالوج الأكاديمي',
            'fr' => 'Catalogue académique',
            default => 'Academic Catalogue',
        };
    }

    public static function getNavigationSort(): int
    {
        return 10;
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'سجّل فقط المسارات الأكاديمية المعتمدة من المالك. لا يتم اختراع قيم المجلس أو المنهج أو الإصدار.',
            'fr' => 'Enregistrez uniquement les parcours approuvés par le propriétaire. Les références de board, syllabus et version ne sont jamais inventées.',
            default => 'Register only owner-approved academic tracks. Board, syllabus and version references are never invented.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $requestId = request()->query('request');
        if (is_string($requestId) === false || $requestId === '') {
            return;
        }

        $row = DB::table('preparation_requests')->where('id', $requestId)->first(['id', 'normalized_settings']);
        if ($row === null) {
            return;
        }

        $settings = json_decode((string) $row->normalized_settings, true);
        $scope = is_array($settings) && is_array($settings['academic_scope'] ?? null)
            ? $settings['academic_scope']
            : [];

        $this->sourceRequestId = (string) $row->id;
        $this->form['code'] = (string) ($scope['track_reference'] ?? '');
        $this->form['board_reference'] = (string) ($scope['board_reference'] ?? '');
        $this->form['syllabus_version'] = (string) ($scope['syllabus_version'] ?? '');
        $this->form['year_level'] = (string) ($scope['year_level'] ?? '');
        $this->form['reason'] = 'Register owner-approved academic scope referenced by preparation request '.$this->sourceRequestId;
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        $query = DB::table('academic_tracks')->orderBy('code')->limit(200);

        if ($this->search !== '') {
            $needle = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('code', 'like', $needle)
                    ->orWhere('board_reference', 'like', $needle)
                    ->orWhere('syllabus_version', 'like', $needle)
                    ->orWhere('year_level', 'like', $needle);
            });
        }

        if (in_array($this->fixtureFilter, ['fixture', 'real'], true)) {
            $query->where('is_fixture', $this->fixtureFilter === 'fixture');
        }

        return $query->get()->map(function (object $row): array {
            $title = json_decode((string) $row->title, true);
            $title = is_array($title) ? $title : [];

            return [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'board_reference' => (string) ($row->board_reference ?? ''),
                'syllabus_version' => (string) ($row->syllabus_version ?? ''),
                'year_level' => (string) $row->year_level,
                'title' => $title,
                'is_fixture' => (bool) $row->is_fixture,
                'locked' => $this->canMutateTrack((string) $row->id) === false,
                'updated_at' => (string) $row->updated_at,
            ];
        })->all();
    }

    public function edit(string $id): void
    {
        $row = DB::table('academic_tracks')->where('id', $id)->first();
        if ($row === null) {
            return;
        }

        if ($this->canMutateTrack($id) === false) {
            throw ValidationException::withMessages([
                'form.code' => $this->translate('This track is referenced by learner or curriculum history and is read-only.', 'هذا المسار مرتبط بسجل طالب أو منهج وأصبح للقراءة فقط.', 'Ce parcours est référencé par un historique apprenant ou curriculum et devient en lecture seule.'),
            ]);
        }

        $title = json_decode((string) $row->title, true);
        $title = is_array($title) ? $title : [];
        $this->editingId = $id;
        $this->form = [
            'code' => (string) $row->code,
            'board_reference' => (string) ($row->board_reference ?? ''),
            'syllabus_version' => (string) ($row->syllabus_version ?? ''),
            'year_level' => (string) $row->year_level,
            'title_en' => (string) ($title['en'] ?? ''),
            'title_ar' => (string) ($title['ar'] ?? ''),
            'title_fr' => (string) ($title['fr'] ?? ''),
            'is_fixture' => (bool) $row->is_fixture,
            'reason' => '',
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $id = $this->editingId;
        if ($id !== null && $this->canMutateTrack($id) === false) {
            throw ValidationException::withMessages(['form.code' => 'Referenced academic tracks cannot be edited.']);
        }

        $data = Validator::make($this->form, [
            'code' => ['required', 'string', 'max:120', Rule::unique('academic_tracks', 'code')->ignore($id)],
            'board_reference' => ['nullable', 'string', 'max:160'],
            'syllabus_version' => ['nullable', 'string', 'max:120'],
            'year_level' => ['required', 'string', 'max:40'],
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_fr' => ['nullable', 'string', 'max:255'],
            'is_fixture' => ['boolean'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ])->validate();

        $now = now();
        $payload = [
            'code' => trim((string) $data['code']),
            'board_reference' => $this->nullableString($data['board_reference'] ?? null),
            'syllabus_version' => $this->nullableString($data['syllabus_version'] ?? null),
            'year_level' => trim((string) $data['year_level']),
            'title' => json_encode(array_filter([
                'en' => trim((string) $data['title_en']),
                'ar' => trim((string) ($data['title_ar'] ?? '')),
                'fr' => trim((string) ($data['title_fr'] ?? '')),
            ], static fn (string $value): bool => $value !== ''), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'is_fixture' => (bool) $data['is_fixture'],
            'updated_at' => $now,
        ];

        DB::transaction(function () use ($id, $payload, $data, $now): void {
            $before = $id === null ? null : DB::table('academic_tracks')->where('id', $id)->first();
            $trackId = $id ?? (string) Str::ulid();

            if ($id === null) {
                DB::table('academic_tracks')->insert($payload + ['id' => $trackId, 'created_at' => $now]);
            } else {
                DB::table('academic_tracks')->where('id', $trackId)->update($payload);
            }

            $after = DB::table('academic_tracks')->where('id', $trackId)->first();
            DB::table('academic_track_audits')->insert([
                'id' => (string) Str::ulid(),
                'academic_track_id' => $trackId,
                'actor_id' => auth()->id(),
                'action' => $id === null ? 'created' : 'updated',
                'before' => $before === null ? null : json_encode((array) $before, JSON_THROW_ON_ERROR),
                'after' => json_encode((array) $after, JSON_THROW_ON_ERROR),
                'reason' => trim((string) $data['reason']),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $this->editingId = null;
        $this->sourceRequestId = null;
        $this->resetForm();
    }

    /** @return array<int, object> */
    public function auditRows(): array
    {
        return DB::table('academic_track_audits')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'academic_track_audits.academic_track_id')
            ->leftJoin('users', 'users.id', '=', 'academic_track_audits.actor_id')
            ->orderByDesc('academic_track_audits.occurred_at')
            ->limit(30)
            ->get([
                'academic_track_audits.action',
                'academic_track_audits.reason',
                'academic_track_audits.occurred_at',
                'academic_tracks.code as track_code',
                'users.email as actor_email',
            ])->all();
    }

    private function canMutateTrack(string $id): bool
    {
        return DB::table('user_academic_contexts')->where('academic_track_id', $id)->doesntExist()
            && DB::table('curriculum_nodes')->where('academic_track_id', $id)->doesntExist();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function resetForm(): void
    {
        $this->form = [
            'code' => '',
            'board_reference' => '',
            'syllabus_version' => '',
            'year_level' => '',
            'title_en' => '',
            'title_ar' => '',
            'title_fr' => '',
            'is_fixture' => false,
            'reason' => '',
        ];
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
