<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;

final class MasteryBandPolicy
{
    public const CRITICAL_MAX_KEY = 'learning.mastery.band.critical_max';

    public const WEAK_MAX_KEY = 'learning.mastery.band.weak_max';

    public const DEVELOPING_MAX_KEY = 'learning.mastery.band.developing_max';

    public function __construct(private readonly SystemSettingsRegistry $settings) {}

    /** @return array{critical_max:int, weak_max:int, developing_max:int, version_fingerprint:string} */
    public function thresholds(string $environment): array
    {
        $critical = $this->settings->current(self::CRITICAL_MAX_KEY, $environment);
        $weak = $this->settings->current(self::WEAK_MAX_KEY, $environment);
        $developing = $this->settings->current(self::DEVELOPING_MAX_KEY, $environment);

        $criticalMax = $this->integer($critical['value'] ?? null);
        $weakMax = $this->integer($weak['value'] ?? null);
        $developingMax = $this->integer($developing['value'] ?? null);

        if ($criticalMax < 0
            || $criticalMax >= $weakMax
            || $weakMax >= $developingMax
            || $developingMax >= 100) {
            throw new ApiProblemException(
                500,
                'MASTERY_BAND_CONFIGURATION_INVALID',
                'Mastery display bands are invalid',
                'Governed mastery display thresholds must be strictly increasing between 0 and 99.',
            );
        }

        return [
            'critical_max' => $criticalMax,
            'weak_max' => $weakMax,
            'developing_max' => $developingMax,
            'version_fingerprint' => hash('sha256', implode(':', [
                (string) ($critical['version'] ?? 0),
                (string) ($weak['version'] ?? 0),
                (string) ($developing['version'] ?? 0),
                (string) $criticalMax,
                (string) $weakMax,
                (string) $developingMax,
            ])),
        ];
    }

    public function band(float $scorePercent, string $environment): string
    {
        $scorePercent = max(0.0, min(100.0, $scorePercent));
        $thresholds = $this->thresholds($environment);

        return match (true) {
            $scorePercent <= $thresholds['critical_max'] => 'critical',
            $scorePercent <= $thresholds['weak_max'] => 'weak',
            $scorePercent <= $thresholds['developing_max'] => 'developing',
            default => 'strong',
        };
    }

    private function integer(mixed $value): int
    {
        if (! is_int($value)) {
            throw new ApiProblemException(
                500,
                'MASTERY_BAND_CONFIGURATION_INVALID',
                'Mastery display bands are invalid',
                'Governed mastery display thresholds must be integers.',
            );
        }

        return $value;
    }
}
