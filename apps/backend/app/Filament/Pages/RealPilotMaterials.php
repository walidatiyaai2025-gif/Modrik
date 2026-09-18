<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use JsonException;
use RuntimeException;
use UnitEnum;

final class RealPilotMaterials extends Page
{
    protected string $view = 'filament.pages.real-pilot-materials';

    protected static ?string $slug = 'real-pilot-materials';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مواد التجربة الحقيقية',
            'fr' => 'Matériaux pilotes réels',
            default => 'Real Pilot Materials',
        };
    }

    public static function getNavigationSort(): int
    {
        return 26;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->text(
            'سجل مقروء فقط للمواد الحقيقية التي سلّمها المالك، مع تقسيم المصدر وبصمة الملف وحالة الربط والحقوق والخصوصية قبل أي توليد أو نشر للأسئلة.',
            'Read-only registry of owner-supplied real materials with source segmentation, file fingerprints, mapping, rights and privacy gates before question generation or publication.',
            'Registre en lecture seule des supports réels fournis par le propriétaire, avec segmentation, empreintes, mapping, droits et confidentialité avant génération ou publication.',
        );
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

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        $path = resource_path('pilot/real-pilot-materials.json');
        $contents = @file_get_contents($path);
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Real pilot materials manifest is unavailable.');
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Real pilot materials manifest is invalid JSON.', previous: $exception);
        }

        if (! is_array($manifest)
            || ($manifest['schema_version'] ?? null) !== 'modrik-real-pilot-sources-v1'
            || ! is_array($manifest['materials'] ?? null)) {
            throw new RuntimeException('Real pilot materials manifest schema is invalid.');
        }

        return $manifest;
    }

    /** @return list<array<string, mixed>> */
    public function materials(): array
    {
        $materials = $this->manifest()['materials'];

        return array_values(array_filter($materials, static fn (mixed $material): bool => is_array($material)));
    }

    /** @return array{total:int, segmented:int, rights_pending:int, mapping_required:int, pii_redaction_required:int, delivery_eligible:int} */
    public function metrics(): array
    {
        $materials = $this->materials();

        return [
            'total' => count($materials),
            'segmented' => count(array_filter($materials, static fn (array $material): bool => is_array($material['segments'] ?? null) && $material['segments'] !== [])),
            'rights_pending' => count(array_filter($materials, static fn (array $material): bool => ($material['rights']['status'] ?? null) === 'pending_review')),
            'mapping_required' => count(array_filter($materials, static fn (array $material): bool => ($material['curriculum_mapping']['status'] ?? null) !== 'mapped')),
            'pii_redaction_required' => count(array_filter($materials, static fn (array $material): bool => ($material['privacy']['status'] ?? null) === 'pii_redaction_required')),
            'delivery_eligible' => count(array_filter($materials, static fn (array $material): bool => ($material['delivery_eligible'] ?? false) === true)),
        ];
    }

    /** @param array<string, mixed> $material */
    public function localizedTitle(array $material): string
    {
        $titles = is_array($material['title'] ?? null) ? $material['title'] : [];
        $locale = App::getLocale();

        return (string) ($titles[$locale] ?? $titles['en'] ?? $material['source_id'] ?? 'Material');
    }

    /** @param array<string, mixed> $material */
    public function mappingLabel(array $material): string
    {
        $mapping = is_array($material['curriculum_mapping'] ?? null) ? $material['curriculum_mapping'] : [];
        $year = is_string($mapping['year_level'] ?? null) && trim($mapping['year_level']) !== '' ? trim($mapping['year_level']) : null;
        $subject = is_string($mapping['subject'] ?? null) && trim($mapping['subject']) !== '' ? trim($mapping['subject']) : null;

        if ($year !== null && $subject !== null) {
            return $year.' · '.$subject;
        }

        return $subject ?? $year ?? $this->text('الربط الأكاديمي مطلوب', 'Academic mapping required', 'Mapping académique requis');
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
