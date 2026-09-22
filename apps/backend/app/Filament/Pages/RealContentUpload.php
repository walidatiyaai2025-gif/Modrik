<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

final class RealContentUpload extends Page
{
    protected string $view = 'filament.pages.real-content-upload';

    protected static ?string $slug = 'real-content-upload';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public string $academicTrackId = '';

    public string $subjectLabel = '';

    /** @var list<string> */
    public array $locales = ['ar', 'en'];

    /** @var list<string> */
    public array $contentTypes = ['lesson', 'practice_quiz'];

    public int $maximumQuestionsPerQuiz = 20;

    /** @var array<string, mixed> */
    public array $lastResult = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && in_array((string) $user->role, ['admin', 'content_team'], true)
            && (string) $user->account_status === 'active'
            && $user->deleted_at === null;
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'إعداد المحتوى عبر ChatGPT',
            'fr' => 'Préparation via ChatGPT',
            default => 'Prepare with ChatGPT',
        };
    }

    public static function getNavigationSort(): int
    {
        return 8;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->text(
            'MODRIK لا يحتوي AI. جهّز حزمة مرتبطة بالصف والمادة، ثم ارفع الحزمة والكتاب هنا في ChatGPT العادي. بعد أن يرجع ChatGPT الـJSON/Content Pack، ارفعه إلى MODRIK للمراجعة والنشر.',
            'MODRIK contains no AI. Create a package bound to the year and subject, then upload that package and the book to normal ChatGPT. When ChatGPT returns the JSON/Content Pack, bring it back to MODRIK for review and publication.',
            'MODRIK ne contient aucune IA. Créez un paquet lié à l’année et à la matière, utilisez ChatGPT manuellement, puis renvoyez le JSON/Content Pack à MODRIK pour révision et publication.',
        );
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string, string> */
    public function academicTrackOptions(): array
    {
        $options = [];

        foreach (DB::table('academic_tracks')
            ->where('is_fixture', false)
            ->orderBy('year_level')
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get(['id', 'title', 'year_level', 'board_reference', 'syllabus_version']) as $track) {
            $titles = json_decode((string) $track->title, true);
            $titles = is_array($titles) ? $titles : [];
            $name = (string) ($titles[App::getLocale()] ?? $titles['en'] ?? $titles['ar'] ?? 'Academic track');
            $parts = array_values(array_filter([
                $this->readableReference((string) $track->year_level),
                $this->readableReference((string) ($track->board_reference ?? '')),
                $this->readableReference((string) ($track->syllabus_version ?? '')),
            ]));

            $options[(string) $track->id] = $name.($parts === [] ? '' : ' — '.implode(' · ', $parts));
        }

        return $options;
    }

    /** @return list<array<string, mixed>> */
    public function recentRequests(): array
    {
        return array_values(DB::table('preparation_requests')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'normalized_settings', 'status', 'created_at'])
            ->map(function (object $row): array {
                $settings = json_decode((string) $row->normalized_settings, true);
                $scope = is_array($settings) && is_array($settings['academic_scope'] ?? null)
                    ? $settings['academic_scope']
                    : [];
                $subjects = is_array($scope['subject_references'] ?? null)
                    ? array_values(array_filter($scope['subject_references'], 'is_string'))
                    : [];

                return [
                    'id' => (string) $row->id,
                    'status' => (string) $row->status,
                    'year_level' => (string) ($scope['year_level'] ?? ''),
                    'track_reference' => (string) ($scope['track_reference'] ?? ''),
                    'subjects' => $subjects,
                    'created_at' => (string) $row->created_at,
                ];
            })
            ->all());
    }

    public function createPreparation(): void
    {
        $this->validate([
            'academicTrackId' => ['required', 'string', 'exists:academic_tracks,id'],
            'subjectLabel' => ['required', 'string', 'min:2', 'max:255'],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['in:ar,en,fr'],
            'contentTypes' => ['required', 'array', 'min:1'],
            'contentTypes.*' => ['in:lesson,practice_quiz,mock_exam'],
            'maximumQuestionsPerQuiz' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $track = DB::table('academic_tracks')
            ->where('id', $this->academicTrackId)
            ->where('is_fixture', false)
            ->first(['id', 'code', 'board_reference', 'syllabus_version', 'year_level']);

        if (($track instanceof \stdClass) === false) {
            $this->addError('academicTrackId', $this->text('اختر مسارًا حقيقيًا متاحًا.', 'Choose an available real academic track.', 'Choisissez un parcours académique réel disponible.'));

            return;
        }

        $subjectReference = $this->subjectReference($this->subjectLabel, (string) $track->code);

        try {
            $this->lastResult = app(ContentAdminWorkflowService::class)->createRequest($this->operator(), [
                'schema_version' => (string) config('modrik.content_import.schema_version', '1.0.0'),
                'settings' => [
                    'locales' => array_values(array_unique($this->locales)),
                    'academic_scope' => [
                        'track_reference' => (string) $track->code,
                        'board_reference' => is_string($track->board_reference) && $track->board_reference !== '' ? $track->board_reference : null,
                        'syllabus_version' => is_string($track->syllabus_version) && $track->syllabus_version !== '' ? $track->syllabus_version : null,
                        'year_level' => (string) $track->year_level,
                        'subject_references' => [$subjectReference],
                    ],
                    'content_types' => array_values(array_unique($this->contentTypes)),
                    'generation' => [
                        'include_answer_explanations' => true,
                        'maximum_questions_per_quiz' => $this->maximumQuestionsPerQuiz,
                        'paid_ai_required' => false,
                    ],
                ],
            ]);

            Notification::make()
                ->success()
                ->title($this->text('حزمة ChatGPT جاهزة', 'ChatGPT package is ready', 'Le paquet ChatGPT est prêt'))
                ->body($this->text(
                    'حمّل الحزمة وارفعها مع الكتاب هنا في ChatGPT. MODRIK لم يقرأ أو يقسم أو يولد أي محتوى.',
                    'Download the package and upload it with the book to normal ChatGPT. MODRIK did not read, split or generate any content.',
                    'Téléchargez le paquet et fournissez-le avec le livre à ChatGPT. MODRIK n’a généré aucun contenu.',
                ))
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title($this->text('تعذر إنشاء حزمة الإعداد', 'Preparation package failed', 'Échec de la préparation'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function downloadPreparationPackage(): ?StreamedResponse
    {
        $requestId = (string) ($this->lastResult['preparation_request_id'] ?? '');
        if ($requestId === '') {
            return null;
        }

        $details = app(ContentAdminWorkflowService::class)->requestDetails($this->operator(), $requestId);
        $package = [
            'workflow' => 'manual_chatgpt_offline_content_preparation',
            'runtime_ai_required' => false,
            'instructions' => [
                'Upload this file and the original educational source to normal ChatGPT.',
                'ChatGPT must use only the supplied source as curriculum authority.',
                'ChatGPT should return structured MODRIK JSON and a Content Pack ZIP bound to this request.',
                'Do not invent source pages, curriculum facts, rights or student data.',
            ],
            'preparation_request_id' => $details['preparation_request_id'],
            'schema_version' => $details['schema_version'],
            'settings_hash' => $details['settings_hash'],
            'prompt' => $details['prompt'],
            'bundle' => $details['bundle'],
        ];

        $json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return response()->streamDownload(
            static function () use ($json): void {
                echo $json;
            },
            'modrik-chatgpt-preparation-'.$requestId.'.json',
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    private function operator(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && self::canAccess(), 403);

        return $user;
    }

    private function subjectReference(string $subjectLabel, string $trackCode): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $subjectLabel) ?? $subjectLabel));
        $slug = Str::upper(Str::slug(Str::ascii($subjectLabel), '-'));
        if ($slug === '') {
            $slug = 'SUBJECT';
        }

        return 'SUBJECT:'.mb_substr($slug, 0, 72).':'.Str::upper(substr(hash('sha256', $normalized.'|'.$trackCode), 0, 8));
    }

    private function readableReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return '';
        }

        $parts = preg_split('/[:\\/]+/', $reference) ?: [$reference];
        if (count($parts) > 1 && preg_match('/^[A-F0-9]{8}$/i', (string) end($parts)) === 1) {
            array_pop($parts);
        }
        if ($parts !== [] && in_array(strtoupper((string) $parts[0]), ['YEAR', 'BOARD', 'SYLLABUS', 'TRACK'], true)) {
            array_shift($parts);
        }

        return str_replace(['-', '_', '.'], ' ', implode(' ', $parts));
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
