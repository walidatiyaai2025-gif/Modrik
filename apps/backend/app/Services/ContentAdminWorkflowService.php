<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class ContentAdminWorkflowService
{
    /** @var list<string> */
    private const OPERATOR_ROLES = ['admin', 'content_team'];

    /** @var list<string> */
    private const REVIEW_DECISIONS = ['approved', 'rejected', 'request_fix'];

    public function __construct(
        private readonly ContentPreparationService $preparation,
        private readonly ContentPackArchiveValidator $archives,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function createRequest(User $user, array $payload): array
    {
        $this->assertOperator($user);

        return $this->preparation->create($user, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function regenerateRequest(User $user, string $requestId, array $payload): array
    {
        $this->assertOperator($user);

        return DB::transaction(function () use ($user, $requestId, $payload): array {
            $request = DB::table('preparation_requests')->where('id', $requestId)->lockForUpdate()->first();
            if (! $request instanceof \stdClass) {
                throw $this->problem(404, 'PREPARATION_REQUEST_NOT_FOUND', 'Preparation request not found', 'The preparation request does not exist.');
            }
            if ((string) $request->status === 'superseded' || $request->superseded_by_request_id !== null) {
                throw $this->problem(409, 'PREPARATION_REQUEST_ALREADY_SUPERSEDED', 'Preparation request already superseded', 'Regenerate from the latest active preparation request instead.');
            }

            $replacement = $this->preparation->create($user, $payload);
            if (hash_equals((string) $request->settings_hash, (string) $replacement['settings_hash'])) {
                throw $this->problem(422, 'PREPARATION_SETTINGS_UNCHANGED', 'Preparation settings unchanged', 'No regeneration is required because the normalized preparation settings are unchanged.');
            }

            $replacementId = (string) $replacement['preparation_request_id'];
            $now = now();
            DB::table('preparation_requests')->where('id', $requestId)->update([
                'status' => 'superseded',
                'superseded_by_request_id' => $replacementId,
                'superseded_at' => $now,
                'updated_at' => $now,
            ]);

            $staleImports = DB::table('preparation_imports')
                ->where('preparation_request_id', $requestId)
                ->whereIn('status', ['staged', 'validated', 'reviewed', 'imported'])
                ->lockForUpdate()
                ->get();

            foreach ($staleImports as $staleImport) {
                $this->supersedeDraftPublication((string) $staleImport->id);
                DB::table('preparation_imports')->where('id', $staleImport->id)->update([
                    'status' => 'superseded',
                    'operation_state' => 'stale',
                    'operation_checkpoint' => 'settings_regenerated',
                    'updated_at' => $now,
                ]);
                $this->audit(
                    $user,
                    $requestId,
                    (string) $staleImport->id,
                    'import_superseded',
                    (string) $staleImport->status,
                    'superseded',
                    'Preparation settings were regenerated.',
                    ['replacement_preparation_request_id' => $replacementId],
                );
            }

            $this->audit(
                $user,
                $requestId,
                null,
                'preparation_regenerated',
                (string) $request->status,
                'superseded',
                'Preparation settings changed and require a new returned archive.',
                ['replacement_preparation_request_id' => $replacementId],
            );
            $this->outbox('preparation_request', $requestId, 'content.preparation_superseded', [
                'replacement_preparation_request_id' => $replacementId,
                'reason_code' => 'SETTINGS_CHANGED',
            ]);

            return $replacement;
        });
    }

    /**
     * @return array{accepted: bool, data: array<string, mixed>, errors: list<array{pointer: string, code: string, message: string}>}
     *
     * @throws JsonException
     */
    public function stageReturnedArchive(User $user, string $expectedRequestId, UploadedFile $archive): array
    {
        $this->assertOperator($user);
        $request = DB::table('preparation_requests')->where('id', $expectedRequestId)->first();
        if (! $request instanceof \stdClass) {
            throw $this->problem(404, 'PREPARATION_REQUEST_NOT_FOUND', 'Preparation request not found', 'The originating preparation request does not exist.');
        }
        $this->assertRequestFresh($request);

        $path = $archive->getRealPath();
        if ($path === false) {
            throw $this->problem(422, 'CONTENT_ARCHIVE_UNREADABLE', 'Content archive unreadable', 'The returned ZIP cannot be read.');
        }

        $validated = $this->archives->validate($path);
        $claimedRequestId = (string) $validated['manifest']['preparation_request_id'];
        if (! hash_equals($expectedRequestId, $claimedRequestId)) {
            throw $this->problem(
                422,
                'CONTENT_ORIGIN_REQUEST_MISMATCH',
                'Returned archive belongs to another request',
                'Upload the returned ZIP from the same preparation request that generated its prompt and bundle.',
            );
        }

        $result = $this->preparation->stage($user, $archive);
        if (! $result['accepted']) {
            return $result;
        }

        $importId = (string) $result['data']['preparation_import_id'];
        $contentJson = $this->json($validated['content_pack']);
        $contentHash = hash('sha256', $this->canonicalJson($validated['content_pack']));
        $import = DB::table('preparation_imports')->where('id', $importId)->first();
        if (! $import instanceof \stdClass) {
            throw $this->problem(500, 'CONTENT_IMPORT_NOT_FOUND', 'Content import missing', 'The validated import record could not be loaded.', true);
        }

        if (is_string($import->content_hash) && $import->content_hash !== '' && ! hash_equals($import->content_hash, $contentHash)) {
            throw $this->problem(409, 'CONTENT_SNAPSHOT_HASH_MISMATCH', 'Validated snapshot mismatch', 'The staged import already has a different validated content snapshot.');
        }

        $alreadySnapshotted = is_string($import->content_hash) && $import->content_hash !== '';
        DB::table('preparation_imports')->where('id', $importId)->update([
            'validated_content' => $contentJson,
            'content_hash' => $contentHash,
            'operation_state' => 'ready',
            'operation_checkpoint' => 'archive_staged',
            'last_error_code' => null,
            'last_error_fingerprint' => null,
            'last_error_at' => null,
            'updated_at' => now(),
        ]);

        if (! $alreadySnapshotted) {
            $this->audit(
                $user,
                $expectedRequestId,
                $importId,
                'archive_staged',
                'validating',
                'staged',
                null,
                [
                    'content_hash' => $contentHash,
                    'validated_file_count' => (int) $result['data']['validated_file_count'],
                    'validated_record_count' => (int) $result['data']['validated_record_count'],
                ],
            );
        }

        $result['data']['content_hash'] = $contentHash;

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ApiProblemException
     * @throws JsonException
     */
    public function dryRun(User $user, string $importId): array
    {
        $this->assertOperator($user);

        return DB::transaction(function () use ($user, $importId): array {
            $import = $this->lockImport($importId);
            $request = $this->lockRequestForImport($import);
            $this->assertRequestFresh($request);
            if (! in_array((string) $import->status, ['staged', 'validated', 'reviewed'], true)) {
                throw $this->invalidState($import, 'staged or validated');
            }
            $pack = $this->validatedSnapshot($import);
            $summary = $this->buildDiff($pack, $request, (string) $import->content_hash);
            $summaryHash = hash('sha256', $this->canonicalJson($summary));
            $previousStatus = (string) $import->status;
            $reviewWasInvalidated = $previousStatus === 'reviewed';
            $targetStatus = $summary['publishable'] === true ? 'validated' : 'staged';

            DB::table('preparation_imports')->where('id', $importId)->update([
                'status' => $targetStatus,
                'dry_run_summary' => $this->json($summary),
                'dry_run_hash' => $summaryHash,
                'review_decision' => $reviewWasInvalidated ? null : $import->review_decision,
                'review_reason' => $reviewWasInvalidated ? null : $import->review_reason,
                'reviewed_by' => $reviewWasInvalidated ? null : $import->reviewed_by,
                'reviewed_at' => $reviewWasInvalidated ? null : $import->reviewed_at,
                'operation_state' => $summary['publishable'] === true ? 'ready' : 'blocked',
                'operation_checkpoint' => $summary['publishable'] === true ? 'dry_run_complete' : 'dry_run_blocked',
                'last_error_code' => null,
                'last_error_fingerprint' => null,
                'last_error_at' => null,
                'updated_at' => now(),
            ]);

            $this->audit(
                $user,
                (string) $import->preparation_request_id,
                $importId,
                $summary['publishable'] === true ? 'dry_run_validated' : 'dry_run_blocked',
                $previousStatus,
                $targetStatus,
                $reviewWasInvalidated ? 'A new dry-run invalidated the previous review decision.' : null,
                [
                    'dry_run_hash' => $summaryHash,
                    'blocking_codes' => $summary['blocking_codes'],
                    'counts' => $summary['counts'],
                ],
            );

            return $summary + ['dry_run_hash' => $summaryHash, 'status' => $targetStatus];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function review(User $user, string $importId, string $decision, ?string $reason = null): array
    {
        $this->assertOperator($user);
        if (! in_array($decision, self::REVIEW_DECISIONS, true)) {
            throw $this->problem(422, 'CONTENT_REVIEW_DECISION_INVALID', 'Review decision invalid', 'Use approved, rejected, or request_fix.');
        }
        $normalizedReason = is_string($reason) ? trim($reason) : '';
        if ($decision !== 'approved' && $normalizedReason === '') {
            throw $this->problem(422, 'CONTENT_REVIEW_REASON_REQUIRED', 'Review reason required', 'Reject and request-fix decisions require an operator reason.');
        }
        if (mb_strlen($normalizedReason) > 2000) {
            throw $this->problem(422, 'CONTENT_REVIEW_REASON_TOO_LONG', 'Review reason too long', 'Review reasons are limited to 2000 characters.');
        }

        return DB::transaction(function () use ($user, $importId, $decision, $normalizedReason): array {
            $import = $this->lockImport($importId);
            $request = $this->lockRequestForImport($import);
            $this->assertRequestFresh($request);
            if ((string) $import->status !== 'validated') {
                throw $this->invalidState($import, 'validated');
            }
            $this->validatedSnapshot($import);
            if (! is_string($import->dry_run_hash) || $import->dry_run_hash === '') {
                throw $this->problem(409, 'CONTENT_DRY_RUN_REQUIRED', 'Dry-run required', 'Run and review the deterministic diff before recording a review decision.');
            }

            $now = now();
            DB::table('preparation_imports')->where('id', $importId)->update([
                'status' => 'reviewed',
                'review_decision' => $decision,
                'review_reason' => $normalizedReason === '' ? null : $normalizedReason,
                'reviewed_by' => (string) $user->getKey(),
                'reviewed_at' => $now,
                'operation_state' => $decision === 'approved' ? 'ready' : 'blocked',
                'operation_checkpoint' => 'review_'.$decision,
                'updated_at' => $now,
            ]);
            $this->audit(
                $user,
                (string) $import->preparation_request_id,
                $importId,
                'review_'.$decision,
                'validated',
                'reviewed',
                $normalizedReason === '' ? null : $normalizedReason,
                ['decision' => $decision, 'dry_run_hash' => $import->dry_run_hash],
            );
            $this->outbox('preparation_import', $importId, 'content.preparation_reviewed', [
                'preparation_request_id' => (string) $import->preparation_request_id,
                'decision' => $decision,
            ]);

            return [
                'preparation_import_id' => $importId,
                'status' => 'reviewed',
                'decision' => $decision,
                'reviewed_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * Persist a reviewed snapshot into non-published canonical rows. This is a separate,
     * transactional lifecycle step so operators can inspect imported drafts before publication.
     *
     * @return array<string, mixed>
     */
    public function importReviewed(User $user, string $importId): array
    {
        $this->assertOperator($user);
        $publication = $this->ensurePublication($user, $importId);
        if ((string) $publication->status === 'published') {
            return $this->publicationResult($publication, true);
        }
        if ((string) $publication->status === 'imported') {
            return $this->publicationResult($publication, true);
        }

        try {
            return DB::transaction(function () use ($user, $importId, $publication): array {
                $import = $this->lockImport($importId);
                if ((string) $import->status === 'imported') {
                    $current = DB::table('content_publications')->where('id', $publication->id)->lockForUpdate()->first();
                    if (! $current instanceof \stdClass) {
                        throw $this->problem(500, 'CONTENT_PUBLICATION_NOT_FOUND', 'Publication operation missing', 'The publication operation record is missing.', true);
                    }

                    return $this->publicationResult($current, true);
                }
                $request = $this->lockRequestForImport($import);
                $this->assertRequestFresh($request);
                if ((string) $import->status !== 'reviewed' || (string) $import->review_decision !== 'approved') {
                    throw $this->problem(409, 'CONTENT_APPROVAL_REQUIRED', 'Approved review required', 'Only an approved reviewed import can enter canonical draft import.');
                }
                $pack = $this->validatedSnapshot($import);
                $summary = $this->buildDiff($pack, $request, (string) $import->content_hash);
                if ($summary['publishable'] !== true || ! is_string($import->dry_run_hash) || $import->dry_run_hash === '') {
                    throw $this->problem(409, 'CONTENT_DRY_RUN_STALE', 'Dry-run is stale', 'Run the dry-run again and obtain a fresh approval before import.');
                }
                $summaryHash = hash('sha256', $this->canonicalJson($summary));
                if (! hash_equals((string) $import->dry_run_hash, $summaryHash)) {
                    throw $this->problem(409, 'CONTENT_DRY_RUN_STALE', 'Dry-run is stale', 'Canonical content changed after review. Run the dry-run and review again.');
                }

                $operation = DB::table('content_publications')->where('id', $publication->id)->lockForUpdate()->first();
                if (! $operation instanceof \stdClass) {
                    throw $this->problem(500, 'CONTENT_PUBLICATION_NOT_FOUND', 'Publication operation missing', 'The publication operation record is missing.', true);
                }
                if ((string) $operation->status === 'imported' || (string) $operation->status === 'published') {
                    return $this->publicationResult($operation, true);
                }

                $attempt = (int) $operation->attempt_count + 1;
                DB::table('content_publications')->where('id', $operation->id)->update([
                    'status' => 'importing',
                    'checkpoint' => 'canonical_draft_import',
                    'attempt_count' => $attempt,
                    'last_error_code' => null,
                    'last_error_fingerprint' => null,
                    'last_error_at' => null,
                    'updated_at' => now(),
                ]);
                DB::table('preparation_imports')->where('id', $importId)->update([
                    'operation_state' => 'running',
                    'operation_checkpoint' => 'canonical_draft_import',
                    'operation_attempts' => DB::raw('operation_attempts + 1'),
                    'updated_at' => now(),
                ]);

                $counts = $this->importPackAsDraft($pack, (string) $operation->id);
                DB::table('preparation_imports')->where('id', $importId)->update([
                    'status' => 'imported',
                    'operation_state' => 'ready',
                    'operation_checkpoint' => 'canonical_draft_imported',
                    'updated_at' => now(),
                ]);
                DB::table('content_publications')->where('id', $operation->id)->update([
                    'status' => 'imported',
                    'checkpoint' => 'canonical_draft_imported',
                    'updated_at' => now(),
                ]);
                $this->audit(
                    $user,
                    (string) $import->preparation_request_id,
                    $importId,
                    'canonical_draft_imported',
                    'reviewed',
                    'imported',
                    null,
                    ['publication_id' => (string) $operation->id, 'counts' => $counts],
                );
                $this->outbox('preparation_import', $importId, 'content.preparation_canonical_imported', [
                    'preparation_request_id' => (string) $import->preparation_request_id,
                    'publication_id' => (string) $operation->id,
                    'content_hash' => (string) $import->content_hash,
                ]);

                $fresh = DB::table('content_publications')->where('id', $operation->id)->first();
                if (! $fresh instanceof \stdClass) {
                    throw $this->problem(500, 'CONTENT_PUBLICATION_NOT_FOUND', 'Publication operation missing', 'The publication operation record is missing.', true);
                }

                return $this->publicationResult($fresh, false);
            });
        } catch (Throwable $exception) {
            $this->recordFailure($user, $importId, (string) $publication->id, 'import', $exception);
            throw $this->normalizeFailure($exception);
        }
    }

    /**
     * Publish only canonical rows that were created by this reviewed import. Reused already-
     * published rows remain unchanged. The curriculum state, import state, audit and outbox
     * publication signal commit atomically.
     *
     * @return array<string, mixed>
     */
    public function publish(User $user, string $importId): array
    {
        $this->assertOperator($user);
        $publication = DB::table('content_publications')->where('preparation_import_id', $importId)->first();
        if (! $publication instanceof \stdClass) {
            throw $this->problem(409, 'CONTENT_CANONICAL_IMPORT_REQUIRED', 'Canonical draft import required', 'Import the approved snapshot as canonical draft content before publishing it.');
        }
        if ((string) $publication->status === 'published') {
            return $this->publicationResult($publication, true);
        }

        try {
            return DB::transaction(function () use ($user, $importId, $publication): array {
                $import = $this->lockImport($importId);
                if ((string) $import->status === 'published') {
                    $current = DB::table('content_publications')->where('id', $publication->id)->lockForUpdate()->first();
                    if (! $current instanceof \stdClass) {
                        throw $this->problem(500, 'CONTENT_PUBLICATION_NOT_FOUND', 'Publication operation missing', 'The publication operation record is missing.', true);
                    }

                    return $this->publicationResult($current, true);
                }
                $request = $this->lockRequestForImport($import);
                $this->assertRequestFresh($request);
                if ((string) $import->status !== 'imported' || (string) $import->review_decision !== 'approved') {
                    throw $this->problem(409, 'CONTENT_CANONICAL_IMPORT_REQUIRED', 'Canonical draft import required', 'Only an approved import in imported state can be published.');
                }
                $this->validatedSnapshot($import);
                $operation = DB::table('content_publications')->where('id', $publication->id)->lockForUpdate()->first();
                if (! $operation instanceof \stdClass) {
                    throw $this->problem(500, 'CONTENT_PUBLICATION_NOT_FOUND', 'Publication operation missing', 'The publication operation record is missing.', true);
                }
                if ((string) $operation->status === 'published') {
                    return $this->publicationResult($operation, true);
                }
                if (! in_array((string) $operation->status, ['imported', 'failed'], true)) {
                    throw $this->problem(409, 'CONTENT_PUBLICATION_STATE_INVALID', 'Publication state invalid', 'The publication operation is not ready to publish.');
                }

                $attempt = (int) $operation->attempt_count + 1;
                DB::table('content_publications')->where('id', $operation->id)->update([
                    'status' => 'publishing',
                    'checkpoint' => 'official_publication',
                    'attempt_count' => $attempt,
                    'last_error_code' => null,
                    'last_error_fingerprint' => null,
                    'last_error_at' => null,
                    'updated_at' => now(),
                ]);
                DB::table('preparation_imports')->where('id', $importId)->update([
                    'operation_state' => 'running',
                    'operation_checkpoint' => 'official_publication',
                    'operation_attempts' => DB::raw('operation_attempts + 1'),
                    'updated_at' => now(),
                ]);

                $publishedCounts = $this->publishDraftItems((string) $operation->id);
                $supersededLessons = $this->supersedeOlderLessonVersions((string) $operation->id);
                $now = now();
                DB::table('preparation_imports')->where('id', $importId)->update([
                    'status' => 'published',
                    'published_by' => (string) $user->getKey(),
                    'published_at' => $now,
                    'operation_state' => 'succeeded',
                    'operation_checkpoint' => 'official_content_published',
                    'last_error_code' => null,
                    'last_error_fingerprint' => null,
                    'last_error_at' => null,
                    'updated_at' => $now,
                ]);
                DB::table('content_publications')->where('id', $operation->id)->update([
                    'status' => 'published',
                    'checkpoint' => 'official_content_published',
                    'published_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->audit(
                    $user,
                    (string) $import->preparation_request_id,
                    $importId,
                    'official_content_published',
                    'imported',
                    'published',
                    null,
                    [
                        'publication_id' => (string) $operation->id,
                        'published_counts' => $publishedCounts,
                        'superseded_lesson_versions' => $supersededLessons,
                    ],
                );
                $this->outbox('preparation_import', $importId, 'content.official_content_published', [
                    'preparation_request_id' => (string) $import->preparation_request_id,
                    'publication_id' => (string) $operation->id,
                    'pack_id' => is_string($import->pack_id) ? $import->pack_id : null,
                    'content_hash' => (string) $import->content_hash,
                ]);

                $fresh = DB::table('content_publications')->where('id', $operation->id)->first();
                if (! $fresh instanceof \stdClass) {
                    throw $this->problem(500, 'CONTENT_PUBLICATION_NOT_FOUND', 'Publication operation missing', 'The publication operation record is missing.', true);
                }

                return $this->publicationResult($fresh, false);
            });
        } catch (Throwable $exception) {
            $this->recordFailure($user, $importId, (string) $publication->id, 'publish', $exception);
            throw $this->normalizeFailure($exception);
        }
    }

    /** @return array<string, mixed> */
    public function requestDetails(User $user, string $requestId): array
    {
        $this->assertOperator($user);
        $row = DB::table('preparation_requests')->where('id', $requestId)->first();
        if ($row === null) {
            throw $this->problem(404, 'PREPARATION_REQUEST_NOT_FOUND', 'Preparation request not found', 'The preparation request does not exist.');
        }
        $settings = json_decode((string) $row->normalized_settings, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($settings)) {
            throw $this->problem(500, 'PREPARATION_SETTINGS_CORRUPT', 'Preparation settings corrupt', 'The stored preparation settings cannot be decoded.');
        }

        return [
            'preparation_request_id' => (string) $row->id,
            'schema_version' => (string) $row->schema_version,
            'settings_hash' => (string) $row->settings_hash,
            'status' => (string) $row->status,
            'superseded_by_request_id' => is_string($row->superseded_by_request_id) ? $row->superseded_by_request_id : null,
            'prompt' => (string) $row->prompt,
            'bundle' => [
                'manifest_binding' => [
                    'preparation_request_id' => (string) $row->id,
                    'schema_version' => (string) $row->schema_version,
                    'settings_hash' => (string) $row->settings_hash,
                ],
                'settings' => $settings,
            ],
        ];
    }

    public function assertOperator(User $user): void
    {
        if (! in_array((string) $user->role, self::OPERATOR_ROLES, true)) {
            throw $this->problem(
                403,
                'CONTENT_ROLE_REQUIRED',
                'Content role required',
                'Only an authorized Content Team or Admin account may operate official content workflows.',
            );
        }
    }

    private function ensurePublication(User $user, string $importId): \stdClass
    {
        $import = DB::table('preparation_imports')->where('id', $importId)->first();
        if (! $import instanceof \stdClass) {
            throw $this->problem(404, 'CONTENT_IMPORT_NOT_FOUND', 'Content import not found', 'The preparation import does not exist.');
        }
        $existing = DB::table('content_publications')->where('preparation_import_id', $importId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $id = (string) Str::ulid();
        $now = now();
        DB::table('content_publications')->insert([
            'id' => $id,
            'preparation_import_id' => $importId,
            'initiated_by' => (string) $user->getKey(),
            'status' => 'queued',
            'checkpoint' => 'awaiting_canonical_import',
            'attempt_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = DB::table('content_publications')->where('id', $id)->first();
        if (! $created instanceof \stdClass) {
            throw $this->problem(500, 'CONTENT_PUBLICATION_NOT_FOUND', 'Publication operation missing', 'The publication operation record could not be created.', true);
        }

        return $created;
    }

    private function lockImport(string $importId): \stdClass
    {
        $import = DB::table('preparation_imports')->where('id', $importId)->lockForUpdate()->first();
        if (! $import instanceof \stdClass) {
            throw $this->problem(404, 'CONTENT_IMPORT_NOT_FOUND', 'Content import not found', 'The preparation import does not exist.');
        }

        return $import;
    }

    private function lockRequestForImport(\stdClass $import): \stdClass
    {
        if (! is_string($import->preparation_request_id) || $import->preparation_request_id === '') {
            throw $this->problem(409, 'PREPARATION_REQUEST_NOT_FOUND', 'Preparation request not found', 'The import is not bound to an originating preparation request.');
        }
        $request = DB::table('preparation_requests')->where('id', $import->preparation_request_id)->lockForUpdate()->first();
        if (! $request instanceof \stdClass) {
            throw $this->problem(409, 'PREPARATION_REQUEST_NOT_FOUND', 'Preparation request not found', 'The originating preparation request does not exist.');
        }

        return $request;
    }

    private function assertRequestFresh(\stdClass $request): void
    {
        if ((string) $request->status === 'superseded' || $request->superseded_by_request_id !== null) {
            throw $this->problem(
                409,
                'PREPARATION_REGENERATION_REQUIRED',
                'Preparation regeneration required',
                'This request is stale because its preparation settings were replaced. Generate a new prompt and bundle before continuing.',
            );
        }
    }

    /** @return array<string, mixed> */
    private function validatedSnapshot(\stdClass $import): array
    {
        if (! is_string($import->validated_content) || $import->validated_content === '' || ! is_string($import->content_hash) || $import->content_hash === '') {
            throw $this->problem(
                409,
                'CONTENT_VALIDATED_SNAPSHOT_REQUIRED',
                'Validated content snapshot required',
                'Re-upload the returned ZIP through its originating Admin preparation request before review.',
            );
        }
        $pack = json_decode($import->validated_content, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($pack)) {
            throw $this->problem(500, 'CONTENT_VALIDATED_SNAPSHOT_CORRUPT', 'Validated content snapshot corrupt', 'The stored validated content snapshot cannot be decoded.');
        }
        $actualHash = hash('sha256', $this->canonicalJson($pack));
        if (! hash_equals($import->content_hash, $actualHash)) {
            throw $this->problem(409, 'CONTENT_SNAPSHOT_HASH_MISMATCH', 'Validated snapshot mismatch', 'The validated content snapshot no longer matches its stored hash.');
        }

        return $pack;
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array<string, mixed>
     */
    private function buildDiff(array $pack, \stdClass $request, string $contentHash): array
    {
        $counts = [
            'curriculum_nodes' => ['create' => 0, 'reuse' => 0, 'conflict' => 0],
            'lessons' => ['create' => 0, 'reuse' => 0, 'conflict' => 0],
            'questions' => ['create' => 0, 'reuse' => 0, 'conflict' => 0],
            'quizzes' => ['create' => 0, 'reuse' => 0, 'conflict' => 0],
        ];
        $blocking = [];
        $scope = is_array($pack['academic_scope'] ?? null) ? $pack['academic_scope'] : [];
        $trackReference = is_string($scope['track_reference'] ?? null) ? $scope['track_reference'] : '';
        $track = DB::table('academic_tracks')->where('code', $trackReference)->first();
        if ($track === null) {
            $blocking[] = 'CONTENT_TARGET_TRACK_MISSING';
        } elseif (
            $track->board_reference !== ($scope['board_reference'] ?? null)
            || $track->syllabus_version !== ($scope['syllabus_version'] ?? null)
            || (string) $track->year_level !== (string) ($scope['year_level'] ?? '')
        ) {
            $blocking[] = 'CONTENT_TARGET_TRACK_SCOPE_MISMATCH';
        }

        if ($track !== null) {
            $existingNodes = DB::table('curriculum_nodes')->where('academic_track_id', $track->id)->get();
            $nodeByCode = [];
            $nodeCodeById = [];
            foreach ($existingNodes as $node) {
                $code = (string) $node->code;
                if (array_key_exists($code, $nodeByCode)) {
                    $blocking[] = 'CONTENT_TARGET_NODE_AMBIGUOUS';

                    continue;
                }
                $nodeByCode[$code] = $node;
                $nodeCodeById[(string) $node->id] = $code;
            }

            foreach ($this->listOfArrays($pack['curriculum_nodes'] ?? null) as $node) {
                $reference = (string) ($node['reference'] ?? '');
                $existing = $nodeByCode[$reference] ?? null;
                if (! $existing instanceof \stdClass) {
                    $counts['curriculum_nodes']['create']++;

                    continue;
                }
                $existingParentReference = is_string($existing->parent_id) ? ($nodeCodeById[$existing->parent_id] ?? null) : null;
                $matches = (string) $existing->type === (string) ($node['type'] ?? '')
                    && $existingParentReference === ($node['parent_reference'] ?? null)
                    && $this->jsonEquals($existing->title, $node['title'] ?? null);
                if ($matches) {
                    $counts['curriculum_nodes']['reuse']++;
                } else {
                    $counts['curriculum_nodes']['conflict']++;
                    $blocking[] = 'CONTENT_IMMUTABLE_REFERENCE_CONFLICT';
                }
            }

            $packNodeReferences = [];
            foreach ($this->listOfArrays($pack['curriculum_nodes'] ?? null) as $node) {
                $packNodeReferences[(string) ($node['reference'] ?? '')] = true;
            }

            foreach ($this->listOfArrays($pack['lessons'] ?? null) as $lesson) {
                $existing = DB::table('lessons')->where('id', (string) ($lesson['id'] ?? ''))->first();
                if (! $existing instanceof \stdClass) {
                    $counts['lessons']['create']++;

                    continue;
                }
                if ($this->lessonMatches($existing, $lesson, $nodeCodeById)) {
                    $counts['lessons']['reuse']++;
                } else {
                    $counts['lessons']['conflict']++;
                    $blocking[] = 'CONTENT_IMMUTABLE_ID_CONFLICT';
                }
            }

            foreach ($this->listOfArrays($pack['questions'] ?? null) as $question) {
                $existing = DB::table('questions')->where('id', (string) ($question['id'] ?? ''))->first();
                if (! $existing instanceof \stdClass) {
                    $counts['questions']['create']++;

                    continue;
                }
                if ($this->questionMatches($existing, $question, $nodeCodeById)) {
                    $counts['questions']['reuse']++;
                } else {
                    $counts['questions']['conflict']++;
                    $blocking[] = 'CONTENT_IMMUTABLE_ID_CONFLICT';
                }
            }

            foreach ($this->listOfArrays($pack['quizzes'] ?? null) as $quiz) {
                $existing = DB::table('quizzes')->where('id', (string) ($quiz['id'] ?? ''))->first();
                if (! $existing instanceof \stdClass) {
                    $counts['quizzes']['create']++;

                    continue;
                }
                if ($this->quizMatches($existing, $quiz, $nodeCodeById)) {
                    $counts['quizzes']['reuse']++;
                } else {
                    $counts['quizzes']['conflict']++;
                    $blocking[] = 'CONTENT_IMMUTABLE_ID_CONFLICT';
                }
            }

            foreach (array_keys($packNodeReferences) as $reference) {
                if ($reference !== '' && ! array_key_exists($reference, $nodeByCode)) {
                    continue;
                }
            }
        }

        if ((string) ($pack['schema_version'] ?? '') !== (string) $request->schema_version) {
            $blocking[] = 'CONTENT_SCHEMA_VERSION_MISMATCH';
        }
        if (! is_string($request->settings_hash) || $request->settings_hash === '') {
            $blocking[] = 'CONTENT_SETTINGS_HASH_MISSING';
        }
        $blocking = array_values(array_unique($blocking));
        sort($blocking, SORT_STRING);

        return [
            'publishable' => $blocking === [],
            'schema_version' => (string) $request->schema_version,
            'settings_hash' => (string) $request->settings_hash,
            'content_hash' => $contentHash,
            'counts' => $counts,
            'blocking_codes' => $blocking,
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array<string, int>
     */
    private function importPackAsDraft(array $pack, string $publicationId): array
    {
        $scope = is_array($pack['academic_scope'] ?? null) ? $pack['academic_scope'] : [];
        $track = DB::table('academic_tracks')->where('code', (string) ($scope['track_reference'] ?? ''))->lockForUpdate()->first();
        if ($track === null) {
            throw $this->problem(409, 'CONTENT_TARGET_TRACK_MISSING', 'Academic target missing', 'The owner-controlled academic track must exist before publication.');
        }
        if (
            $track->board_reference !== ($scope['board_reference'] ?? null)
            || $track->syllabus_version !== ($scope['syllabus_version'] ?? null)
            || (string) $track->year_level !== (string) ($scope['year_level'] ?? '')
        ) {
            throw $this->problem(409, 'CONTENT_TARGET_TRACK_SCOPE_MISMATCH', 'Academic target mismatch', 'The returned content does not match the configured academic target.');
        }

        $counts = ['curriculum_nodes' => 0, 'lessons' => 0, 'questions' => 0, 'quizzes' => 0, 'reused' => 0];
        $nodeIds = [];
        $existingNodes = DB::table('curriculum_nodes')->where('academic_track_id', $track->id)->lockForUpdate()->get();
        $existingByCode = [];
        $codeById = [];
        foreach ($existingNodes as $existingNode) {
            $code = (string) $existingNode->code;
            if (array_key_exists($code, $existingByCode)) {
                throw $this->problem(409, 'CONTENT_TARGET_NODE_AMBIGUOUS', 'Academic node ambiguous', 'More than one canonical node uses the same content reference.');
            }
            $existingByCode[$code] = $existingNode;
            $codeById[(string) $existingNode->id] = $code;
            $nodeIds[$code] = (string) $existingNode->id;
        }

        $pending = $this->listOfArrays($pack['curriculum_nodes'] ?? null);
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $index => $node) {
                $reference = (string) ($node['reference'] ?? '');
                $parentReference = $node['parent_reference'] ?? null;
                if (is_string($parentReference) && ! array_key_exists($parentReference, $nodeIds)) {
                    continue;
                }
                $existing = $existingByCode[$reference] ?? null;
                if ($existing !== null) {
                    $existingParentReference = is_string($existing->parent_id) ? ($codeById[$existing->parent_id] ?? null) : null;
                    if (
                        (string) $existing->type !== (string) ($node['type'] ?? '')
                        || $existingParentReference !== $parentReference
                        || ! $this->jsonEquals($existing->title, $node['title'] ?? null)
                    ) {
                        throw $this->problem(409, 'CONTENT_IMMUTABLE_REFERENCE_CONFLICT', 'Canonical reference conflict', 'An existing curriculum reference has different canonical content.');
                    }
                    $this->recordPublicationItem($publicationId, 'curriculum_node', (string) $existing->id, 'reused');
                    $counts['reused']++;
                } else {
                    $nodeId = (string) Str::ulid();
                    DB::table('curriculum_nodes')->insert([
                        'id' => $nodeId,
                        'academic_track_id' => (string) $track->id,
                        'parent_id' => is_string($parentReference) ? $nodeIds[$parentReference] : null,
                        'code' => $reference,
                        'type' => (string) ($node['type'] ?? ''),
                        'title' => $this->json($node['title'] ?? []),
                        'status' => 'draft',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $nodeIds[$reference] = $nodeId;
                    $codeById[$nodeId] = $reference;
                    $this->recordPublicationItem($publicationId, 'curriculum_node', $nodeId, 'created');
                    $counts['curriculum_nodes']++;
                }
                unset($pending[$index]);
                $progress = true;
            }
            $pending = array_values($pending);
            if (! $progress) {
                throw $this->problem(409, 'CONTENT_NODE_DEPENDENCY_INVALID', 'Curriculum node dependency invalid', 'Curriculum parent references cannot be resolved deterministically.');
            }
        }

        foreach ($this->listOfArrays($pack['lessons'] ?? null) as $lesson) {
            $id = (string) ($lesson['id'] ?? '');
            $existing = DB::table('lessons')->where('id', $id)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! $this->lessonMatches($existing, $lesson, $codeById)) {
                    throw $this->problem(409, 'CONTENT_IMMUTABLE_ID_CONFLICT', 'Immutable content ID conflict', 'A lesson ID already exists with different canonical content.');
                }
                $this->recordPublicationItem($publicationId, 'lesson', $id, 'reused');
                $counts['reused']++;

                continue;
            }
            $nodeReference = (string) ($lesson['curriculum_node_reference'] ?? '');
            if (! array_key_exists($nodeReference, $nodeIds)) {
                throw $this->problem(409, 'CONTENT_REFERENCE_INVALID', 'Content reference invalid', 'A lesson references an unknown curriculum node.');
            }
            DB::table('lessons')->insert([
                'id' => $id,
                'curriculum_node_id' => $nodeIds[$nodeReference],
                'slug' => (string) ($lesson['slug'] ?? ''),
                'content_version' => (int) ($lesson['content_version'] ?? 0),
                'title' => $this->json($lesson['title'] ?? []),
                'status' => 'draft',
                'published_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($this->listOfArrays($lesson['blocks'] ?? null) as $block) {
                DB::table('lesson_blocks')->insert([
                    'id' => (string) ($block['id'] ?? ''),
                    'lesson_id' => $id,
                    'position' => (int) ($block['position'] ?? 0),
                    'type' => (string) ($block['type'] ?? ''),
                    'content' => $this->json($block['content'] ?? []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->recordPublicationItem($publicationId, 'lesson', $id, 'created');
            $counts['lessons']++;
        }

        foreach ($this->listOfArrays($pack['questions'] ?? null) as $question) {
            $id = (string) ($question['id'] ?? '');
            $existing = DB::table('questions')->where('id', $id)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! $this->questionMatches($existing, $question, $codeById)) {
                    throw $this->problem(409, 'CONTENT_IMMUTABLE_ID_CONFLICT', 'Immutable content ID conflict', 'A question ID already exists with different canonical content.');
                }
                $this->recordPublicationItem($publicationId, 'question', $id, 'reused');
                $counts['reused']++;

                continue;
            }
            $nodeReference = (string) ($question['curriculum_node_reference'] ?? '');
            if (! array_key_exists($nodeReference, $nodeIds)) {
                throw $this->problem(409, 'CONTENT_REFERENCE_INVALID', 'Content reference invalid', 'A question references an unknown curriculum node.');
            }
            DB::table('questions')->insert([
                'id' => $id,
                'curriculum_node_id' => $nodeIds[$nodeReference],
                'content_version' => (int) ($question['content_version'] ?? 0),
                'type' => (string) ($question['type'] ?? ''),
                'prompt' => $this->json($question['prompt'] ?? []),
                'options' => array_key_exists('options', $question) ? $this->json($question['options']) : null,
                'answer_contract' => $this->json($question['answer_contract'] ?? []),
                'explanation' => $this->json($question['explanation'] ?? []),
                'maximum_score' => $question['maximum_score'] ?? 0,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->recordPublicationItem($publicationId, 'question', $id, 'created');
            $counts['questions']++;
        }

        foreach ($this->listOfArrays($pack['quizzes'] ?? null) as $quiz) {
            $id = (string) ($quiz['id'] ?? '');
            $existing = DB::table('quizzes')->where('id', $id)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! $this->quizMatches($existing, $quiz, $codeById)) {
                    throw $this->problem(409, 'CONTENT_IMMUTABLE_ID_CONFLICT', 'Immutable content ID conflict', 'A quiz ID already exists with different canonical content.');
                }
                $this->recordPublicationItem($publicationId, 'quiz', $id, 'reused');
                $counts['reused']++;

                continue;
            }
            $nodeReference = (string) ($quiz['curriculum_node_reference'] ?? '');
            if (! array_key_exists($nodeReference, $nodeIds)) {
                throw $this->problem(409, 'CONTENT_REFERENCE_INVALID', 'Content reference invalid', 'A quiz references an unknown curriculum node.');
            }
            DB::table('quizzes')->insert([
                'id' => $id,
                'curriculum_node_id' => $nodeIds[$nodeReference],
                'kind' => (string) ($quiz['kind'] ?? ''),
                'blueprint_version' => (int) ($quiz['blueprint_version'] ?? 0),
                'title' => $this->json($quiz['title'] ?? []),
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($this->stringList($quiz['question_ids'] ?? null) as $position => $questionId) {
                DB::table('quiz_questions')->insert([
                    'quiz_id' => $id,
                    'question_id' => $questionId,
                    'source_position' => $position + 1,
                ]);
            }
            $this->recordPublicationItem($publicationId, 'quiz', $id, 'created');
            $counts['quizzes']++;
        }

        return $counts;
    }

    /** @return array<string, int> */
    private function publishDraftItems(string $publicationId): array
    {
        $counts = ['curriculum_nodes' => 0, 'lessons' => 0, 'questions' => 0, 'quizzes' => 0, 'reused' => 0];
        $items = DB::table('content_publication_items')->where('content_publication_id', $publicationId)->lockForUpdate()->get();
        foreach ($items as $item) {
            if ((string) $item->action === 'reused') {
                $counts['reused']++;

                continue;
            }
            $now = now();
            switch ((string) $item->entity_type) {
                case 'curriculum_node':
                    $updated = DB::table('curriculum_nodes')->where('id', $item->entity_id)->where('status', 'draft')->update(['status' => 'published', 'updated_at' => $now]);
                    $counts['curriculum_nodes'] += $updated;
                    break;
                case 'lesson':
                    $updated = DB::table('lessons')->where('id', $item->entity_id)->where('status', 'draft')->update(['status' => 'published', 'published_at' => $now, 'updated_at' => $now]);
                    $counts['lessons'] += $updated;
                    break;
                case 'question':
                    $updated = DB::table('questions')->where('id', $item->entity_id)->where('status', 'draft')->update(['status' => 'published', 'updated_at' => $now]);
                    $counts['questions'] += $updated;
                    break;
                case 'quiz':
                    $updated = DB::table('quizzes')->where('id', $item->entity_id)->where('status', 'draft')->update(['status' => 'published', 'updated_at' => $now]);
                    $counts['quizzes'] += $updated;
                    break;
                default:
                    throw $this->problem(500, 'CONTENT_PUBLICATION_ITEM_INVALID', 'Publication item invalid', 'A publication item uses an unsupported entity type.', true);
            }
        }

        return $counts;
    }

    private function supersedeOlderLessonVersions(string $publicationId): int
    {
        $lessonIds = DB::table('content_publication_items')
            ->where('content_publication_id', $publicationId)
            ->where('entity_type', 'lesson')
            ->pluck('entity_id')
            ->all();
        $superseded = 0;
        foreach ($lessonIds as $lessonId) {
            if (! is_string($lessonId)) {
                continue;
            }
            $lesson = DB::table('lessons')->where('id', $lessonId)->first();
            if ($lesson === null) {
                continue;
            }
            $superseded += DB::table('lessons')
                ->where('curriculum_node_id', $lesson->curriculum_node_id)
                ->where('slug', $lesson->slug)
                ->where('content_version', '<', $lesson->content_version)
                ->where('status', 'published')
                ->update(['status' => 'superseded', 'updated_at' => now()]);
        }

        return $superseded;
    }

    private function supersedeDraftPublication(string $importId): void
    {
        $publication = DB::table('content_publications')->where('preparation_import_id', $importId)->first();
        if (! $publication instanceof \stdClass || (string) $publication->status === 'published') {
            return;
        }
        $items = DB::table('content_publication_items')
            ->where('content_publication_id', $publication->id)
            ->where('action', 'created')
            ->get();
        foreach ($items as $item) {
            $table = match ((string) $item->entity_type) {
                'curriculum_node' => 'curriculum_nodes',
                'lesson' => 'lessons',
                'question' => 'questions',
                'quiz' => 'quizzes',
                default => null,
            };
            if ($table !== null) {
                DB::table($table)->where('id', $item->entity_id)->where('status', 'draft')->update(['status' => 'superseded', 'updated_at' => now()]);
            }
        }
        DB::table('content_publications')->where('id', $publication->id)->update([
            'status' => 'superseded',
            'checkpoint' => 'settings_regenerated',
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @param  array<string, string>  $codeById
     */
    private function lessonMatches(\stdClass $existing, array $lesson, array $codeById): bool
    {
        if (($codeById[(string) $existing->curriculum_node_id] ?? null) !== ($lesson['curriculum_node_reference'] ?? null)
            || (string) $existing->slug !== (string) ($lesson['slug'] ?? '')
            || (int) $existing->content_version !== (int) ($lesson['content_version'] ?? -1)
            || ! $this->jsonEquals($existing->title, $lesson['title'] ?? null)) {
            return false;
        }
        $blocks = DB::table('lesson_blocks')->where('lesson_id', $existing->id)->orderBy('position')->get();
        $expectedBlocks = $this->listOfArrays($lesson['blocks'] ?? null);
        if ($blocks->count() !== count($expectedBlocks)) {
            return false;
        }
        foreach ($expectedBlocks as $index => $block) {
            $current = $blocks->get($index);
            if ($current === null
                || (string) $current->id !== (string) ($block['id'] ?? '')
                || (int) $current->position !== (int) ($block['position'] ?? -1)
                || (string) $current->type !== (string) ($block['type'] ?? '')
                || ! $this->jsonEquals($current->content, $block['content'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, string>  $codeById
     */
    private function questionMatches(\stdClass $existing, array $question, array $codeById): bool
    {
        return ($codeById[(string) $existing->curriculum_node_id] ?? null) === ($question['curriculum_node_reference'] ?? null)
            && (int) $existing->content_version === (int) ($question['content_version'] ?? -1)
            && (string) $existing->type === (string) ($question['type'] ?? '')
            && $this->jsonEquals($existing->prompt, $question['prompt'] ?? null)
            && $this->jsonEquals($existing->options, $question['options'] ?? null)
            && $this->jsonEquals($existing->answer_contract, $question['answer_contract'] ?? null)
            && $this->jsonEquals($existing->explanation, $question['explanation'] ?? null)
            && (float) $existing->maximum_score === (float) ($question['maximum_score'] ?? -1);
    }

    /**
     * @param  array<string, mixed>  $quiz
     * @param  array<string, string>  $codeById
     */
    private function quizMatches(\stdClass $existing, array $quiz, array $codeById): bool
    {
        if (($codeById[(string) $existing->curriculum_node_id] ?? null) !== ($quiz['curriculum_node_reference'] ?? null)
            || (string) $existing->kind !== (string) ($quiz['kind'] ?? '')
            || (int) $existing->blueprint_version !== (int) ($quiz['blueprint_version'] ?? -1)
            || ! $this->jsonEquals($existing->title, $quiz['title'] ?? null)) {
            return false;
        }
        $currentQuestionIds = DB::table('quiz_questions')
            ->where('quiz_id', $existing->id)
            ->orderBy('source_position')
            ->pluck('question_id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        return $currentQuestionIds === $this->stringList($quiz['question_ids'] ?? null);
    }

    private function jsonEquals(mixed $storedJson, mixed $expected): bool
    {
        if ($storedJson === null) {
            return $expected === null;
        }
        $decoded = is_string($storedJson) ? json_decode($storedJson, true, flags: JSON_THROW_ON_ERROR) : $storedJson;

        return $this->canonicalJson($decoded) === $this->canonicalJson($expected);
    }

    private function recordPublicationItem(string $publicationId, string $entityType, string $entityId, string $action): void
    {
        $exists = DB::table('content_publication_items')
            ->where('content_publication_id', $publicationId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->exists();
        if ($exists) {
            return;
        }
        $now = now();
        DB::table('content_publication_items')->insert([
            'id' => (string) Str::ulid(),
            'content_publication_id' => $publicationId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function recordFailure(User $user, string $importId, string $publicationId, string $phase, Throwable $exception): void
    {
        $errorCode = $exception instanceof ApiProblemException ? $exception->problemCode : 'CONTENT_PUBLICATION_INTERNAL_ERROR';
        $fingerprint = hash('sha256', $exception::class.'|'.$errorCode.'|'.$phase);
        DB::transaction(function () use ($user, $importId, $publicationId, $phase, $errorCode, $fingerprint): void {
            $publication = DB::table('content_publications')->where('id', $publicationId)->lockForUpdate()->first();
            if ($publication !== null && (string) $publication->status !== 'published') {
                DB::table('content_publications')->where('id', $publicationId)->update([
                    'status' => 'failed',
                    'checkpoint' => $phase.'_failed',
                    'attempt_count' => (int) $publication->attempt_count + 1,
                    'last_error_code' => $errorCode,
                    'last_error_fingerprint' => $fingerprint,
                    'last_error_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $import = DB::table('preparation_imports')->where('id', $importId)->lockForUpdate()->first();
            if (! $import instanceof \stdClass || (string) $import->status === 'published') {
                return;
            }
            DB::table('preparation_imports')->where('id', $importId)->update([
                'operation_state' => 'failed',
                'operation_checkpoint' => $phase.'_failed',
                'operation_attempts' => (int) $import->operation_attempts + 1,
                'last_error_code' => $errorCode,
                'last_error_fingerprint' => $fingerprint,
                'last_error_at' => now(),
                'updated_at' => now(),
            ]);
            $requestId = is_string($import->preparation_request_id) ? $import->preparation_request_id : null;
            $this->audit($user, $requestId, $importId, $phase.'_failed', (string) $import->status, (string) $import->status, null, [
                'publication_id' => $publicationId,
                'error_code' => $errorCode,
                'error_fingerprint' => $fingerprint,
            ]);
            $this->outbox('preparation_import', $importId, 'content.official_content_operation_failed', [
                'preparation_request_id' => $requestId,
                'publication_id' => $publicationId,
                'phase' => $phase,
                'error_code' => $errorCode,
            ]);
        });
    }

    private function normalizeFailure(Throwable $exception): ApiProblemException
    {
        if ($exception instanceof ApiProblemException) {
            return $exception;
        }

        return $this->problem(
            500,
            'CONTENT_PUBLICATION_INTERNAL_ERROR',
            'Content publication failed',
            'The operation failed safely. Review the operator checkpoint and retry after the underlying issue is resolved.',
            true,
        );
    }

    /** @return array<string, mixed> */
    private function publicationResult(\stdClass $publication, bool $replayed): array
    {
        return [
            'publication_id' => (string) $publication->id,
            'preparation_import_id' => (string) $publication->preparation_import_id,
            'status' => (string) $publication->status,
            'checkpoint' => is_string($publication->checkpoint) ? $publication->checkpoint : null,
            'attempt_count' => (int) $publication->attempt_count,
            'replayed' => $replayed,
            'last_error_code' => is_string($publication->last_error_code) ? $publication->last_error_code : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(
        ?User $user,
        ?string $requestId,
        ?string $importId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $reason,
        array $metadata,
    ): void {
        DB::table('content_workflow_audits')->insert([
            'id' => (string) Str::ulid(),
            'preparation_request_id' => $requestId,
            'preparation_import_id' => $importId,
            'actor_id' => $user !== null ? (string) $user->getKey() : null,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $this->json($metadata),
            'created_at' => now(),
        ]);
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

    private function invalidState(\stdClass $import, string $expected): ApiProblemException
    {
        return $this->problem(
            409,
            'CONTENT_WORKFLOW_STATE_INVALID',
            'Content workflow state invalid',
            'Expected '.$expected.' state, received '.(string) $import->status.'.',
        );
    }

    private function problem(int $status, string $code, string $title, string $detail, bool $retryable = false): ApiProblemException
    {
        return new ApiProblemException($status, $code, $title, $detail, $retryable);
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
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

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
