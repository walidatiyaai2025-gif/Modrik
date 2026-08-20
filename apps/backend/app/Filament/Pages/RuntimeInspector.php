<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Observability\DiagnosticRecorder;
use App\Support\Observability\RuntimeInspectorService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RuntimeInspector extends Page
{
    protected string $view = 'filament.pages.runtime-inspector';

    protected static ?string $slug = 'runtime-inspector';

    public string $correlationId = '';

    public string $severity = 'all';

    public string $surface = 'all';

    public string $dataClass = 'all';

    public string $stableCode = '';

    public int $hours = 24;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) config('observability.inspector_enabled', false)
            && $user instanceof User
            && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return 'Runtime Inspector';
    }

    public function getTitle(): string
    {
        return 'Runtime Inspector';
    }

    public function getSubheading(): string
    {
        return 'Sanitized application diagnostics, durable diagnostic audit, and outbox state.';
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $inspector = app(RuntimeInspectorService::class);

        return [
            'available' => $inspector->isAvailable(),
            'summary' => $inspector->runtimeSummary(),
            'events' => $inspector->events($this->filters()),
        ];
    }

    public function downloadDiagnosticBundle(): StreamedResponse
    {
        $inspector = app(RuntimeInspectorService::class);
        $bundle = $inspector->exportBundle($this->filters());
        $user = auth()->user();

        app(DiagnosticRecorder::class)->audit(
            'diagnostic_export',
            $user instanceof User ? (string) $user->getAuthIdentifier() : null,
            [
                'event_count' => $bundle['event_count'],
                'filter_correlation_id' => $this->correlationId !== '' ? $this->correlationId : null,
            ],
            stableCode: 'DIAGNOSTIC_EXPORT',
        );

        return response()->streamDownload(
            static function () use ($bundle): void {
                echo $bundle['json'];
            },
            'modrik-runtime-diagnostics.json',
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return [
            'correlation_id' => trim($this->correlationId),
            'severity' => $this->severity === 'all' ? '' : $this->severity,
            'surface' => $this->surface === 'all' ? '' : $this->surface,
            'data_class' => $this->dataClass === 'all' ? '' : $this->dataClass,
            'stable_code' => trim($this->stableCode),
            'hours' => max(1, min(168, $this->hours)),
        ];
    }
}
