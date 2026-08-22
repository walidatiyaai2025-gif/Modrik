<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class AcademicCatalogue extends Page
{
    private const NEW_OPTION = '__new__';

    protected string $view = 'filament.pages.academic-catalogue';

    protected static ?string $slug = 'academic-catalogue';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Academic;

    public string $search = '';

    public string $fixtureFilter = 'all';

    public ?string $editingId = null;

    public ?string $sourceRequestId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'board_reference' => '',
        'syllabus_version' => '',
        'year_level' => '',
        'new_board_label' => '',
        'new_syllabus_label' => '',
        'new_year_level_label' => '',
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
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'أدخل أسماء مفهومة للمشغل فقط. المراجع والأكواد الداخلية يولدها أو يتحقق منها الخادم.',
            'fr' => 'Saisissez uniquement des libellés compréhensibles. Le serveur génère ou valide les références internes.',
            default => 'Enter operator-readable academic data only. Internal references are generated or validated by the server.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $requestId = request()->query('request');
        if (! is_string($requestId) || $requestId === '') {
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
        $this->form['board_reference'] = (string) ($scope['board_reference'] ?? '');
        $this->form['syllabus_version'] = (string) ($scope['syllabus_version'] ?? '');
        $this->form['year_level'] = (string) ($scope['year_level'] ?? '');
        $this->form['reason'] = 'Register owner-approved academic scope referenced by preparation request '.$this->sourceRequestId;
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        $query = DB::table('academic_tracks')->orderBy('created_at')->limit(200);

        if ($this->search !== '') {
            $needle = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('code', 'like', $needle)
                    ->orWhere('board_reference', 'like', $needle)
                    ->orWhere('syllabus_version', 'like', $needle)
                    ->orWhere('year_level', 'like', $needle)
                    ->orWhere('title', 'like', $needle);
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
                'board_label' => $this->humanizeReference((string) ($row->board_reference ?? '')),
                'syllabus_version' => (string) ($row->syllabus_version ?? ''),
                'syllabus_label' => $this->humanizeReference((string) ($row->syllabus_version ?? '')),
                'year_level' => (string) $row->year_level,
                'year_label' => $this->humanizeReference((string) $row->year_level),
                'title' => $title,
                'is_fixture' => (bool) $row->is_fixture,
                'locked' => $this->canMutateTrack((string) $row->id) === false,
                'updated_at' => (string) $row->updated_at,
            ];
        })->all();
    }

    /** @return array<string, string> */
    public function boardOptions(): array
    {
        return $this->referenceOptions('board_reference');
    }

    /** @return array<string, string> */
    public function syllabusOptions(): array
    {
        return $this->referenceOptions('syllabus_version');
    }

    /** @return array<string, string> */
    public function yearLevelOptions(): array
    {
        return $this->referenceOptions('year_level');
    }

    public function newOptionValue(): string
    {
        return self::NEW_OPTION;
    }

    public function displayReference(string $reference): string
    {
        return $this->humanizeReference($reference);
    }

    public function edit(string $id): void
    {
        $row = DB::table('academic_tracks')->where('id', $id)->first();
        if ($row === null) {
            return;
        }

        if ($this->canMutateTrack($id) === false) {
            throw ValidationException::withMessages([
                'form.title_en' => $this->translate('This track is referenced by learner or curriculum history and is read-only.', 'هذا المسار مرتبط بسجل طالب أو منهج وأصبح للقراءة فقط.', 'Ce parcours est référencé par un historique apprenant ou curriculum et devient en lecture seule.'),
            ]);
        }

        $title = json_decode((string) $row->title, true);
        $title = is_array($title) ? $title : [];
        $this->editingId = $id;
        $this->sourceRequestId = null;
        $this->form = [
            'board_reference' => (string) ($row->board_reference ?? ''),
            'syllabus_version' => (string) ($row->syllabus_version ?? ''),
            'year_level' => (string) $row->year_level,
            'new_board_label' => '',
            'new_syllabus_label' => '',
            'new_year_level_label' => '',
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
        $this->sourceRequestId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $id = $this->editingId;
        if ($id !== null && $this->canMutateTrack($id) === false) {
            throw ValidationException::withMessages(['form.title_en' => 'Referenced academic tracks cannot be edited.']);
        }

        $data = Validator::make($this->form, [
            'board_reference' => ['nullable', 'string', 'max:160'],
            'syllabus_version' => ['nullable', 'string', 'max:160'],
            'year_level' => ['required', 'string', 'max:160'],
            'new_board_label' => ['nullable', 'string', 'min:2', 'max:160'],
            'new_syllabus_label' => ['nullable', 'string', 'min:2', 'max:160'],
            'new_year_level_label' => ['nullable', 'string', 'min:1', 'max:120'],
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_fr' => ['nullable', 'string', 'max:255'],
            'is_fixture' => ['boolean'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ])->validate();

        $sourceScope = $this->sourceScope();
        if ($sourceScope !== null) {
            $code = trim((string) ($sourceScope['track_reference'] ?? ''));
            $boardReference = $this->nullableString($sourceScope['board_reference'] ?? null);
            $syllabusVersion = $this->nullableString($sourceScope['syllabus_version'] ?? null);
            $yearLevel = trim((string) ($sourceScope['year_level'] ?? ''));
            if ($code === '' || $yearLevel === '') {
                throw ValidationException::withMessages([
                    'form.title_en' => $this->translate('The originating preparation request has an incomplete academic scope.', 'طلب إعداد المحتوى الأصلي يحتوي على نطاق أكاديمي غير مكتمل.', 'La demande de préparation d’origine contient un périmètre académique incomplet.'),
                ]);
            }
        } else {
            $boardReference = $this->resolveReference(
                'BOARD',
                (string) ($data['board_reference'] ?? ''),
                (string) ($data['new_board_label'] ?? ''),
                $this->boardOptions(),
                false,
                'form.board_reference',
            );
            $syllabusVersion = $this->resolveReference(
                'SYLLABUS',
                (string) ($data['syllabus_version'] ?? ''),
                (string) ($data['new_syllabus_label'] ?? ''),
                $this->syllabusOptions(),
                false,
                'form.syllabus_version',
            );
            $yearLevel = (string) $this->resolveReference(
                'YEAR',
                (string) $data['year_level'],
                (string) ($data['new_year_level_label'] ?? ''),
                $this->yearLevelOptions(),
                true,
                'form.year_level',
            );

            if ($id !== null) {
                $existingCode = DB::table('academic_tracks')->where('id', $id)->value('code');
                if (! is_string($existingCode) || $existingCode === '') {
                    throw ValidationException::withMessages(['form.title_en' => 'Academic track identity is missing.']);
                }
                $code = $existingCode;
            } else {
                $code = $this->internalReference(
                    'TRACK',
                    (string) $data['title_en'],
                    implode('|', [$boardReference ?? '', $syllabusVersion ?? '', $yearLevel]),
                );
            }
        }

        $duplicate = DB::table('academic_tracks')->where('code', $code);
        if ($id !== null) {
            $duplicate->where('id', '<>', $id);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'form.title_en' => $this->translate('An academic track with the same internal identity already exists.', 'يوجد بالفعل مسار أكاديمي مطابق لهذه البيانات.', 'Un parcours académique avec la même identité existe déjà.'),
            ]);
        }

        $now = now();
        $payload = [
            'code' => $code,
            'board_reference' => $boardReference,
            'syllabus_version' => $syllabusVersion,
            'year_level' => $yearLevel,
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

    /** @return array<int, array{action: string, status: string, reason: string, actor: string, created_at: string}> */
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
                'academic_tracks.title as track_title',
                'users.email as actor_email',
            ])
            ->map(function (object $audit): array {
                $title = json_decode((string) $audit->track_title, true);
                $title = is_array($title) ? $title : [];

                return [
                    'action' => match ((string) $audit->action) {
                        'created' => $this->translate('Academic track created', 'تم إنشاء المسار الأكاديمي', 'Parcours académique créé'),
                        'updated' => $this->translate('Academic track updated', 'تم تحديث المسار الأكاديمي', 'Parcours académique mis à jour'),
                        default => (string) $audit->action,
                    },
                    'status' => (string) ($title[App::getLocale()] ?? $title['en'] ?? $this->translate('Academic track', 'مسار أكاديمي', 'Parcours académique')),
                    'reason' => (string) $audit->reason,
                    'actor' => (string) ($audit->actor_email ?? 'system/removed-user'),
                    'created_at' => (string) $audit->occurred_at,
                ];
            })
            ->all();
    }

    /** @return array<string, string> */
    private function referenceOptions(string $field): array
    {
        $references = DB::table('academic_tracks')
            ->whereNotNull($field)
            ->where($field, '<>', '')
            ->distinct()
            ->pluck($field)
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        foreach ($this->preparationScopes() as $scope) {
            $value = $scope[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $references[] = trim($value);
            }
        }

        $options = [];
        foreach (array_values(array_unique($references)) as $reference) {
            $options[$reference] = $this->humanizeReference($reference);
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** @return list<array<string, mixed>> */
    private function preparationScopes(): array
    {
        $scopes = [];
        foreach (DB::table('preparation_requests')->orderByDesc('created_at')->limit(200)->pluck('normalized_settings') as $settingsJson) {
            $settings = json_decode((string) $settingsJson, true);
            $scope = is_array($settings) && is_array($settings['academic_scope'] ?? null)
                ? $settings['academic_scope']
                : null;
            if (is_array($scope)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /** @return array<string, mixed>|null */
    private function sourceScope(): ?array
    {
        if ($this->sourceRequestId === null) {
            return null;
        }

        $settingsJson = DB::table('preparation_requests')->where('id', $this->sourceRequestId)->value('normalized_settings');
        if (! is_string($settingsJson) || $settingsJson === '') {
            return null;
        }
        $settings = json_decode($settingsJson, true);
        $scope = is_array($settings) && is_array($settings['academic_scope'] ?? null)
            ? $settings['academic_scope']
            : null;

        return is_array($scope) ? $scope : null;
    }

    /** @param array<string, string> $options */
    private function resolveReference(
        string $prefix,
        string $selected,
        string $newLabel,
        array $options,
        bool $required,
        string $errorKey,
    ): ?string {
        $selected = trim($selected);
        if ($selected === '') {
            if ($required) {
                throw ValidationException::withMessages([$errorKey => $this->translate('Choose a value.', 'اختر قيمة من القائمة.', 'Choisissez une valeur.')]);
            }

            return null;
        }

        if ($selected === self::NEW_OPTION) {
            $newLabel = trim($newLabel);
            if ($newLabel === '') {
                throw ValidationException::withMessages([$errorKey => $this->translate('Enter the readable name for the new value.', 'اكتب الاسم المقروء للقيمة الجديدة.', 'Saisissez le nom lisible de la nouvelle valeur.')]);
            }

            return $this->internalReference($prefix, $newLabel);
        }

        if (! array_key_exists($selected, $options)) {
            throw ValidationException::withMessages([$errorKey => $this->translate('The selected value is no longer available. Choose it again.', 'القيمة المختارة لم تعد متاحة. اخترها مرة أخرى.', 'La valeur sélectionnée n’est plus disponible. Choisissez-la de nouveau.')]);
        }

        return $selected;
    }

    private function internalReference(string $prefix, string $label, string $context = ''): string
    {
        $normalized = mb_strtolower(trim($label));
        $slug = Str::upper(Str::slug(Str::ascii($label), '-'));
        if ($slug === '') {
            $slug = 'ITEM';
        }
        $slug = mb_substr($slug, 0, 72);
        $hash = Str::upper(substr(hash('sha256', $normalized.'|'.$context), 0, 8));

        return $prefix.':'.$slug.':'.$hash;
    }

    private function humanizeReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return $this->translate('Not specified', 'غير محدد', 'Non spécifié');
        }

        $segments = preg_split('/[:\/]+/', $reference) ?: [$reference];
        if ($segments !== [] && in_array(Str::upper((string) $segments[0]), ['BOARD', 'SYLLABUS', 'YEAR', 'TRACK', 'SUBJECT'], true)) {
            array_shift($segments);
        }
        if (count($segments) > 1 && preg_match('/^[A-F0-9]{8}$/i', (string) end($segments)) === 1) {
            array_pop($segments);
        }
        $readable = trim(implode(' ', $segments));
        $readable = str_replace(['-', '_', '.'], ' ', $readable);

        return Str::headline($readable === '' ? $reference : $readable);
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
            'board_reference' => '',
            'syllabus_version' => '',
            'year_level' => '',
            'new_board_label' => '',
            'new_syllabus_label' => '',
            'new_year_level_label' => '',
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
