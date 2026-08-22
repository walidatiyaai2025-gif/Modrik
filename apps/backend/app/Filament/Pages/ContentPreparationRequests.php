<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

final class ContentPreparationRequests extends Page
{
    protected string $view = 'filament.pages.content-preparation-requests';

    protected static ?string $slug = 'content-preparation-requests';

    public string $statusFilter = 'all';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'طلبات إعداد المحتوى',
            'fr' => 'Demandes de préparation',
            default => 'Preparation Requests',
        };
    }

    public static function getNavigationSort(): int
    {
        return 20;
    }

    public function getTitle(): string
    {
        return match (App::getLocale()) {
            'ar' => 'طلبات إعداد المحتوى السابقة',
            'fr' => 'Historique des demandes de préparation',
            default => 'Preparation Request History',
        };
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'اعثر على أي طلب إعداد محفوظ وافتح إعداداته والـPrompt والـBundle والـZIP المرتبط به بدون الحاجة لمعرفة المعرّف مسبقًا.',
            'fr' => 'Retrouvez une demande enregistrée et rouvrez ses paramètres, son prompt, son bundle et son ZIP sans connaître son identifiant à l’avance.',
            default => 'Find any saved preparation request and reopen its settings, prompt, bundle, and returned ZIP workflow without knowing the request ID first.',
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
    public function rows(): array
    {
        $query = DB::table('preparation_requests')
            ->select([
                'id',
                'schema_version',
                'settings_hash',
                'normalized_settings',
                'status',
                'superseded_by_request_id',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('created_at')
            ->limit(100);

        if (in_array($this->statusFilter, ['ready', 'superseded'], true)) {
            $query->where('status', $this->statusFilter);
        }

        return $query->get()->map(static function (object $row): array {
            $settings = json_decode((string) $row->normalized_settings, true);
            $settings = is_array($settings) ? $settings : [];
            $scope = is_array($settings['academic_scope'] ?? null) ? $settings['academic_scope'] : [];
            $locales = is_array($settings['locales'] ?? null) ? array_values(array_map('strval', $settings['locales'])) : [];
            $contentTypes = is_array($settings['content_types'] ?? null) ? array_values(array_map('strval', $settings['content_types'])) : [];
            $subjects = is_array($scope['subject_references'] ?? null) ? array_values(array_map('strval', $scope['subject_references'])) : [];

            return [
                'id' => (string) $row->id,
                'schema_version' => (string) $row->schema_version,
                'settings_hash' => (string) $row->settings_hash,
                'status' => (string) $row->status,
                'superseded_by_request_id' => is_string($row->superseded_by_request_id) ? $row->superseded_by_request_id : null,
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,
                'track_reference' => (string) ($scope['track_reference'] ?? ''),
                'board_reference' => (string) ($scope['board_reference'] ?? ''),
                'syllabus_version' => (string) ($scope['syllabus_version'] ?? ''),
                'year_level' => (string) ($scope['year_level'] ?? ''),
                'subject_references' => $subjects,
                'locales' => $locales,
                'content_types' => $contentTypes,
            ];
        })->all();
    }
}
