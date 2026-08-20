<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\RuntimeDiagnostics;
use App\Support\CorrelationId;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RuntimeInspector extends Page
{
    protected string $view = 'filament.pages.runtime-inspector';

    protected static ?string $slug = 'runtime-inspector';

    public string $correlationId = '';

    public string $severity = 'all';

    public string $surface = 'all';

    public string $stableCode = '';

    public string $eventClass = 'all';

    public int $windowMinutes = 60;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) config('modrik.observability.inspector_enabled', true)
            && $user instanceof User
            && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return __('observability.navigation');
    }

    public function getTitle(): string
    {
        return __('observability.title');
    }

    public function getSubheading(): string
    {
        return __('observability.subtitle');
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

    public function resetFilters(): void
    {
        $this->correlationId = '';
        $this->severity = 'all';
        $this->surface = 'all';
        $this->stableCode = '';
        $this->eventClass = 'all';
        $this->windowMinutes = 60;
    }

    /** @return list<array<string, mixed>> */
    public function events(): array
    {
        return app(RuntimeDiagnostics::class)->events($this->filters());
    }

    /** @return array<string, bool|int|string|null> */
    public function runtimeSummary(): array
    {
        return app(RuntimeDiagnostics::class)->runtimeSummary();
    }

    /** @return array<string, mixed> */
    public function outboxSummary(): array
    {
        return app(RuntimeDiagnostics::class)->outboxSummary();
    }

    public function exportDiagnostics(): StreamedResponse
    {
        $operator = auth()->user();
        $json = app(RuntimeDiagnostics::class)->exportJson(
            $this->filters(),
            CorrelationId::assign(request()),
            $operator instanceof User ? $operator : null,
        );
        $filename = 'modrik-diagnostics-'.now()->utc()->format('Ymd-His').'.json';

        return response()->streamDownload(
            static function () use ($json): void {
                echo $json;
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    /**
     * @return array{correlation_id?: string, severity?: string, surface?: string, stable_code?: string, event_class?: string, window_minutes?: int}
     */
    private function filters(): array
    {
        return [
            'correlation_id' => trim($this->correlationId),
            'severity' => $this->severity === 'all' ? '' : $this->severity,
            'surface' => $this->surface === 'all' ? '' : $this->surface,
            'stable_code' => strtoupper(trim($this->stableCode)),
            'event_class' => $this->eventClass === 'all' ? '' : $this->eventClass,
            'window_minutes' => max(5, min($this->windowMinutes, 10_080)),
        ];
    }
}
