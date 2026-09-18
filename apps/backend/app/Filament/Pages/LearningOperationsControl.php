<?php

namespace App\Filament\Pages;

use App\Exceptions\LearningOperationBlocked;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\LearningOperationsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use Throwable;
use UnitEnum;

final class LearningOperationsControl extends Page
{
    protected string $view = 'filament.pages.learning-operations-control';

    protected static ?string $slug = 'learning-operations';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Operations;

    /** @var array<string, string> */
    public array $featureStates = [];

    /** @var array<string, int> */
    public array $featureVersions = [];

    /** @var array<string, string> */
    public array $featureReasons = [];

    /** @var array<string, int> */
    public array $jobVersions = [];

    /** @var array<string, string> */
    public array $jobReasons = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && (string) $user->role === 'admin'
            && (string) $user->account_status === 'active'
            && $user->deleted_at === null;
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'تشغيل التعلم',
            'fr' => 'Opérations apprentissage',
            default => 'Learning Operations',
        };
    }

    public static function getNavigationSort(): int
    {
        return 7;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->text(
            'مفاتيح تشغيل ونطاقات ووظائف تعلم خاضعة للتدقيق، مع إيقاف آمن للوظائف التي لم تكتمل تبعياتها.',
            'Governed learning feature states, rollout scopes and jobs with audit evidence and fail-closed dependency gates.',
            'États de fonctionnalités, périmètres et tâches d’apprentissage gouvernés avec audit et dépendances bloquées par défaut.',
        );
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->reload();
    }

    /** @return array<string, array<string, mixed>> */
    public function features(): array
    {
        return app(LearningOperationsService::class)->features();
    }

    /** @return array<string, array<string, mixed>> */
    public function jobs(): array
    {
        return app(LearningOperationsService::class)->jobs();
    }

    public function saveFeature(string $featureKey): void
    {
        $user = $this->admin();
        try {
            app(LearningOperationsService::class)->updateFeature(
                $featureKey,
                (string) ($this->featureStates[$featureKey] ?? 'disabled'),
                null,
                (int) ($this->featureVersions[$featureKey] ?? 0),
                (string) ($this->featureReasons[$featureKey] ?? ''),
                (string) $user->id,
            );
            $this->featureReasons[$featureKey] = '';
            $this->reload();
            Notification::make()->title($this->text('تم حفظ حالة الميزة', 'Feature state saved', 'État enregistré'))->success()->send();
        } catch (InvalidArgumentException $exception) {
            $this->addError('featureReasons.'.$featureKey, $exception->getMessage());
        }
    }

    public function pauseJob(string $jobKey): void
    {
        $this->setPaused($jobKey, true);
    }

    public function resumeJob(string $jobKey): void
    {
        $this->setPaused($jobKey, false);
    }

    public function runJobNow(string $jobKey): void
    {
        $user = $this->admin();
        try {
            app(LearningOperationsService::class)->runNow($jobKey, (string) $user->id);
            $this->reload();
            Notification::make()->title($this->text('اكتمل التشغيل', 'Job completed', 'Tâche terminée'))->success()->send();
        } catch (LearningOperationBlocked $exception) {
            $this->addError('jobs.'.$jobKey, $exception->getMessage());
        } catch (Throwable) {
            $this->addError('jobs.'.$jobKey, $this->text('فشل التشغيل بأمان.', 'The job failed safely.', 'La tâche a échoué en sécurité.'));
        }
    }

    /** @return list<array<string, mixed>> */
    public function jobHistory(string $jobKey): array
    {
        return app(LearningOperationsService::class)->jobHistory($jobKey, 10);
    }

    private function setPaused(string $jobKey, bool $paused): void
    {
        $user = $this->admin();
        try {
            app(LearningOperationsService::class)->setJobPaused(
                $jobKey,
                $paused,
                (int) ($this->jobVersions[$jobKey] ?? 0),
                (string) ($this->jobReasons[$jobKey] ?? ''),
                (string) $user->id,
            );
            $this->jobReasons[$jobKey] = '';
            $this->reload();
        } catch (InvalidArgumentException $exception) {
            $this->addError('jobReasons.'.$jobKey, $exception->getMessage());
        }
    }

    private function reload(): void
    {
        foreach (app(LearningOperationsService::class)->features() as $key => $feature) {
            $this->featureStates[$key] = (string) $feature['state'];
            $this->featureVersions[$key] = (int) $feature['version'];
            $this->featureReasons[$key] ??= '';
        }
        foreach (app(LearningOperationsService::class)->jobs() as $key => $job) {
            $this->jobVersions[$key] = (int) $job['version'];
            $this->jobReasons[$key] ??= '';
        }
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && self::canAccess(), 403);

        return $user;
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
