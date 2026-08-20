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
        $subjects = is_array($scope['subject_references'] ?? null) ? $scope['subject_references'] : [];
        $this->subjectReferences = implode("\n", array_map('strval', $subjects));
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
            $this->validate([
                'trackReference' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
                'boardReference' => ['nullable', 'string', 'max:160'],
                'syllabusVersion' => ['nullable', 'string', 'max:120'],
                'yearLevel' => ['required', 'string', 'max:40'],
                'subjectReferences' => ['required', 'string', 'max:4000'],
            ]);

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
        $subjects = $this->subjects();
        if ($subjects === []) {
            $this->addError('subjectReferences', __('admin.validation.subject_required'));
            throw ValidationException::withMessages([
                'subjectReferences' => [__('admin.validation.subject_required')],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'schema_version' => (string) config('modrik.content_import.schema_version', '1.0.0'),
            'settings' => [
                'locales' => array_values(array_unique($this->locales)),
                'academic_scope' => [
                    'track_reference' => trim($this->trackReference),
                    'board_reference' => trim($this->boardReference) === '' ? null : trim($this->boardReference),
                    'syllabus_version' => trim($this->syllabusVersion) === '' ? null : trim($this->syllabusVersion),
                    'year_level' => trim($this->yearLevel),
                    'subject_references' => $this->subjects(),
                ],
                'content_types' => array_values(array_unique($this->contentTypes)),
                'generation' => [
                    'include_answer_explanations' => $this->includeAnswerExplanations,
                    'maximum_questions_per_quiz' => $this->maximumQuestionsPerQuiz,
                    'paid_ai_required' => false,
                ],
            ],
        ];
    }

    /** @return list<string> */
    private function subjects(): array
    {
        $lines = preg_split('/\R/u', $this->subjectReferences) ?: [];
        $lines = array_map(static fn (string $value): string => trim($value), $lines);

        return array_values(array_unique(array_filter($lines, static fn (string $value): bool => $value !== '')));
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
}
