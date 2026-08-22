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
use Throwable;
use UnitEnum;

final class ContentIngestionOperations extends Page
{
    protected string $view = 'filament.pages.content-ingestion-operations';

    protected static ?string $slug = 'content-ingestion-operations';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'الاستيعاب والمعالجة',
            'fr' => 'Ingestion et traitement',
            default => 'Ingestion & Processing',
        };
    }

    public static function getNavigationSort(): int
    {
        return 30;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function uploadSurfaceUrl(): string
    {
        return ContentPreparationRequests::getUrl();
    }

    /** @return array<string, int> */
    public function metrics(): array
    {
        return [
            'total' => DB::table('preparation_imports')->count(),
            'processing' => DB::table('preparation_imports')->whereIn('operation_state', ['processing', 'retrying'])->count(),
            'blocked' => DB::table('preparation_imports')->where('operation_state', 'blocked')->count(),
            'failed' => DB::table('preparation_imports')->where('operation_state', 'failed')->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function imports(): array
    {
        return DB::table('preparation_imports')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'preparation_request_id', 'status', 'operation_state', 'operation_checkpoint', 'operation_attempts', 'last_error_code', 'last_error_at', 'updated_at'])
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    public function retryDryRun(string $importId, ContentAdminWorkflowService $workflow): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! self::canAccess()) {
            abort(403);
        }

        $import = DB::table('preparation_imports')->where('id', $importId)->first();
        if (! $import instanceof \stdClass || ! in_array((string) $import->status, ['staged', 'validated', 'reviewed'], true)) {
            Notification::make()->title($this->text('تعذر إعادة المحاولة', 'Retry unavailable', 'Relance indisponible'))->danger()->send();

            return;
        }

        try {
            $workflow->dryRun($user, $importId);
            Notification::make()->title($this->text('اكتملت إعادة المحاولة', 'Processing retried', 'Traitement relancé'))->success()->send();
        } catch (Throwable) {
            Notification::make()->title($this->text('فشلت إعادة المحاولة', 'Retry failed', 'Échec de la relance'))->danger()->send();
        }
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
