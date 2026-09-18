<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;

final class AssessmentModePolicy
{
    /** @var list<string> */
    public const MODES = [
        'practice',
        'daily_practice',
        'topic_practice',
        'skill_practice',
        'weak_skills',
        'revision',
        'mistakes',
        'diagnostic',
        'exam',
        'custom_assignment',
    ];

    /** @return array{mode: string, hints_allowed: bool, reveal_policy: string} */
    public function forQuizKind(string $kind): array
    {
        $mode = match ($kind) {
            'mock_exam' => 'exam',
            default => $kind,
        };

        if (! in_array($mode, self::MODES, true)) {
            throw new ApiProblemException(
                409,
                'ASSESSMENT_MODE_UNSUPPORTED',
                'Assessment mode is unsupported',
                'The published assessment uses a mode that is not supported by the authoritative runtime.',
            );
        }

        return [
            'mode' => $mode,
            'hints_allowed' => ! in_array($mode, ['diagnostic', 'exam'], true),
            'reveal_policy' => 'after_submit',
        ];
    }
}
