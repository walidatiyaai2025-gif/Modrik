<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Exceptions\ContentValidationException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class ContentPreparationService
{
    public function __construct(private readonly ContentPackArchiveValidator $archives) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function create(User $user, array $payload): array
    {
        $settings = $this->validateRequest($payload);
        $schemaVersion = (string) config('modrik.content_import.schema_version');
        $normalizedSettings = $this->canonicalJson($settings);
        $settingsHash = hash('sha256', $normalizedSettings);
        $requestId = (string) Str::ulid();
        $createdAt = now();
        $prompt = $this->prompt($requestId, $schemaVersion, $settingsHash, $normalizedSettings);

        DB::table('preparation_requests')->insert([
            'id' => $requestId,
            'created_by' => $user->getKey(),
            'schema_version' => $schemaVersion,
            'settings_hash' => $settingsHash,
            'normalized_settings' => $normalizedSettings,
            'prompt' => $prompt,
            'status' => 'ready',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $this->outbox('preparation_request', $requestId, 'content.preparation_requested', [
            'schema_version' => $schemaVersion,
            'settings_hash' => $settingsHash,
        ]);

        return [
            'preparation_request_id' => $requestId,
            'schema_version' => $schemaVersion,
            'settings_hash' => $settingsHash,
            'status' => 'ready',
            'prompt' => $prompt,
            'bundle' => [
                'manifest_binding' => [
                    'preparation_request_id' => $requestId,
                    'schema_version' => $schemaVersion,
                    'settings_hash' => $settingsHash,
                ],
                'settings' => $settings,
            ],
        ];
    }

    /**
     * @return array{accepted: bool, data: array<string, mixed>, errors: list<array{pointer: string, code: string, message: string}>}
     *
     * @throws JsonException
     */
    public function stage(User $user, UploadedFile $archive): array
    {
        $archivePath = $archive->getRealPath();
        $archiveBytes = $archive->getSize();
        if ($archivePath === false || ! is_int($archiveBytes) || $archiveBytes < 1) {
            throw new ApiProblemException(400, 'CONTENT_ARCHIVE_UNREADABLE', 'Content archive unreadable', 'A non-empty ZIP archive is required.');
        }
        if ($archiveBytes > (int) config('modrik.content_import.maximum_archive_bytes')) {
            throw new ApiProblemException(413, 'CONTENT_ARCHIVE_SIZE_EXCEEDED', 'Content archive too large', 'The uploaded ZIP exceeds the configured archive limit.');
        }
        $archiveHash = hash_file('sha256', $archivePath);
        if (! is_string($archiveHash)) {
            throw new ApiProblemException(400, 'CONTENT_ARCHIVE_UNREADABLE', 'Content archive unreadable', 'The uploaded ZIP could not be hashed.');
        }

        $existing = DB::table('preparation_imports')
            ->where('uploaded_by', $user->getKey())
            ->where('archive_hash', $archiveHash)
            ->first();
        if ($existing !== null) {
            return $this->storedResult((array) $existing);
        }

        $importId = (string) Str::ulid();
        $createdAt = now();
        DB::table('preparation_imports')->insert([
            'id' => $importId,
            'uploaded_by' => $user->getKey(),
            'archive_hash' => $archiveHash,
            'status' => 'validating',
            'validation_summary' => $this->json(['valid' => false, 'errors' => []]),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        try {
            $validated = $this->archives->validate($archivePath);
            $manifest = $validated['manifest'];
            $claimedRequestId = (string) $manifest['preparation_request_id'];
            $preparation = DB::table('preparation_requests')
                ->where('id', $claimedRequestId)
                ->lockForUpdate()
                ->first();
            if ($preparation === null) {
                $this->reject('PREPARATION_REQUEST_NOT_FOUND', 'The bound preparation request does not exist.', '/preparation_request_id', $manifest);
            }
            /** @var array<string, mixed> $requestRow */
            $requestRow = (array) $preparation;
            DB::table('preparation_imports')->where('id', $importId)->update([
                'preparation_request_id' => $claimedRequestId,
                'claimed_preparation_request_id' => $claimedRequestId,
                'pack_id' => $manifest['pack_id'],
                'rights_status' => $manifest['provenance']['rights_status'],
                'updated_at' => now(),
            ]);

            if ($manifest['schema_version'] !== $requestRow['schema_version']) {
                $this->reject('CONTENT_SCHEMA_VERSION_MISMATCH', 'The archive schema version does not match its preparation request.', '/schema_version', $manifest);
            }
            if (! hash_equals((string) $requestRow['settings_hash'], (string) $manifest['settings_hash'])) {
                $this->reject('CONTENT_SETTINGS_HASH_MISMATCH', 'The archive settings hash does not match its preparation request.', '/settings_hash', $manifest);
            }

            $settings = json_decode((string) $requestRow['normalized_settings'], true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($settings)
                || $this->canonicalJson($settings['academic_scope'] ?? null) !== $this->canonicalJson($validated['content_pack']['academic_scope'] ?? null)) {
                $this->reject('CONTENT_SCOPE_MISMATCH', 'The Content Pack academic scope does not match its preparation settings.', '/academic_scope', $manifest);
            }
            $rightsStatus = $manifest['provenance']['rights_status'];
            if ($rightsStatus !== 'synthetic_fixture' || ! (bool) config('modrik.fixture.enabled')) {
                $this->reject('CONTENT_RIGHTS_REVIEW_REQUIRED', 'Only the synthetic fixture may stage without owner rights review evidence.', '/provenance/rights_status', $manifest);
            }
            $maximumQuestions = (int) ($settings['generation']['maximum_questions_per_quiz'] ?? 0);
            foreach ($validated['content_pack']['quizzes'] as $position => $quiz) {
                if (is_array($quiz) && count($quiz['question_ids'] ?? []) > $maximumQuestions) {
                    $this->reject('CONTENT_QUIZ_LIMIT_EXCEEDED', 'A quiz exceeds the preparation request question limit.', '/quizzes/'.$position.'/question_ids', $manifest);
                }
            }

            foreach ($validated['files'] as $file) {
                DB::table('preparation_import_files')->insert([
                    'id' => (string) Str::ulid(),
                    'preparation_import_id' => $importId,
                    'path' => $file['path'],
                    'media_type' => $file['media_type'],
                    'sha256' => $file['sha256'],
                    'bytes' => $file['bytes'],
                    'status' => 'validated',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $summary = ['valid' => true, 'errors' => []];
            DB::table('preparation_imports')->where('id', $importId)->update([
                'status' => 'staged',
                'validation_summary' => $this->json($summary),
                'imported_file_count' => count($validated['files']),
                'imported_record_count' => $validated['imported_record_count'],
                'updated_at' => now(),
            ]);
            DB::table('preparation_requests')->where('id', $claimedRequestId)->update([
                'status' => 'returned',
                'updated_at' => now(),
            ]);
            $this->outbox('preparation_import', $importId, 'content.preparation_imported', [
                'preparation_request_id' => $claimedRequestId,
                'imported_file_count' => count($validated['files']),
                'imported_record_count' => $validated['imported_record_count'],
            ]);

            return [
                'accepted' => true,
                'data' => $this->resultData($importId, 'staged', $claimedRequestId, (string) $manifest['pack_id'], count($validated['files']), $validated['imported_record_count'], $summary),
                'errors' => [],
            ];
        } catch (ContentValidationException $exception) {
            $manifest = $exception->manifest;
            $claimedRequestId = is_string($manifest['preparation_request_id'] ?? null) ? $manifest['preparation_request_id'] : null;
            $summary = ['valid' => false, 'errors' => $exception->errors];
            $provenance = is_array($manifest['provenance'] ?? null) ? $manifest['provenance'] : [];
            DB::table('preparation_imports')->where('id', $importId)->update([
                'preparation_request_id' => $claimedRequestId !== null && DB::table('preparation_requests')->where('id', $claimedRequestId)->exists()
                    ? $claimedRequestId
                    : null,
                'claimed_preparation_request_id' => $claimedRequestId,
                'pack_id' => is_string($manifest['pack_id'] ?? null) ? $manifest['pack_id'] : null,
                'rights_status' => is_string($provenance['rights_status'] ?? null) ? $provenance['rights_status'] : null,
                'status' => 'rejected',
                'validation_summary' => $this->json($summary),
                'updated_at' => now(),
            ]);
            $this->outbox('preparation_import', $importId, 'content.preparation_import_rejected', [
                'preparation_request_id' => $claimedRequestId,
                'rejection_codes' => array_values(array_unique(array_column($exception->errors, 'code'))),
            ]);

            return [
                'accepted' => false,
                'data' => $this->resultData($importId, 'rejected', $claimedRequestId, null, 0, 0, $summary),
                'errors' => $exception->errors,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateRequest(array $payload): array
    {
        if (array_diff(array_keys($payload), ['schema_version', 'settings']) !== []
            || array_diff(['schema_version', 'settings'], array_keys($payload)) !== []
            || $payload['schema_version'] !== (string) config('modrik.content_import.schema_version')
            || ! is_array($payload['settings'])) {
            throw $this->invalidRequest('/', 'PREPARATION_REQUEST_SCHEMA_INVALID', 'schema_version 1.0.0 and a settings object are required with no extra fields.');
        }
        $settings = $payload['settings'];
        $allowedSettings = ['locales', 'academic_scope', 'content_types', 'generation'];
        if (array_diff(array_keys($settings), $allowedSettings) !== [] || array_diff($allowedSettings, array_keys($settings)) !== []) {
            throw $this->invalidRequest('/settings', 'PREPARATION_SETTINGS_INVALID', 'Settings must contain only locales, academic_scope, content_types, and generation.');
        }
        $locales = $settings['locales'];
        if (! is_array($locales) || ! array_is_list($locales) || $locales === []
            || count($locales) !== count(array_unique($locales, SORT_REGULAR)) || array_filter($locales, 'is_string') !== $locales
            || array_diff($locales, ['ar', 'en', 'fr']) !== []) {
            throw $this->invalidRequest('/settings/locales', 'PREPARATION_LOCALES_INVALID', 'Locales must be a unique non-empty subset of ar, en, and fr.');
        }
        $contentTypes = $settings['content_types'];
        if (! is_array($contentTypes) || ! array_is_list($contentTypes) || $contentTypes === []
            || count($contentTypes) !== count(array_unique($contentTypes, SORT_REGULAR)) || array_filter($contentTypes, 'is_string') !== $contentTypes
            || array_diff($contentTypes, ['lesson', 'practice_quiz', 'mock_exam']) !== []) {
            throw $this->invalidRequest('/settings/content_types', 'PREPARATION_CONTENT_TYPES_INVALID', 'Content types are invalid.');
        }
        $scope = $settings['academic_scope'];
        if (! is_array($scope) || array_is_list($scope) || ! is_string($scope['track_reference'] ?? null)
            || preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,99}$/', $scope['track_reference']) !== 1
            || ! is_string($scope['year_level'] ?? null) || $scope['year_level'] === '' || mb_strlen($scope['year_level']) > 40
            || ! is_array($scope['subject_references'] ?? null) || ! array_is_list($scope['subject_references'])
            || $scope['subject_references'] === [] || count($scope['subject_references']) > 20
            || count($scope['subject_references']) !== count(array_unique($scope['subject_references'], SORT_REGULAR))) {
            throw $this->invalidRequest('/settings/academic_scope', 'PREPARATION_ACADEMIC_SCOPE_INVALID', 'The academic scope is invalid.');
        }
        $allowedScope = ['track_reference', 'board_reference', 'syllabus_version', 'year_level', 'subject_references'];
        if (array_diff(array_keys($scope), $allowedScope) !== []) {
            throw $this->invalidRequest('/settings/academic_scope', 'PREPARATION_ACADEMIC_SCOPE_INVALID', 'The academic scope contains unsupported fields.');
        }
        $boardReference = $scope['board_reference'] ?? null;
        $syllabusVersion = $scope['syllabus_version'] ?? null;
        if (($boardReference !== null && (! is_string($boardReference)
                || preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,99}$/', $boardReference) !== 1))
            || ($syllabusVersion !== null && (! is_string($syllabusVersion)
                || $syllabusVersion === '' || mb_strlen($syllabusVersion) > 100))) {
            throw $this->invalidRequest('/settings/academic_scope', 'PREPARATION_ACADEMIC_SCOPE_INVALID', 'Board and syllabus references are invalid.');
        }
        foreach ($scope['subject_references'] as $reference) {
            if (! is_string($reference) || preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,99}$/', $reference) !== 1) {
                throw $this->invalidRequest('/settings/academic_scope/subject_references', 'PREPARATION_ACADEMIC_SCOPE_INVALID', 'Subject references are invalid.');
            }
        }
        $generation = $settings['generation'];
        if (! is_array($generation)
            || array_diff(array_keys($generation), ['include_answer_explanations', 'maximum_questions_per_quiz', 'paid_ai_required']) !== []
            || array_diff(['include_answer_explanations', 'maximum_questions_per_quiz', 'paid_ai_required'], array_keys($generation)) !== []
            || ! is_bool($generation['include_answer_explanations'])
            || ! is_int($generation['maximum_questions_per_quiz'])
            || $generation['maximum_questions_per_quiz'] < 1 || $generation['maximum_questions_per_quiz'] > 200
            || $generation['paid_ai_required'] !== false) {
            throw $this->invalidRequest('/settings/generation', 'PREPARATION_GENERATION_INVALID', 'Generation settings are invalid and paid_ai_required must be false.');
        }

        return $settings;
    }

    private function invalidRequest(string $pointer, string $code, string $message): ApiProblemException
    {
        return new ApiProblemException(422, 'VALIDATION_FAILED', 'Request validation failed', $message, errors: [[
            'pointer' => $pointer,
            'code' => $code,
            'message' => $message,
        ]]);
    }

    /** @param array<string, mixed> $manifest */
    private function reject(string $code, string $message, string $pointer, array $manifest): never
    {
        throw new ContentValidationException([['pointer' => $pointer, 'code' => $code, 'message' => $message]], $manifest);
    }

    private function prompt(string $requestId, string $schemaVersion, string $settingsHash, string $settings): string
    {
        return implode("\n", [
            'MODRIK deterministic Content Preparation request',
            'preparation_request_id='.$requestId,
            'schema_version='.$schemaVersion,
            'settings_hash='.$settingsHash,
            'normalized_settings='.$settings,
            'Return one ZIP containing manifest.json and every file declared by that manifest.',
            'Do not include student PII. Do not require paid AI. Do not claim rights that have not been reviewed.',
        ]);
    }

    private function canonicalJson(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $item) {
                $value[$key] = json_decode($this->canonicalJson($item), true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return $this->json($value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{accepted: bool, data: array<string, mixed>, errors: list<array{pointer: string, code: string, message: string}>}
     */
    private function storedResult(array $row): array
    {
        $summary = json_decode((string) $row['validation_summary'], true, flags: JSON_THROW_ON_ERROR);
        $accepted = $row['status'] === 'staged';

        return [
            'accepted' => $accepted,
            'data' => $this->resultData(
                (string) $row['id'],
                (string) $row['status'],
                is_string($row['claimed_preparation_request_id']) ? $row['claimed_preparation_request_id'] : null,
                is_string($row['pack_id']) ? $row['pack_id'] : null,
                (int) $row['imported_file_count'],
                (int) $row['imported_record_count'],
                is_array($summary) ? $summary : ['valid' => false, 'errors' => []],
            ),
            'errors' => $this->storedErrors($summary['errors'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function resultData(string $id, string $status, ?string $requestId, ?string $packId, int $fileCount, int $recordCount, array $summary): array
    {
        return [
            'preparation_import_id' => $id,
            'preparation_request_id' => $requestId,
            'pack_id' => $packId,
            'status' => $status,
            'validated_file_count' => $fileCount,
            'validated_record_count' => $recordCount,
            'validation_summary' => $summary,
        ];
    }

    /** @return list<array{pointer: string, code: string, message: string}> */
    private function storedErrors(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $errors = [];
        foreach ($value as $error) {
            if (is_array($error)
                && is_string($error['pointer'] ?? null)
                && is_string($error['code'] ?? null)
                && is_string($error['message'] ?? null)) {
                $errors[] = [
                    'pointer' => $error['pointer'],
                    'code' => $error['code'],
                    'message' => $error['message'],
                ];
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $payload */
    private function outbox(string $aggregateType, string $aggregateId, string $eventType, array $payload): void
    {
        DB::table('outbox_events')->insert([
            'id' => (string) Str::ulid(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $this->json($payload),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
