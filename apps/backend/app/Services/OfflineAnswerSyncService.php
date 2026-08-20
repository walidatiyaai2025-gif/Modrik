<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class OfflineAnswerSyncService
{
    public function __construct(private readonly AttemptService $attempts) {}

    /**
     * @param  list<array{operation_id: string, attempt_id: string, attempt_question_id: string, expected_revision: int, value: mixed}>  $operations
     * @return list<array{operation_id: string, outcome: string, code: string, replayed: bool, retryable: bool, answer_revision: ?int, answered_at: ?string}>
     *
     * @throws JsonException
     */
    public function sync(User $user, array $operations): array
    {
        $secret = (string) config('modrik.idempotency.secret');
        if ($secret === '') {
            throw new ApiProblemException(
                500,
                'SYNC_CONFIGURATION_INVALID',
                'Offline sync unavailable',
                'The offline sync digest is not configured.',
            );
        }

        $acknowledgements = [];
        foreach ($operations as $operation) {
            $acknowledgements[] = $this->syncOne($user, $operation, $secret);
        }

        return $acknowledgements;
    }

    /**
     * @param  array{operation_id: string, attempt_id: string, attempt_question_id: string, expected_revision: int, value: mixed}  $operation
     * @return array{operation_id: string, outcome: string, code: string, replayed: bool, retryable: bool, answer_revision: ?int, answered_at: ?string}
     *
     * @throws JsonException
     */
    private function syncOne(User $user, array $operation, string $secret): array
    {
        $operationId = $operation['operation_id'];
        $operationDigest = hash_hmac('sha256', "modrik-answer-sync-v1\0".$operationId, $secret);
        $requestHash = $this->requestHash($operation);

        return DB::transaction(function () use ($user, $operation, $operationId, $operationDigest, $requestHash): array {
            $now = now();
            $inserted = DB::table('answer_sync_acknowledgements')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'actor_id' => $user->getKey(),
                'operation_id_digest' => $operationDigest,
                'request_hash' => $requestHash,
                'outcome' => 'processing',
                'retryable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]) === 1;

            $record = DB::table('answer_sync_acknowledgements')
                ->where('actor_id', $user->getKey())
                ->where('operation_id_digest', $operationDigest)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new ApiProblemException(
                    500,
                    'SYNC_ACKNOWLEDGEMENT_UNAVAILABLE',
                    'Offline sync unavailable',
                    'The operation acknowledgement could not be reserved.',
                    true,
                );
            }

            /** @var array<string, mixed> $stored */
            $stored = (array) $record;

            if (! hash_equals((string) $stored['request_hash'], $requestHash)) {
                return $this->acknowledgement(
                    operationId: $operationId,
                    outcome: 'conflict',
                    code: 'SYNC_OPERATION_ID_REUSED',
                    replayed: false,
                    retryable: false,
                );
            }

            if (! $inserted) {
                if ($stored['outcome'] === 'processing' || ! is_string($stored['code'])) {
                    return $this->acknowledgement(
                        operationId: $operationId,
                        outcome: 'conflict',
                        code: 'SYNC_OPERATION_IN_PROGRESS',
                        replayed: false,
                        retryable: true,
                    );
                }

                return $this->fromStored($operationId, $stored);
            }

            try {
                $answer = DB::transaction(fn (): array => $this->attempts->recordAnswer(
                    $user,
                    $operation['attempt_id'],
                    $operation['attempt_question_id'],
                    $operation['expected_revision'],
                    $operation['value'],
                ));
            } catch (ApiProblemException $exception) {
                if ($exception->status >= 500) {
                    throw $exception;
                }

                $outcome = $exception->status === 409 ? 'conflict' : 'rejected';
                $completedAt = now();
                DB::table('answer_sync_acknowledgements')
                    ->where('id', $stored['id'])
                    ->update([
                        'outcome' => $outcome,
                        'code' => $exception->problemCode,
                        'retryable' => $exception->retryable,
                        'completed_at' => $completedAt,
                        'updated_at' => $completedAt,
                    ]);

                return $this->acknowledgement(
                    operationId: $operationId,
                    outcome: $outcome,
                    code: $exception->problemCode,
                    replayed: false,
                    retryable: $exception->retryable,
                );
            }

            $revision = (int) $answer['revision'];
            $answeredAt = CarbonImmutable::parse((string) $answer['answered_at'])->utc();
            $completedAt = now();
            DB::table('answer_sync_acknowledgements')
                ->where('id', $stored['id'])
                ->update([
                    'outcome' => 'applied',
                    'code' => 'SYNC_ANSWER_APPLIED',
                    'answer_revision' => $revision,
                    'answered_at' => $answeredAt,
                    'retryable' => false,
                    'completed_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);

            return $this->acknowledgement(
                operationId: $operationId,
                outcome: 'applied',
                code: 'SYNC_ANSWER_APPLIED',
                replayed: false,
                retryable: false,
                answerRevision: $revision,
                answeredAt: $answeredAt->toIso8601String(),
            );
        }, 3);
    }

    /**
     * @param  array{operation_id: string, attempt_id: string, attempt_question_id: string, expected_revision: int, value: mixed}  $operation
     *
     * @throws JsonException
     */
    private function requestHash(array $operation): string
    {
        $canonical = [
            'operation' => 'attempt.answer.sync.v1',
            'attempt_id' => $operation['attempt_id'],
            'attempt_question_id' => $operation['attempt_question_id'],
            'expected_revision' => $operation['expected_revision'],
            'value' => $this->canonicalize($operation['value']),
        ];

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{operation_id: string, outcome: string, code: string, replayed: bool, retryable: bool, answer_revision: ?int, answered_at: ?string}
     */
    private function fromStored(string $operationId, array $stored): array
    {
        $answeredAt = is_string($stored['answered_at'] ?? null)
            ? CarbonImmutable::parse($stored['answered_at'])->utc()->toIso8601String()
            : null;

        return $this->acknowledgement(
            operationId: $operationId,
            outcome: (string) $stored['outcome'],
            code: (string) $stored['code'],
            replayed: true,
            retryable: (bool) $stored['retryable'],
            answerRevision: $stored['answer_revision'] === null ? null : (int) $stored['answer_revision'],
            answeredAt: $answeredAt,
        );
    }

    /**
     * @return array{operation_id: string, outcome: string, code: string, replayed: bool, retryable: bool, answer_revision: ?int, answered_at: ?string}
     */
    private function acknowledgement(
        string $operationId,
        string $outcome,
        string $code,
        bool $replayed,
        bool $retryable,
        ?int $answerRevision = null,
        ?string $answeredAt = null,
    ): array {
        return [
            'operation_id' => $operationId,
            'outcome' => $outcome,
            'code' => $code,
            'replayed' => $replayed,
            'retryable' => $retryable,
            'answer_revision' => $answerRevision,
            'answered_at' => $answeredAt,
        ];
    }
}
