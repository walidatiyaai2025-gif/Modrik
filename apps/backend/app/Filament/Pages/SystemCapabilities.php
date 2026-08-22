<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;

final class SystemCapabilities extends Page
{
    protected string $view = 'filament.pages.system-capabilities';

    protected static ?string $slug = 'system-capabilities';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'وظائف النظام',
            'fr' => 'Fonctions du système',
            default => 'System Capabilities',
        };
    }

    public static function getNavigationSort(): ?int
    {
        return 90;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'فهرس واضح للوظائف المفعلة ومكان تشغيلها، مع تمييز الخدمات الخلفية والسياسات المقصود ألا يكون لها زر يدوي.',
            'fr' => 'Index des fonctions actives et de leur surface, en distinguant les services de fond et les politiques qui ne doivent pas avoir de contrôle manuel.',
            default => 'A clear index of implemented capabilities and where to use them, including background and policy services that intentionally have no manual control.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en', 'fr'], true)) {
            return;
        }

        session()->put('admin_locale', $locale);
        App::setLocale($locale);
    }

    /** @return array<int, array<string, mixed>> */
    public function capabilities(): array
    {
        $inspectorEnabled = (bool) config('observability.inspector_enabled', false);
        $studentUrl = $this->studentWorkspaceUrl();

        return [
            [
                'module' => 'content_preparation',
                'mode' => 'interactive',
                'url' => ContentPreparationWizard::getUrl(),
            ],
            [
                'module' => 'preparation_history',
                'mode' => 'interactive',
                'url' => ContentPreparationRequests::getUrl(),
            ],
            [
                'module' => 'content_rights',
                'mode' => 'interactive',
                'url' => ContentRightsReview::getUrl(),
            ],
            [
                'module' => 'content_review_publish',
                'mode' => 'interactive',
                'url' => ContentReviewQueue::getUrl(),
            ],
            [
                'module' => 'student_auth_account',
                'mode' => 'student_surface',
                'url' => $studentUrl,
            ],
            [
                'module' => 'academic_context',
                'mode' => 'student_surface',
                'url' => $studentUrl,
            ],
            [
                'module' => 'study_lessons',
                'mode' => 'student_surface',
                'url' => $studentUrl,
            ],
            [
                'module' => 'assessment_practice',
                'mode' => 'student_surface',
                'url' => $studentUrl,
            ],
            [
                'module' => 'progress',
                'mode' => 'student_surface',
                'url' => $studentUrl,
            ],
            [
                'module' => 'offline_sync',
                'mode' => 'background',
                'url' => null,
            ],
            [
                'module' => 'advertising_policy',
                'mode' => 'policy',
                'url' => null,
            ],
            [
                'module' => 'outbox_idempotency',
                'mode' => 'internal',
                'url' => null,
            ],
            [
                'module' => 'runtime_inspector',
                'mode' => $inspectorEnabled ? 'interactive' : 'gated',
                'url' => $inspectorEnabled ? RuntimeInspector::getUrl() : null,
            ],
        ];
    }

    private function studentWorkspaceUrl(): ?string
    {
        $host = request()->getHost();
        if (! str_starts_with($host, 'api.')) {
            return null;
        }

        $webHost = substr($host, 4);
        if ($webHost === '') {
            return null;
        }

        return request()->getScheme().'://'.$webHost.'/student';
    }
}
