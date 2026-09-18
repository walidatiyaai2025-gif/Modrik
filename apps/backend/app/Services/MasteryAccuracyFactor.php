<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use JsonException;

final class MasteryAccuracyFactor
{
    public const ALGORITHM_VERSION = 'mastery-accuracy-v1';

    /**
     * @return array{
     *     algorithm_version: string,
     *     skill_node_id: string,
     *     evidence_count: int,
     *     correct_count: int,
     *     score: float
     * }
     */
    public function calculate(User $user, string $academicContextId, string $skillNodeId): array
    {
        $rows = DB::table('attempt_answers')
            ->join('attempt_questions', 'attempt_questions.id', '=', 'attempt_answers.attempt_question_id')
            ->join('attempts', 'attempts.id', '=', 'attempt_questions.attempt_id')
            ->where('attempts.user_id', $user->getKey())
            ->where('attempts.academic_context_id', $academicContextId)
            ->where('attempts.status', 'graded')
            ->whereNotNull('attempt_answers.graded_at')
            ->whereNotNull('attempt_answers.is_correct')
            ->orderBy('attempt_questions.id')
            ->orderBy('attempt_answers.revision')
            ->get([
                'attempt_questions.id as attempt_question_id',
                'attempt_questions.question_snapshot',
                'attempt_answers.revision',
                'attempt_answers.is_correct',
            ]);

        /** @var array<string, array{revision: int, is_correct: bool}> $latest */
        $latest = [];

        foreach ($rows as $row) {
            /** @var array{attempt_question_id: string, question_snapshot: string, revision: int, is_correct: bool|int|string} $record */
            $record = (array) $row;
            $snapshot = $this->snapshot($record['question_snapshot']);

            if (($snapshot['skill_node_id'] ?? null) !== $skillNodeId) {
                continue;
            }

            $latest[$record['attempt_question_id']] = [
                'revision' => (int) $record['revision'],
                'is_correct' => (bool) $record['is_correct'],
            ];
        }

        $evidenceCount = count($latest);
        $correctCount = count(array_filter(
            $latest,
            static fn (array $answer): bool => $answer['is_correct'],
        ));

        return [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'skill_node_id' => $skillNodeId,
            'evidence_count' => $evidenceCount,
            'correct_count' => $correctCount,
            'score' => $evidenceCount === 0
                ? 0.0
                : round(($correctCount / $evidenceCount) * 100, 4),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(string $json): array
    {
        try {
            $snapshot = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiProblemException(
                500,
                'MASTERY_EVIDENCE_INVALID',
                'Mastery evidence is invalid',
                'An immutable assessment question snapshot could not be decoded.',
                previous: $exception,
            );
        }

        if (! is_array($snapshot) || array_is_list($snapshot)) {
            throw new ApiProblemException(
                500,
                'MASTERY_EVIDENCE_INVALID',
                'Mastery evidence is invalid',
                'An immutable assessment question snapshot is not a JSON object.',
            );
        }

        return $snapshot;
    }
}
