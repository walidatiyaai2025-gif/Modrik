<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ContentReviewQueue extends Page
{
    protected string $view = 'filament.pages.content-review-queue';

    protected static ?string $slug = 'content-review';

    public string $statusFilter = 'all';

    /** @var array<string, string> */
    public array $reasons = [];

    public ?string $selectedImportId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.review.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.review.title');
    }

    public function getSubheading(): string
    {
        return __('admin.review.subtitle');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function runDryRun(string $importId): void
    {
        $this->perform(function (ContentAdminWorkflowService $workflow) use ($importId): void {
            $summary = $workflow->dryRun($this->operator(), $importId);
            if ($summary['publishable'] === true) {
                Notification::make()->title(__('admin.messages.dry_run_ready'))->success()->send();
            } else {
                Notification::make()->title(__('admin.messages.dry_run_blocked'))->warning()->send();
            }
            $this->selectedImportId = $importId;
        });
    }

    public function approve(string $importId): void
    {
        $this->review($importId, 'approved');
    }

    public function reject(string $importId): void
    {
        $this->review($importId, 'rejected');
    }

    public function requestFix(string $importId): void
    {
        $this->review($importId, 'request_fix');
    }

    public function importReviewed(string $importId): void
    {
        $this->perform(function (ContentAdminWorkflowService $workflow) use ($importId): void {
            $result = $workflow->importReviewed($this->operator(), $importId);
            Notification::make()
                ->title($result['replayed'] === true ? __('admin.messages.operation_replayed') : __('admin.messages.canonical_import_ready'))
                ->success()
                ->send();
            $this->selectedImportId = $importId;
        });
    }

    public function publishOfficial(string $importId): void
    {
        $this->perform(function (ContentAdminWorkflowService $workflow) use ($importId): void {
            $result = $workflow->publish($this->operator(), $importId);
            Notification::make()
                ->title($result['replayed'] === true ? __('admin.messages.operation_replayed') : __('admin.messages.published'))
                ->success()
                ->send();
            $this->selectedImportId = $importId;
        });
    }

    public function retry(string $importId): void
    {
        $row = DB::table('preparation_imports')->where('id', $importId)->first();
        if ($row === null) {
            Notification::make()->title(__('admin.messages.import_missing'))->danger()->send();

            return;
        }
        if ((string) $row->status === 'reviewed' && (string) $row->review_decision === 'approved') {
            $this->importReviewed($importId);

            return;
        }
        if ((string) $row->status === 'imported') {
            $this->publishOfficial($importId);

            return;
        }
        Notification::make()->title(__('admin.messages.retry_not_available'))->warning()->send();
    }

    public function selectImport(string $importId): void
    {
        $this->selectedImportId = $this->selectedImportId === $importId ? null : $importId;
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
    public function queueRows(): array
    {
        $allowed = ['validating', 'rejected', 'staged', 'validated', 'reviewed', 'imported', 'published', 'superseded'];
        $query = DB::table('preparation_imports as i')
            ->leftJoin('preparation_requests as r', 'r.id', '=', 'i.preparation_request_id')
            ->leftJoin('content_publications as p', 'p.preparation_import_id', '=', 'i.id')
            ->select([
                'i.id',
                'i.preparation_request_id',
                'i.pack_id',
                'i.rights_status',
                'i.status',
                'i.validation_summary',
                'i.dry_run_summary',
                'i.review_decision',
                'i.review_reason',
                'i.reviewed_at',
                'i.published_at',
                'i.operation_state',
                'i.operation_checkpoint',
                'i.operation_attempts',
                'i.last_error_code',
                'i.last_error_at',
                'i.created_at',
                'r.settings_hash',
                'r.schema_version',
                'r.status as request_status',
                'r.superseded_by_request_id',
                'p.id as publication_id',
                'p.status as publication_status',
                'p.attempt_count as publication_attempt_count',
            ])
            ->orderByDesc('i.created_at')
            ->limit(100);
        if (in_array($this->statusFilter, $allowed, true)) {
            $query->where('i.status', $this->statusFilter);
        }

        return $query->get()->map(function (object $row): array {
            return [
                'id' => (string) $row->id,
                'preparation_request_id' => is_string($row->preparation_request_id) ? $row->preparation_request_id : null,
                'pack_id' => is_string($row->pack_id) ? $row->pack_id : null,
                'rights_status' => is_string($row->rights_status) ? $row->rights_status : null,
                'status' => (string) $row->status,
                'validation_summary' => $this->decode($row->validation_summary),
                'dry_run_summary' => $this->decode($row->dry_run_summary),
                'review_decision' => is_string($row->review_decision) ? $row->review_decision : null,
                'review_reason' => is_string($row->review_reason) ? $row->review_reason : null,
                'reviewed_at' => $row->reviewed_at,
                'published_at' => $row->published_at,
                'operation_state' => (string) $row->operation_state,
                'operation_checkpoint' => is_string($row->operation_checkpoint) ? $row->operation_checkpoint : null,
                'operation_attempts' => (int) $row->operation_attempts,
                'last_error_code' => is_string($row->last_error_code) ? $row->last_error_code : null,
                'last_error_at' => $row->last_error_at,
                'created_at' => $row->created_at,
                'settings_hash' => is_string($row->settings_hash) ? $row->settings_hash : null,
                'schema_version' => is_string($row->schema_version) ? $row->schema_version : null,
                'request_status' => is_string($row->request_status) ? $row->request_status : null,
                'superseded_by_request_id' => is_string($row->superseded_by_request_id) ? $row->superseded_by_request_id : null,
                'publication_id' => is_string($row->publication_id) ? $row->publication_id : null,
                'publication_status' => is_string($row->publication_status) ? $row->publication_status : null,
                'publication_attempt_count' => (int) ($row->publication_attempt_count ?? 0),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function auditRows(string $importId): array
    {
        return DB::table('content_workflow_audits')
            ->where('preparation_import_id', $importId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'actor_id' => is_string($row->actor_id) ? $row->actor_id : null,
                'action' => (string) $row->action,
                'from_status' => is_string($row->from_status) ? $row->from_status : null,
                'to_status' => is_string($row->to_status) ? $row->to_status : null,
                'reason' => is_string($row->reason) ? $row->reason : null,
                'metadata' => $this->decode($row->metadata),
                'created_at' => $row->created_at,
            ])
            ->values()
            ->all();
    }

    public function preparationUrl(?string $requestId): string
    {
        if ($requestId === null || $requestId === '') {
            return ContentPreparationWizard::getUrl();
        }

        return ContentPreparationWizard::getUrl(['request' => $requestId]);
    }

    private function review(string $importId, string $decision): void
    {
        $this->perform(function (ContentAdminWorkflowService $workflow) use ($importId, $decision): void {
            $reason = $this->reasons[$importId] ?? null;
            $workflow->review($this->operator(), $importId, $decision, $reason);
            Notification::make()->title(__('admin.messages.review_saved'))->success()->send();
            $this->selectedImportId = $importId;
        });
    }

    /** @param callable(ContentAdminWorkflowService): void $callback */
    private function perform(callable $callback): void
    {
        try {
            $callback(app(ContentAdminWorkflowService::class));
        } catch (ApiProblemException $exception) {
            Notification::make()
                ->title(__('admin.messages.operation_blocked'))
                ->body($exception->problemCode.' — '.$exception->getMessage())
                ->danger()
                ->send();
        } catch (Throwable) {
            Notification::make()->title(__('admin.messages.operation_failed'))->danger()->send();
        }
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

    /** @return array<string, mixed>|null */
    private function decode(mixed $json): ?array
    {
        if (! is_string($json) || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
