<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AcademicTrackCatalogueService
{
    private const LOCALES = ['ar', 'en', 'fr'];

    /** @return list<array{id: string, year: array{key: string, label: string}, labels: array{ar: string, en: string, fr: string}}> */
    public function catalogue(): array
    {
        $tracks = [];

        foreach ($this->availableQuery()
            ->orderBy('academic_tracks.year_level')
            ->orderBy('academic_tracks.id')
            ->get() as $row) {
            $track = (array) $row;
            $labels = $this->displayLabels($track['title'] ?? null);
            $year = $this->displayYear($track['year_level'] ?? null);
            if ($labels === null || $year === null) {
                continue;
            }

            $tracks[] = [
                'id' => (string) $track['id'],
                'year' => $year,
                'labels' => $labels,
            ];
        }

        return $tracks;
    }

    /** @return array{id: string, year_level: string} */
    public function requireAvailableTrack(string $academicTrackId): array
    {
        $row = $this->availableQuery()
            ->where('academic_tracks.id', $academicTrackId)
            ->first();

        if ($row === null) {
            throw $this->unavailableTrack();
        }

        $track = (array) $row;
        if ($this->displayLabels($track['title'] ?? null) === null || $this->displayYear($track['year_level'] ?? null) === null) {
            throw $this->unavailableTrack();
        }

        return [
            'id' => (string) $track['id'],
            'year_level' => (string) $track['year_level'],
        ];
    }

    private function availableQuery(): Builder
    {
        $query = DB::table('academic_tracks')
            ->select([
                'academic_tracks.id',
                'academic_tracks.year_level',
                'academic_tracks.title',
                'academic_tracks.is_fixture',
            ]);

        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        return $query;
    }

    /** @return null|array{key: string, label: string} */
    private function displayYear(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $key = trim($value);
        if ($key === '' || mb_strlen($key) > 160 || $this->containsUnsafeMarkupOrControls($key)) {
            return null;
        }

        $segments = preg_split('/[:\/]+/', $key) ?: [$key];
        if (Str::upper((string) $segments[0]) === 'YEAR') {
            array_shift($segments);
        }
        if (count($segments) > 1 && preg_match('/^[A-F0-9]{8}$/i', (string) end($segments)) === 1) {
            array_pop($segments);
        }

        $readable = trim(implode(' ', $segments));
        $readable = str_replace(['-', '_', '.'], ' ', $readable);
        $label = Str::headline($readable === '' ? $key : $readable);
        if ($label === '' || mb_strlen($label) > 160 || $this->containsUnsafeMarkupOrControls($label)) {
            return null;
        }

        return ['key' => $key, 'label' => $label];
    }

    /** @return null|array{ar: string, en: string, fr: string} */
    private function displayLabels(mixed $value): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $labels = [];
        foreach (self::LOCALES as $locale) {
            $candidate = $decoded[$locale] ?? null;
            if (! is_string($candidate)) {
                return null;
            }

            $label = trim($candidate);
            if ($label === '' || mb_strlen($label) > 160 || $this->containsUnsafeMarkupOrControls($label)) {
                return null;
            }

            $labels[$locale] = $label;
        }

        return $labels;
    }

    private function containsUnsafeMarkupOrControls(string $label): bool
    {
        if (strip_tags($label) !== $label) {
            return true;
        }

        return preg_match('/[\p{Cc}\p{Cf}]/u', $label) === 1;
    }

    private function unavailableTrack(): ApiProblemException
    {
        return new ApiProblemException(
            404,
            'RESOURCE_NOT_FOUND',
            'Resource not found',
            'The requested academic track is unavailable.',
        );
    }
}
