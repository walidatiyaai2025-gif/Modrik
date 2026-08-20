<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AcademicTrackCatalogueService
{
    private const LOCALES = ['ar', 'en', 'fr'];

    /** @return list<array{id: string, labels: array{ar: string, en: string, fr: string}}> */
    public function catalogue(User $user): array
    {
        $tracks = [];

        foreach ($this->authorizedQuery($user)
            ->orderBy('academic_track_authorizations.sort_order')
            ->orderBy('academic_tracks.id')
            ->get() as $row) {
            $track = (array) $row;
            $labels = $this->displayLabels($track['title'] ?? null);
            if ($labels === null) {
                continue;
            }

            $tracks[] = [
                'id' => (string) $track['id'],
                'labels' => $labels,
            ];
        }

        return $tracks;
    }

    /** @return array{id: string, year_level: string} */
    public function requireAuthorizedTrack(User $user, string $academicTrackId): array
    {
        $row = $this->authorizedQuery($user)
            ->where('academic_tracks.id', $academicTrackId)
            ->first();

        if ($row === null) {
            throw $this->unavailableTrack();
        }

        $track = (array) $row;
        if ($this->displayLabels($track['title'] ?? null) === null) {
            throw $this->unavailableTrack();
        }

        return [
            'id' => (string) $track['id'],
            'year_level' => (string) $track['year_level'],
        ];
    }

    private function authorizedQuery(User $user): Builder
    {
        $query = DB::table('academic_track_authorizations')
            ->join(
                'academic_tracks',
                'academic_tracks.id',
                '=',
                'academic_track_authorizations.academic_track_id',
            )
            ->where('academic_track_authorizations.user_id', $user->getKey())
            ->whereNull('academic_track_authorizations.revoked_at')
            ->select([
                'academic_tracks.id',
                'academic_tracks.year_level',
                'academic_tracks.title',
                'academic_tracks.is_fixture',
                'academic_track_authorizations.sort_order',
            ]);

        if (! (bool) config('modrik.fixture.enabled')) {
            $query->where('academic_tracks.is_fixture', false);
        }

        return $query;
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
