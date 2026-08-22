<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ContentPreparationWizard extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.content-preparation-wizard';

    protected static ?string $slug = 'content-preparation';

    public int $step = 1;

    /** @var list<string> */
    public array $locales = ['ar', 'en', 'fr'];

    /** Canonical persisted selection used by normal operator flows. */
    public string $academicTrackId = '';

    /** Human-readable subject names entered by the operator. */
    public string $subjectNames = '';

    /**
     * Legacy/internal scope mirrors retained only for loading existing requests and fixtures.
     * They are not operator inputs and are never trusted for a new request payload.
     */
    public string $trackReference = '';

    public string $boardReference = '';

    public string $syllabusVersion = '';

    public string $yearLevel = '';

    public string $subjectReferences = '';

    /** @var list<string> */
    public array $contentTypes = ['lesson', 'practice_quiz'];

    public bool $includeAnswerExplanations = true;

    public int $maximumQuestionsPerQuiz = 20;

    public ?string $preparationRequestId = null;

    public ?string $pendingRegenerationRequestId = null;

    /** @var array<string, mixed> */
    public array $requestResult = [];

    /** @var array<string, mixed> */
    public array $validationResult = [];

    public mixed $returnedZip = null;

    public function mount(): void
    {
        $requestId = request()->query('request');
        if (is_string($requestId) && $requestId !== '') {
            $this->loadRequest($requestId);
            $this->step = 4;
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.preparation.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.preparation.title');
    }

    public function getSubheading(): string
    {
        return __('admin.preparation.subtitle');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string, string> */
    public function academicTrackOptions(): array
    {
        $query = DB::table('academic_tracks')->orderBy('created_at');
        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('is_fixture', false);
        }

        $options = [];
        foreach ($query->get(['id', 'title', 'board_reference', 'syllabus_version', 'year_level', 'is_fixture']) as $track) {
            $title = json_decode((string) $track->title, true);
            $title = is_array($title) ? $title : [];
            $name = (string) ($title[App::getLocale()] ?? $title['en'] ?? $this->translate('Academic track', 'مسار أكاديمي', 'Parcours académique'));
            $context = array_values(array_filter([
                $this->humanizeReference((string) ($track->board_reference ?? '')),
                $this->humanizeReference((string) ($track->syllabus_version ?? '')),
                $this->humanizeReference((string) $track->year_level),
            ], fn (string $value): bool => $value !== $this->translate('Not specified', 'غير محدد', 'Non spécifié')));
            $label = $name;
            if ($context !== []) {
                $label .= ' — '.implode(' · ', $context);
            }
            if ((bool) $track->is_fixture) {
                $label .= ' ['.$this->translate('Fixture', 'اختباري', 'Fixture').']';
            }
            $options[(string) $track->id] = $label;
        }

        return $options;
    }

    /** @return array{title: string, board: string, syllabus: string, year: string}|null */
    public function selectedAcademicTrackSummary(): ?array
    {
        $track = $this->academicTrackRecord($this->academicTrackId);
        if ($track === null) {
            return null;
        }
        $title = json_decode((string) $track->title, true);
        $title = is_array($title) ? $title : [];

        return [
            'title' => (string) ($title[App::getLocale()] ?? $title['en'] ?? $this->translate('Academic track', 'مسار أكاديمي', 'Parcours académique')),
            'board' => $this->humanizeReference((string) ($track->board_reference ?? '')),
            'syllabus' => $this->humanizeReference((string) ($track->syllabus_version ?? '')),
            'year' => $this->humanizeReference((string) $track->year_level),
        ];
    }

    public function updatedAcademicTrackId(): void
    {
        $track = $this->academicTrackRecord($this->academicTrackId);
        if ($track === null) {
            $this->trackReference = '';
            $this->boardReference = '';
            $this->syllabusVersion = '';
            $this->yearLevel = '';

            return;
        }

        $this->trackReference = (string) $track->code;
        $this->boardReference = (string) ($track->board_reference ?? '');
        $this->syllabusVersion = (string) ($track->syllabus_version ?? '');
        $this->yearLevel = (string) $track->year_level;
    }

    public function nextStep(): void
    {
        $this->validateStep($this->step);
        $this->step = min(4, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function loadSyntheticFixture(): void
    {
        if (! (bool) config('modrik.fixture.enabled')) {
            Notification::make()->title(__('admin.messages.fixture_unavailable'))->warning()->send();

            return;
        }
        $path = base_path('../../tests/fixtures/content-pack/v1/valid/preparation-settings.json');
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            Notification::make()->title(__('admin.messages.operation_failed'))->danger()->send();

            return;
        }
        $settings = json_decode($contents, true);
        if (! is_array($settings)) {
            Notification::make()->title(__('admin.messages.operation_failed'))->danger()->send();

            return;
        }
        $this->hydrateFromSettings($settings);
        Notification::make()
            ->title(__('admin.messages.fixture_loaded'))
            ->body(__('admin.messages.fixture_warning'))
            ->success()
            ->send();
    }

    public function generate(): void
    {
        if ($this->preparationRequestId !== null) {
            $this->requestRegeneration();

            return;
        }

        $this->validateAllSettings();
        $payload = $this->payload();

        try {
            $result = app(ContentAdminWorkflowService::class)->createRequest($this->operator(), $payload);
            $this->applyPreparationResult($result);
        } catch (ApiProblemException $exception) {
            $this->notifyProblem($exception);
        } catch (Throwable) {
            Notification::make()->title(__('admin.messages.operation_failed'))->danger()->send();
        }
    }

    public function requestRegeneration(): void
    {
        if ($this->preparationRequestId === null) {
            $this->generate();

            return;
        }

        $this->validateAllSettings();
        $this->pendingRegenerationRequestId = $this->preparationRequestId;
        $this->dispatch('open-modal', id: 'confirm-content-regeneration');
    }

    public function cancelRegeneration(): void
    {
        $this->pendingRegenerationRequestId = null;
        $this->dispatch('close-modal', id: 'confirm-content-regeneration');
    }

    public function confirmRegeneration(): void
    {
        $requestId = $this->pendingRegenerationRequestId;
        if ($requestId === null || $this->preparationRequestId === null || ! hash_equals($this->preparationRequestId, $requestId)) {
            $this->pendingRegenerationRequestId = null;
            $this->dispatch('close-modal', id: 'confirm-content-regeneration');
            Notification::make()->title(__('admin.messages.confirmation_stale'))->warning()->send();

            return;
        }

        $this->validateAllSettings();
        $payload = $this->payload();
        $this->pendingRegenerationRequestId = null;
        $this->dispatch('close-modal', id: 'confirm-content-regeneration');

        try {
            $result = app(ContentAdminWorkflowService::class)->regenerateRequest($this->operator(), $requestId, $payload);
            $this->applyPreparationResult($result);
        } catch (ApiProblemException $exception) {
            $this->notifyProblem($exception);
        } catch (Throwable) {
            Notification::make()->title(__('admin.messages.operation_failed'))->danger()->send();
        }
    }

    public function uploadReturnedZip(): void
    {
        if ($this->preparationRequestId === null) {
            Notification::make()->title(__('admin.messages.generate_first'))->warning()->send();

            return;
        }
        $this->validate([
            'returnedZip' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ]);
        if (! $this->returnedZip instanceof UploadedFile) {
            Notification::make()->title(__('admin.messages.zip_required'))->danger()->send();

            return;
        }

        try {
            $result = app(ContentAdminWorkflowService::class)->stageReturnedArchive(
                $this->operator(),
                $this->preparationRequestId,
                $this->returnedZip,
            );
            $this->validationResult = $result;
            $this->returnedZip = null;
            if ($result['accepted']) {
                Notification::make()->title(__('admin.messages.zip_validated'))->success()->send();
            } else {
                Notification::make()->title(__('admin.messages.zip_rejected'))->danger()->send();
            }
        } catch (ApiProblemException $exception) {
            $this->notifyProblem($exception);
        } catch (Throwable) {
            Notification::make()->title(__('admin.messages.operation_failed'))->danger()->send();
        }
    }

    public function downloadPrompt(): ?StreamedResponse
    {
        if ($this->requestResult === [] || $this->preparationRequestId === null) {
            return null;
        }
        $prompt = (string) ($this->requestResult['prompt'] ?? '');
        $filename = 'modrik-prompt-'.$this->preparationRequestId.'-v'.(string) ($this->requestResult['schema_version'] ?? 'unknown').'.txt';

        return response()->streamDownload(static function () use ($prompt): void {
            echo $prompt;
        }, $filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function downloadBundle(): ?StreamedResponse
    {
        if ($this->requestResult === [] || $this->preparationRequestId === null) {
            return null;
        }
        $bundle = [
            'preparation_request_id' => $this->preparationRequestId,
            'schema_version' => $this->requestResult['schema_version'] ?? null,
            'settings_hash' => $this->requestResult['settings_hash'] ?? null,
            'prompt' => $this->requestResult['prompt'] ?? null,
            'bundle' => $this->requestResult['bundle'] ?? null,
        ];
        $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $filename = 'modrik-preparation-bundle-'.$this->preparationRequestId.'-v'.(string) ($this->requestResult['schema_version'] ?? 'unknown').'.json';

        return response()->streamDownload(static function () use ($json): void {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en', 'fr'], true)) {
            return;
        }
        session()->put('admin_locale', $locale);
        App::setLocale($locale);
    }

    private function loadRequest(string $requestId): void
    {
        try {
            $result = app(ContentAdminWorkflowService::class)->requestDetails($this->operator(), $requestId);
            $this->requestResult = $result;
            $this->preparationRequestId = $requestId;
            $settings = $result['bundle']['settings'] ?? null;
            if (is_array($settings)) {
                $this->hydrateFromSettings($settings);
            }
        } catch (ApiProblemException $exception) {
            $this->notifyProblem($exception);
        }
    }

    /** @param array<string, mixed> $result */
    private function applyPreparationResult(array $result): void
    {
        $this->requestResult = $result;
        $this->preparationRequestId = (string) $result['preparation_request_id'];
        $this->validationResult = [];
        $this->returnedZip = null;
        $this->step = 4;
        Notification::make()->title(__('admin.messages.preparation_ready'))->success()->send();
    }

    /** @param array<string, mixed> $settings */
    private function hydrateFromSettings(array $settings): void
    {
        $this->locales = array_values(array_map('strval', is_array($settings['locales'] ?? null) ? $settings['locales'] : []));
        $scope = is_array($settings['academic_scope'] ?? null) ? $settings['academic_scope'] : [];
        $this->trackReference = (string) ($scope['track_reference'] ?? '');
        $this->boardReference = (string) ($scope['board_reference'] ?? '');
        $this->syllabusVersion = (string) ($scope['syllabus_version'] ?? '');
        $this->yearLevel = (string) ($scope['year_level'] ?? '');
        $subjects = is_array($scope['subject_references'] ?? null) ? array_values(array_map('strval', $scope['subject_references'])) : [];
        $this->subjectReferences = implode("\n", $subjects);
        $this->subjectNames = implode("\n", array_map(fn (string $reference): string => $this->humanizeReference($reference), $subjects));
        $this->academicTrackId = $this->findTrackIdForScope($scope) ?? '';
        $this->contentTypes = array_values(array_map('strval', is_array($settings['content_types'] ?? null) ? $settings['content_types'] : []));
        $generation = is_array($settings['generation'] ?? null) ? $settings['generation'] : [];
        $this->includeAnswerExplanations = (bool) ($generation['include_answer_explanations'] ?? true);
        $this->maximumQuestionsPerQuiz = (int) ($generation['maximum_questions_per_quiz'] ?? 20);
    }

    private function validateStep(int $step): void
    {
        if ($step === 1) {
            $this->validate([
                'locales' => ['required', 'array', 'min:1'],
                'locales.*' => ['in:ar,en,fr'],
                'contentTypes' => ['required', 'array', 'min:1'],
                'contentTypes.*' => ['in:lesson,practice_quiz,mock_exam'],
            ]);

            return;
        }
        if ($step === 2) {
            if ($this->preparationRequestId === null) {
                $this->validate([
                    'academicTrackId' => ['required', 'string', 'exists:academic_tracks,id'],
                    'subjectNames' => ['required', 'string', 'max:4000'],
                ]);
                $this->assertSelectedTrackAllowed();
            } else {
                if ($this->academicTrackId !== '') {
                    $this->validate(['academicTrackId' => ['string', 'exists:academic_tracks,id']]);
                    $this->assertSelectedTrackAllowed();
                } elseif ($this->existingRequestScope() === null) {
                    throw ValidationException::withMessages([
                        'academicTrackId' => [$this->translate('Choose an approved academic track.', 'اختر مسارًا أكاديميًا معتمدًا.', 'Choisissez un parcours académique approuvé.')],
                    ]);
                }

                if (trim($this->subjectNames) === '' && trim($this->subjectReferences) === '') {
                    throw ValidationException::withMessages([
                        'subjectNames' => [__('admin.validation.subject_required')],
                    ]);
                }
            }

            if ($this->subjects() === []) {
                throw ValidationException::withMessages([
                    'subjectNames' => [__('admin.validation.subject_required')],
                ]);
            }

            return;
        }
        if ($step === 3) {
            $this->validate([
                'includeAnswerExplanations' => ['boolean'],
                'maximumQuestionsPerQuiz' => ['required', 'integer', 'min:1', 'max:200'],
            ]);
        }
    }

    private function validateAllSettings(): void
    {
        $this->validateStep(1);
        $this->validateStep(2);
        $this->validateStep(3);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $scope = $this->resolvedAcademicScope();
        $scope['subject_references'] = $this->subjects();

        return [
            'schema_version' => (string) config('modrik.content_import.schema_version', '1.0.0'),
            'settings' => [
                'locales' => array_values(array_unique($this->locales)),
                'academic_scope' => $scope,
                'content_types' => array_values(array_unique($this->contentTypes)),
                'generation' => [
                    'include_answer_explanations' => $this->includeAnswerExplanations,
                    'maximum_questions_per_quiz' => $this->maximumQuestionsPerQuiz,
                    'paid_ai_required' => false,
                ],
            ],
        ];
    }

    /** @return array{track_reference: string, board_reference: ?string, syllabus_version: ?string, year_level: string} */
    private function resolvedAcademicScope(): array
    {
        if ($this->academicTrackId !== '') {
            $track = $this->academicTrackRecord($this->academicTrackId);
            if ($track === null) {
                throw ValidationException::withMessages([
                    'academicTrackId' => [$this->translate('The selected track is no longer available. Choose it again.', 'المسار المختار لم يعد متاحًا. اختره مرة أخرى.', 'Le parcours sélectionné n’est plus disponible. Choisissez-le de nouveau.')],
                ]);
            }
            $this->assertTrackAllowed($track);

            return [
                'track_reference' => (string) $track->code,
                'board_reference' => is_string($track->board_reference) && $track->board_reference !== '' ? $track->board_reference : null,
                'syllabus_version' => is_string($track->syllabus_version) && $track->syllabus_version !== '' ? $track->syllabus_version : null,
                'year_level' => (string) $track->year_level,
            ];
        }

        $existing = $this->existingRequestScope();
        if ($existing !== null && is_string($existing['track_reference'] ?? null) && (string) ($existing['year_level'] ?? '') !== '') {
            return [
                'track_reference' => (string) $existing['track_reference'],
                'board_reference' => is_string($existing['board_reference'] ?? null) && $existing['board_reference'] !== '' ? $existing['board_reference'] : null,
                'syllabus_version' => is_string($existing['syllabus_version'] ?? null) && $existing['syllabus_version'] !== '' ? $existing['syllabus_version'] : null,
                'year_level' => (string) $existing['year_level'],
            ];
        }

        throw ValidationException::withMessages([
            'academicTrackId' => [$this->translate('Choose an approved academic track.', 'اختر مسارًا أكاديميًا معتمدًا.', 'Choisissez un parcours académique approuvé.')],
        ]);
    }

    /** @return list<string> */
    private function subjects(): array
    {
        if (trim($this->subjectNames) === '') {
            $existing = $this->existingRequestScope();
            $references = is_array($existing['subject_references'] ?? null) ? $existing['subject_references'] : [];
            $references = array_values(array_filter(array_map('strval', $references), static fn (string $value): bool => trim($value) !== ''));
            if ($references !== []) {
                return array_values(array_unique($references));
            }

            $legacy = preg_split('/\R/u', $this->subjectReferences) ?: [];
            $legacy = array_map(static fn (string $value): string => trim($value), $legacy);

            return array_values(array_unique(array_filter($legacy, static fn (string $value): bool => $value !== '')));
        }

        $names = preg_split('/\R/u', $this->subjectNames) ?: [];
        $names = array_values(array_unique(array_filter(
            array_map(static fn (string $value): string => trim($value), $names),
            static fn (string $value): bool => $value !== '',
        )));

        $known = [];
        $existing = $this->existingRequestScope();
        $existingReferences = is_array($existing['subject_references'] ?? null) ? $existing['subject_references'] : [];
        foreach ($existingReferences as $reference) {
            if (! is_string($reference) || trim($reference) === '') {
                continue;
            }
            $known[$this->normalizeHumanLabel($this->humanizeReference($reference))] = $reference;
        }

        $references = [];
        foreach ($names as $name) {
            $normalized = $this->normalizeHumanLabel($name);
            $references[] = $known[$normalized] ?? $this->internalSubjectReference($name);
        }

        return array_values(array_unique($references));
    }

    /** @return array<string, mixed>|null */
    private function existingRequestScope(): ?array
    {
        if ($this->preparationRequestId === null) {
            return null;
        }

        $settingsJson = DB::table('preparation_requests')->where('id', $this->preparationRequestId)->value('normalized_settings');
        if (! is_string($settingsJson) || $settingsJson === '') {
            return null;
        }
        $settings = json_decode($settingsJson, true);
        $scope = is_array($settings) && is_array($settings['academic_scope'] ?? null) ? $settings['academic_scope'] : null;

        return is_array($scope) ? $scope : null;
    }

    /** @param array<string, mixed> $scope */
    private function findTrackIdForScope(array $scope): ?string
    {
        $trackReference = (string) ($scope['track_reference'] ?? '');
        if ($trackReference === '') {
            return null;
        }

        $query = DB::table('academic_tracks')->where('code', $trackReference);
        $board = $scope['board_reference'] ?? null;
        $syllabus = $scope['syllabus_version'] ?? null;
        $query->where('board_reference', is_string($board) && $board !== '' ? $board : null);
        $query->where('syllabus_version', is_string($syllabus) && $syllabus !== '' ? $syllabus : null);
        $query->where('year_level', (string) ($scope['year_level'] ?? ''));
        $id = $query->value('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function academicTrackRecord(string $id): ?\stdClass
    {
        if ($id === '') {
            return null;
        }
        $track = DB::table('academic_tracks')->where('id', $id)->first();

        return $track instanceof \stdClass ? $track : null;
    }

    private function assertSelectedTrackAllowed(): void
    {
        $track = $this->academicTrackRecord($this->academicTrackId);
        if ($track === null) {
            throw ValidationException::withMessages([
                'academicTrackId' => [$this->translate('Choose an available academic track.', 'اختر مسارًا أكاديميًا متاحًا.', 'Choisissez un parcours académique disponible.')],
            ]);
        }
        $this->assertTrackAllowed($track);
    }

    private function assertTrackAllowed(\stdClass $track): void
    {
        if ((bool) $track->is_fixture && ! (bool) config('modrik.fixture.enabled')) {
            throw ValidationException::withMessages([
                'academicTrackId' => [$this->translate('Fixture tracks are unavailable in this environment.', 'المسارات الاختبارية غير متاحة في هذه البيئة.', 'Les parcours fixture ne sont pas disponibles dans cet environnement.')],
            ]);
        }
    }

    private function internalSubjectReference(string $name): string
    {
        $slug = Str::upper(Str::slug(Str::ascii($name), '-'));
        if ($slug === '') {
            $slug = 'SUBJECT';
        }
        $slug = mb_substr($slug, 0, 80);
        $hash = Str::upper(substr(hash('sha256', $this->normalizeHumanLabel($name)), 0, 8));

        return 'SUBJECT:'.$slug.':'.$hash;
    }

    private function normalizeHumanLabel(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function humanizeReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return $this->translate('Not specified', 'غير محدد', 'Non spécifié');
        }

        $segments = preg_split('/[:\/]+/', $reference) ?: [$reference];
        if (in_array(Str::upper((string) $segments[0]), ['BOARD', 'SYLLABUS', 'YEAR', 'TRACK', 'SUBJECT', 'FIXTURE'], true)) {
            array_shift($segments);
        }
        if (count($segments) > 1 && preg_match('/^[A-F0-9]{8}$/i', (string) end($segments)) === 1) {
            array_pop($segments);
        }
        $readable = trim(implode(' ', $segments));
        $readable = str_replace(['-', '_', '.'], ' ', $readable);

        return Str::headline($readable === '' ? $reference : $readable);
    }

    private function operator(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403);
        }
        app(ContentAdminWorkflowService::class)->assertOperator($user);

        return $user;
    }

    private function notifyProblem(ApiProblemException $exception): void
    {
        Notification::make()
            ->title(__('admin.messages.operation_blocked'))
            ->body($exception->problemCode.' — '.$exception->getMessage())
            ->danger()
            ->send();
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
