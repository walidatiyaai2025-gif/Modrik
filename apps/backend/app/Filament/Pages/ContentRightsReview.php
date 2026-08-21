<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\ContentRightsReviewService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ContentRightsReview extends Page
{
    protected string $view = 'filament.pages.content-rights-review';

    protected static ?string $slug = 'content-rights-review';

    /** @var array<string, string> */
    public array $rightsBases = [];

    /** @var array<string, string> */
    public array $evidenceReferences = [];

    /** @var array<string, string> */
    public array $notes = [];

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
            'ar' => 'مراجعة حقوق المحتوى',
            'fr' => 'Droits du contenu',
            default => 'Content Rights',
        };
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DB::table('preparation_imports')->where('status', 'rights_review')->count();

        return $count > 0 ? (string) $count : null;
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'لا ينتقل المحتوى الحقيقي إلى التجربة الجافة أو النشر قبل مراجعة دليل الحقوق واعتماده صراحةً.',
            'fr' => 'Le contenu réel reste bloqué avant le dry-run et la publication jusqu’à approbation explicite des droits.',
            default => 'Real content remains blocked before dry-run and publication until rights evidence is explicitly approved.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        return DB::table('preparation_imports as i')
            ->leftJoin('preparation_requests as r', 'r.id', '=', 'i.preparation_request_id')
            ->where('i.status', 'rights_review')
            ->select([
                'i.id',
                'i.preparation_request_id',
                'i.pack_id',
                'i.rights_status',
                'i.rights_basis',
                'i.rights_review_status',
                'i.rights_evidence_reference',
                'i.rights_review_note',
                'i.rights_reviewed_at',
                'i.created_at',
                'r.schema_version',
                'r.settings_hash',
            ])
            ->orderByDesc('i.created_at')
            ->limit(100)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'preparation_request_id' => is_string($row->preparation_request_id) ? $row->preparation_request_id : null,
                'pack_id' => is_string($row->pack_id) ? $row->pack_id : null,
                'rights_status' => is_string($row->rights_status) ? $row->rights_status : null,
                'rights_basis' => is_string($row->rights_basis) ? $row->rights_basis : null,
                'rights_source_references' => $this->sourceReferencesForImport((string) $row->id),
                'rights_review_status' => is_string($row->rights_review_status) ? $row->rights_review_status : 'pending',
                'rights_evidence_reference' => is_string($row->rights_evidence_reference) ? $row->rights_evidence_reference : null,
                'rights_review_note' => is_string($row->rights_review_note) ? $row->rights_review_note : null,
                'rights_reviewed_at' => $row->rights_reviewed_at,
                'created_at' => $row->created_at,
                'schema_version' => is_string($row->schema_version) ? $row->schema_version : null,
                'settings_hash' => is_string($row->settings_hash) ? $row->settings_hash : null,
            ])
            ->values()
            ->all();
    }

    public function approve(string $importId): void
    {
        $this->review($importId, 'approved');
    }

    public function reject(string $importId): void
    {
        $this->review($importId, 'rejected');
    }

    public function setLocale(string $locale): void
    {
        if (in_array($locale, ['ar', 'en', 'fr'], true) === false) {
            return;
        }
        session()->put('admin_locale', $locale);
        App::setLocale($locale);
    }

    private function review(string $importId, string $decision): void
    {
        try {
            app(ContentRightsReviewService::class)->review(
                $this->operator(),
                $importId,
                $decision,
                $this->evidenceReferences[$importId] ?? null,
                $this->notes[$importId] ?? null,
                $this->rightsBases[$importId] ?? null,
            );

            $notification = Notification::make()->title($decision === 'approved'
                ? $this->text('تم اعتماد مراجعة الحقوق.', 'Rights review approved.', 'Revue des droits approuvée.')
                : $this->text('تم رفض مراجعة الحقوق.', 'Rights review rejected.', 'Revue des droits rejetée.'));
            if ($decision === 'approved') {
                $notification->success();
            } else {
                $notification->warning();
            }
            $notification->send();
        } catch (ApiProblemException $exception) {
            Notification::make()
                ->title($this->text('تعذر حفظ قرار الحقوق.', 'Rights decision blocked.', 'Décision de droits bloquée.'))
                ->body($exception->problemCode.' — '.$exception->getMessage())
                ->danger()
                ->send();
        } catch (Throwable) {
            Notification::make()->title($this->text('فشلت العملية.', 'Operation failed.', 'Échec de l’opération.'))->danger()->send();
        }
    }

    private function operator(): User
    {
        $user = auth()->user();
        if ($user instanceof User) {
            return $user;
        }

        abort(403);
    }

    /** @return list<string> */
    private function sourceReferencesForImport(string $importId): array
    {
        $payload = DB::table('outbox_events')
            ->where('aggregate_type', 'preparation_import')
            ->where('aggregate_id', $importId)
            ->where('event_type', 'content.rights_review_required')
            ->orderBy('occurred_at')
            ->value('payload');
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            $references = is_array($decoded) ? ($decoded['source_references'] ?? null) : null;
            if (is_array($references) && array_is_list($references)) {
                return array_values(array_filter($references, 'is_string'));
            }
        }

        return [];
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
