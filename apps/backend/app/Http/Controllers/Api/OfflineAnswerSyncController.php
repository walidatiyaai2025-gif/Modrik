<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OfflineAnswerSyncService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OfflineAnswerSyncController extends Controller
{
    public function __construct(private readonly OfflineAnswerSyncService $sync) {}

    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $unexpected = array_values(array_diff(array_keys($payload), ['operations']));
        if ($unexpected !== []) {
            $field = (string) $unexpected[0];
            throw $this->validation('/'.$field, 'FIELD_NOT_ALLOWED', "{$field} is not client writable.");
        }

        $operations = $payload['operations'] ?? null;
        if (! is_array($operations) || ! array_is_list($operations) || count($operations) < 1 || count($operations) > 100) {
            throw $this->validation('/operations', 'SYNC_BATCH_SIZE_INVALID', 'operations must be an ordered array containing 1–100 items.');
        }

        /** @var list<array{operation_id: string, attempt_id: string, attempt_question_id: string, expected_revision: int, value: mixed}> $validated */
        $validated = [];
        foreach ($operations as $index => $operation) {
            $validated[] = $this->operation($operation, $index);
        }

        $acknowledgements = $this->sync->sync($this->user($request), $validated);

        return ApiResponse::success($request, ['acknowledgements' => $acknowledgements]);
    }

    /**
     * @return array{operation_id: string, attempt_id: string, attempt_question_id: string, expected_revision: int, value: mixed}
     */
    private function operation(mixed $operation, int $index): array
    {
        $pointer = '/operations/'.$index;
        if (! is_array($operation) || array_is_list($operation)) {
            throw $this->validation($pointer, 'SYNC_OPERATION_INVALID', 'Each operation must be an object.');
        }

        $allowed = ['operation_id', 'attempt_id', 'attempt_question_id', 'expected_revision', 'value'];
        $unexpected = array_values(array_diff(array_keys($operation), $allowed));
        if ($unexpected !== []) {
            $field = (string) $unexpected[0];
            throw $this->validation($pointer.'/'.$field, 'FIELD_NOT_ALLOWED', "{$field} is not client writable.");
        }

        $operationId = $operation['operation_id'] ?? null;
        if (! is_string($operationId) || preg_match('/^[\x21-\x7E]{16,128}$/D', $operationId) !== 1) {
            throw $this->validation($pointer.'/operation_id', 'SYNC_OPERATION_ID_INVALID', 'operation_id must contain 16–128 visible ASCII characters.');
        }

        $attemptId = $operation['attempt_id'] ?? null;
        if (! is_string($attemptId) || ! Str::isUlid($attemptId)) {
            throw $this->validation($pointer.'/attempt_id', 'ATTEMPT_ID_INVALID', 'attempt_id must be a valid ULID.');
        }

        $attemptQuestionId = $operation['attempt_question_id'] ?? null;
        if (! is_string($attemptQuestionId) || ! Str::isUlid($attemptQuestionId)) {
            throw $this->validation($pointer.'/attempt_question_id', 'ATTEMPT_QUESTION_ID_INVALID', 'attempt_question_id must be a valid ULID.');
        }

        $expectedRevision = $operation['expected_revision'] ?? null;
        if (! is_int($expectedRevision) || $expectedRevision < 0) {
            throw $this->validation($pointer.'/expected_revision', 'EXPECTED_REVISION_INVALID', 'expected_revision must be an integer of zero or greater.');
        }

        if (! array_key_exists('value', $operation)) {
            throw $this->validation($pointer.'/value', 'ANSWER_VALUE_REQUIRED', 'value is required.');
        }

        return [
            'operation_id' => $operationId,
            'attempt_id' => $attemptId,
            'attempt_question_id' => $attemptQuestionId,
            'expected_revision' => $expectedRevision,
            'value' => $operation['value'],
        ];
    }

    private function validation(string $pointer, string $code, string $detail): ApiProblemException
    {
        return new ApiProblemException(
            422,
            'VALIDATION_FAILED',
            'Request validation failed',
            $detail,
            errors: [['pointer' => $pointer, 'code' => $code, 'message' => $detail]],
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid session is required.');
        }

        return $user;
    }
}
