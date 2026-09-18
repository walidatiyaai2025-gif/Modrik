<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

final class QuestionBankWorkbenchService
{
    /** @var list<string> */
    private const QUESTION_TYPES = [
        'multiple_choice',
        'true_false',
        'numeric',
        'fill_blank',
        'short_answer',
        'matching',
        'ordering',
        'multi_select',
        'image_question',
        'reading_comprehension',
        'multi_step_math',
    ];

    /** @var list<string> */
    private const DIFFICULTIES = ['Easy', 'Medium', 'Hard', 'Revision', 'Exam-style'];

    /** @var list<string> */
    private const SOURCE_KINDS = ['book', 'pdf', 'worksheet', 'exam', 'other'];

    /** @var list<string> */
    private const RIGHTS_STATES = ['pending', 'approved', 'rejected'];

    public function __construct(private readonly ContentAdminWorkflowService $contentWorkflow) {}

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function stage(User $user, string $json): array
    {
        $this->contentWorkflow->assertOperator($user);
        if (strlen($json) > 20_000_000) {
            throw $this->problem(413, 'QUESTION_BANK_PACK_TOO_LARGE', 'Question Bank pack too large', 'Question Bank JSON is limited to 20 MB.');
        }

        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->problem(422, 'QUESTION_BANK_JSON_INVALID', 'Question Bank JSON invalid', 'The uploaded file is not valid JSON.');
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw $this->problem(422, 'QUESTION_BANK_SCHEMA_INVALID', 'Question Bank schema invalid', 'The uploaded JSON must be an object.');
        }

        $requestId = $decoded['preparation_request_id'] ?? null;
        $settingsHash = $decoded['settings_hash'] ?? null;
        $packId = $decoded['pack_id'] ?? null;
        if (! is_string($requestId) || ! Str::isUlid($requestId)) {
            throw $this->problem(422, 'QUESTION_BANK_BINDING_INVALID', 'Question Bank binding invalid', 'preparation_request_id must be a ULID.');
        }
        if (! is_string($settingsHash) || preg_match('/^[a-f0-9]{64}$/D', $settingsHash) !== 1) {
            throw $this->problem(422, 'QUESTION_BANK_BINDING_INVALID', 'Question Bank binding invalid', 'settings_hash must be a lowercase SHA-256 digest.');
        }
        if (! is_string($packId) || ! Str::isUlid($packId)) {
            throw $this->problem(422, 'QUESTION_BANK_PACK_ID_INVALID', 'Question Bank pack invalid', 'pack_id must be a ULID.');
        }

        $request = DB::table('preparation_requests')->where('id', $requestId)->first();
        if (! $request instanceof stdClass) {
            throw $this->problem(404, 'PREPARATION_REQUEST_NOT_FOUND', 'Preparation request not found', 'The Question Bank pack is not bound to a known preparation request.');
        }
        if ((string) $request->status === 'superseded' || is_string($request->superseded_by_request_id)) {
            throw $this->problem(409, 'PREPARATION_REGENERATION_REQUIRED', 'Preparation regeneration required', 'The Question Bank pack is bound to a superseded preparation request.');
        }
        if (! hash_equals((string) $request->settings_hash, $settingsHash)) {
            throw $this->problem(409, 'QUESTION_BANK_SETTINGS_MISMATCH', 'Question Bank settings mismatch', 'The pack settings_hash does not match the authoritative preparation request.');
        }

        $contentHash = hash('sha256', $this->canonicalJson($decoded));
        $existing = DB::table('question_bank_imports')->where('pack_id', $packId)->first();
        if ($existing instanceof stdClass) {
            if (! hash_equals((string) $existing->content_hash, $contentHash)) {
                throw $this->problem(409, 'QUESTION_BANK_PACK_ID_REUSED', 'Question Bank pack ID reused', 'The pack_id already belongs to different content.');
            }

            return $this->importResult((string) $existing->id, true);
        }

        $validation = $this->validatePack($decoded, $request);
        $now = now();
        $importId = (string) Str::ulid();

        DB::transaction(function () use ($user, $decoded, $requestId, $settingsHash, $packId, $contentHash, $validation, $now, $importId): void {
            $status = $validation['errors'] === [] ? 'needs_review' : 'rejected';
            DB::table('question_bank_imports')->insert([
                'id' => $importId,
                'preparation_request_id' => $requestId,
                'uploaded_by' => (string) $user->getKey(),
                'pack_id' => $packId,
                'schema_version' => (string) ($decoded['schema_version'] ?? ''),
                'settings_hash' => $settingsHash,
                'prompt_id' => (string) (($decoded['prompt']['id'] ?? '')),
                'prompt_version' => (string) (($decoded['prompt']['version'] ?? '')),
                'content_hash' => $contentHash,
                'status' => $status,
                'validation_summary' => $this->json([
                    'errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                    'unknowns' => $validation['unknowns'],
                    'item_count' => count($validation['items']),
                    'mapping_required' => count(array_filter($validation['items'], static fn (array $item): bool => $item['scope_state'] !== 'mapped')),
                ]),
                'raw_payload' => $this->canonicalJson($decoded),
                'review_reason' => $status === 'rejected' ? 'Schema or semantic validation failed.' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($status === 'rejected') {
                $this->audit($user, $importId, null, null, 'import_rejected', null, 'rejected', 'Schema or semantic validation failed.', [
                    'errors' => $validation['errors'],
                ]);

                return;
            }

            $sourceIds = [];
            foreach ($validation['sources'] as $source) {
                $sourceRow = DB::table('question_bank_source_materials')->where('source_key', $source['source_id'])->first();
                if (! $sourceRow instanceof stdClass) {
                    $sourceMaterialId = (string) Str::ulid();
                    DB::table('question_bank_source_materials')->insert([
                        'id' => $sourceMaterialId,
                        'source_key' => $source['source_id'],
                        'name' => $source['source_name'],
                        'kind' => 'other',
                        'rights_status' => 'pending',
                        'created_by' => (string) $user->getKey(),
                        'updated_by' => (string) $user->getKey(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $sourceMaterialId = (string) $sourceRow->id;
                }
                $sourceIds[$source['source_id']] = $sourceMaterialId;
                DB::table('question_bank_import_sources')->insert([
                    'question_bank_import_id' => $importId,
                    'source_material_id' => $sourceMaterialId,
                    'source_name' => $source['source_name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($validation['items'] as $item) {
                DB::table('question_bank_import_items')->insert([
                    'id' => (string) Str::ulid(),
                    'question_bank_import_id' => $importId,
                    'external_id' => $item['external_id'],
                    'source_material_id' => $sourceIds[$item['source_id']] ?? null,
                    'curriculum_node_id' => $item['curriculum_node_id'],
                    'type' => $item['type'],
                    'language' => $item['language'],
                    'difficulty' => $item['difficulty'],
                    'source_page' => $item['source_page'],
                    'scope_state' => $item['scope_state'],
                    'status' => 'staged',
                    'content_hash' => $item['content_hash'],
                    'payload' => $this->json($item['payload']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->audit($user, $importId, null, null, 'import_staged', null, 'needs_review', null, [
                'item_count' => count($validation['items']),
                'source_count' => count($validation['sources']),
            ]);
        });

        return $this->importResult($importId, false);
    }

    /** @return array<string, mixed> */
    public function review(User $user, string $importId, string $decision, ?string $reason = null): array
    {
        $this->contentWorkflow->assertOperator($user);
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw $this->problem(422, 'QUESTION_BANK_REVIEW_INVALID', 'Question Bank review invalid', 'Use approved or rejected.');
        }
        $reason = trim((string) $reason);
        if ($decision === 'rejected' && $reason === '') {
            throw $this->problem(422, 'QUESTION_BANK_REVIEW_REASON_REQUIRED', 'Review reason required', 'Rejected Question Bank imports require a reason.');
        }

        DB::transaction(function () use ($user, $importId, $decision, $reason): void {
            $import = $this->lockImport($importId);
            if ((string) $import->status !== 'needs_review') {
                throw $this->invalidState($import, 'needs_review');
            }
            if ($decision === 'approved') {
                $unmapped = DB::table('question_bank_import_items')
                    ->where('question_bank_import_id', $importId)
                    ->where('scope_state', '!=', 'mapped')
                    ->count();
                if ($unmapped > 0) {
                    throw $this->problem(409, 'QUESTION_BANK_SCOPE_MAPPING_REQUIRED', 'Scope mapping required', 'Map every staged question to an existing curriculum node before approval.');
                }
            }

            $now = now();
            DB::table('question_bank_imports')->where('id', $importId)->update([
                'status' => $decision,
                'review_reason' => $reason === '' ? null : $reason,
                'reviewed_by' => (string) $user->getKey(),
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('question_bank_import_items')->where('question_bank_import_id', $importId)->update([
                'status' => $decision,
                'updated_at' => $now,
            ]);
            $this->audit($user, $importId, null, null, 'review_'.$decision, 'needs_review', $decision, $reason === '' ? null : $reason);
        });

        return $this->importResult($importId, false);
    }

    /** @return array<string, mixed> */
    public function reclassifyItem(
        User $user,
        string $itemId,
        string $difficulty,
        string $curriculumNodeId,
        string $reason,
    ): array {
        $this->contentWorkflow->assertOperator($user);
        if (! in_array($difficulty, self::DIFFICULTIES, true)) {
            throw $this->problem(422, 'QUESTION_BANK_DIFFICULTY_INVALID', 'Difficulty invalid', 'Use one of the canonical Question Bank difficulty values.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 2000) {
            throw $this->problem(422, 'QUESTION_BANK_RECLASSIFICATION_REASON_INVALID', 'Reclassification reason invalid', 'Enter a reason between 8 and 2000 characters.');
        }

        $importId = DB::transaction(function () use ($user, $itemId, $difficulty, $curriculumNodeId, $reason): string {
            $item = DB::table('question_bank_import_items')->where('id', $itemId)->lockForUpdate()->first();
            if (! $item instanceof stdClass) {
                throw $this->problem(404, 'QUESTION_BANK_ITEM_NOT_FOUND', 'Question Bank item not found', 'The staged question does not exist.');
            }
            $import = $this->lockImport((string) $item->question_bank_import_id);
            if (! in_array((string) $import->status, ['needs_review', 'approved'], true)) {
                throw $this->invalidState($import, 'needs_review or approved');
            }
            $node = $this->nodeAllowedForImport($import, $curriculumNodeId);
            $payload = $this->decodeMap((string) $item->payload);
            $objective = $payload['learning_objective'] ?? null;
            if (is_string($objective) && trim($objective) !== '' && (string) $node->type !== 'skill') {
                throw $this->problem(422, 'QUESTION_BANK_SKILL_MAPPING_REQUIRED', 'Skill mapping required', 'Questions with a learning objective must be mapped to a skill node.');
            }

            $now = now();
            DB::table('question_bank_import_items')->where('id', $itemId)->update([
                'difficulty' => $difficulty,
                'curriculum_node_id' => $curriculumNodeId,
                'scope_state' => 'mapped',
                'status' => 'staged',
                'updated_at' => $now,
            ]);
            if ((string) $import->status === 'approved') {
                DB::table('question_bank_imports')->where('id', $import->id)->update([
                    'status' => 'needs_review',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_reason' => null,
                    'updated_at' => $now,
                ]);
                DB::table('question_bank_import_items')
                    ->where('question_bank_import_id', $import->id)
                    ->where('id', '!=', $itemId)
                    ->update(['status' => 'staged', 'updated_at' => $now]);
            }
            $this->audit($user, (string) $import->id, $itemId, null, 'item_reclassified', (string) $item->status, 'staged', $reason, [
                'difficulty' => $difficulty,
                'curriculum_node_id' => $curriculumNodeId,
            ]);

            return (string) $import->id;
        });

        return $this->importResult($importId, false);
    }

    /** @return array<string, mixed> */
    public function updateSourceMaterial(
        User $user,
        string $sourceMaterialId,
        string $kind,
        ?string $reference,
        string $rightsStatus,
        ?string $note,
    ): array {
        $this->contentWorkflow->assertOperator($user);
        if (! in_array($kind, self::SOURCE_KINDS, true)) {
            throw $this->problem(422, 'QUESTION_BANK_SOURCE_KIND_INVALID', 'Source kind invalid', 'Use book, pdf, worksheet, exam, or other.');
        }
        if (! in_array($rightsStatus, self::RIGHTS_STATES, true)) {
            throw $this->problem(422, 'QUESTION_BANK_RIGHTS_STATUS_INVALID', 'Rights status invalid', 'Use pending, approved, or rejected.');
        }
        $reference = trim((string) $reference);
        $note = trim((string) $note);
        if (mb_strlen($reference) > 500 || mb_strlen($note) > 2000) {
            throw $this->problem(422, 'QUESTION_BANK_SOURCE_METADATA_INVALID', 'Source metadata invalid', 'Source reference or rights note exceeds the allowed length.');
        }

        DB::transaction(function () use ($user, $sourceMaterialId, $kind, $reference, $rightsStatus, $note): void {
            $source = DB::table('question_bank_source_materials')->where('id', $sourceMaterialId)->lockForUpdate()->first();
            if (! $source instanceof stdClass) {
                throw $this->problem(404, 'QUESTION_BANK_SOURCE_NOT_FOUND', 'Source material not found', 'The source material does not exist.');
            }
            $now = now();
            DB::table('question_bank_source_materials')->where('id', $sourceMaterialId)->update([
                'kind' => $kind,
                'reference' => $reference === '' ? null : $reference,
                'rights_status' => $rightsStatus,
                'rights_note' => $note === '' ? null : $note,
                'updated_by' => (string) $user->getKey(),
                'updated_at' => $now,
            ]);
            $this->audit($user, null, null, $sourceMaterialId, 'source_material_updated', (string) $source->rights_status, $rightsStatus, $note === '' ? null : $note, [
                'kind' => $kind,
                'reference' => $reference === '' ? null : $reference,
            ]);

            if ($rightsStatus !== 'approved') {
                $publishedImportIds = DB::table('question_bank_import_sources as links')
                    ->join('question_bank_imports as imports', 'imports.id', '=', 'links.question_bank_import_id')
                    ->where('links.source_material_id', $sourceMaterialId)
                    ->where('imports.status', 'published')
                    ->pluck('imports.id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all();

                foreach ($publishedImportIds as $publishedImportId) {
                    $questionIds = DB::table('question_bank_import_items')
                        ->where('question_bank_import_id', $publishedImportId)
                        ->whereNotNull('canonical_question_id')
                        ->pluck('canonical_question_id');
                    if ($questionIds->isNotEmpty()) {
                        DB::table('questions')->whereIn('id', $questionIds)->update([
                            'status' => 'suspended',
                            'updated_at' => $now,
                        ]);
                    }
                    DB::table('question_bank_import_items')->where('question_bank_import_id', $publishedImportId)->update([
                        'status' => 'suspended',
                        'updated_at' => $now,
                    ]);
                    DB::table('question_bank_imports')->where('id', $publishedImportId)->update([
                        'status' => 'suspended',
                        'suspended_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $this->audit($user, $publishedImportId, null, $sourceMaterialId, 'rights_auto_suspended', 'published', 'suspended', 'Source rights are no longer approved.');
                }
            }
        });

        $row = DB::table('question_bank_source_materials')->where('id', $sourceMaterialId)->first();

        return $row instanceof stdClass ? (array) $row : [];
    }

    /** @return array<string, mixed> */
    public function publish(User $user, string $importId): array
    {
        $this->contentWorkflow->assertOperator($user);

        DB::transaction(function () use ($user, $importId): void {
            $import = $this->lockImport($importId);
            if ((string) $import->status === 'published') {
                return;
            }
            $fromStatus = (string) $import->status;
            if (! in_array($fromStatus, ['approved', 'suspended'], true)) {
                throw $this->invalidState($import, 'approved or suspended');
            }
            $this->assertSourcesRightsApproved($importId);

            $items = DB::table('question_bank_import_items')
                ->where('question_bank_import_id', $importId)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();
            if ($items->isEmpty()) {
                throw $this->problem(409, 'QUESTION_BANK_IMPORT_EMPTY', 'Question Bank import empty', 'No staged questions are available to publish.');
            }
            $now = now();
            foreach ($items as $item) {
                if ((string) $item->scope_state !== 'mapped' || ! is_string($item->curriculum_node_id)) {
                    throw $this->problem(409, 'QUESTION_BANK_SCOPE_MAPPING_REQUIRED', 'Scope mapping required', 'Map every question before publication.');
                }
                $payload = $this->decodeMap((string) $item->payload);
                $node = DB::table('curriculum_nodes')->where('id', $item->curriculum_node_id)->first();
                if (! $node instanceof stdClass) {
                    throw $this->problem(409, 'QUESTION_BANK_SCOPE_MAPPING_INVALID', 'Scope mapping invalid', 'A mapped curriculum node is unavailable.');
                }

                $objectiveId = $this->resolveLearningObjective($payload, $node, $now);
                $questionValues = $this->canonicalQuestionValues($payload, $item, $objectiveId, $user, $now);
                $questionId = is_string($item->canonical_question_id) ? $item->canonical_question_id : (string) Str::ulid();
                if (is_string($item->canonical_question_id)) {
                    DB::table('questions')->where('id', $questionId)->update($questionValues);
                } else {
                    DB::table('questions')->insert([
                        'id' => $questionId,
                        ...$questionValues,
                        'created_at' => $now,
                    ]);
                }
                DB::table('question_bank_import_items')->where('id', $item->id)->update([
                    'learning_objective_id' => $objectiveId,
                    'canonical_question_id' => $questionId,
                    'status' => 'published',
                    'updated_at' => $now,
                ]);
            }

            DB::table('question_bank_imports')->where('id', $importId)->update([
                'status' => 'published',
                'published_by' => (string) $user->getKey(),
                'published_at' => $now,
                'suspended_at' => null,
                'updated_at' => $now,
            ]);
            $this->audit($user, $importId, null, null, 'published', $fromStatus, 'published', null, [
                'question_count' => $items->count(),
            ]);
        });

        return $this->importResult($importId, false);
    }

    /** @return array<string, mixed> */
    public function unpublish(User $user, string $importId, string $reason): array
    {
        return $this->deactivate($user, $importId, 'unpublish', 'approved', 'draft', $reason);
    }

    /** @return array<string, mixed> */
    public function suspend(User $user, string $importId, string $reason): array
    {
        return $this->deactivate($user, $importId, 'suspend', 'suspended', 'suspended', $reason);
    }

    /** @return array<string, mixed> */
    public function archive(User $user, string $importId, string $reason): array
    {
        $this->contentWorkflow->assertOperator($user);
        $reason = $this->requiredReason($reason);

        DB::transaction(function () use ($user, $importId, $reason): void {
            $import = $this->lockImport($importId);
            if ((string) $import->status === 'archived') {
                return;
            }
            $from = (string) $import->status;
            $now = now();
            $questionIds = DB::table('question_bank_import_items')
                ->where('question_bank_import_id', $importId)
                ->whereNotNull('canonical_question_id')
                ->pluck('canonical_question_id');
            if ($questionIds->isNotEmpty()) {
                DB::table('questions')->whereIn('id', $questionIds)->update(['status' => 'archived', 'updated_at' => $now]);
            }
            DB::table('question_bank_import_items')->where('question_bank_import_id', $importId)->update([
                'status' => 'archived',
                'updated_at' => $now,
            ]);
            DB::table('question_bank_imports')->where('id', $importId)->update([
                'status' => 'archived',
                'archived_at' => $now,
                'updated_at' => $now,
            ]);
            $this->audit($user, $importId, null, null, 'archived', $from, 'archived', $reason);
        });

        return $this->importResult($importId, false);
    }

    /**
     * @param  list<string>  $importIds
     * @return array<string, mixed>
     */
    public function bulk(User $user, array $importIds, string $action, string $reason): array
    {
        $this->contentWorkflow->assertOperator($user);
        $importIds = array_values(array_unique(array_filter($importIds, static fn (mixed $id): bool => is_string($id) && Str::isUlid($id))));
        if ($importIds === [] || count($importIds) > 100) {
            throw $this->problem(422, 'QUESTION_BANK_BULK_SELECTION_INVALID', 'Bulk selection invalid', 'Select between 1 and 100 Question Bank imports.');
        }
        $reason = $this->requiredReason($reason);
        $results = [];
        foreach ($importIds as $importId) {
            $beforeStatus = DB::table('question_bank_imports')->where('id', $importId)->value('status');
            $results[$importId] = match ($action) {
                'approve' => $this->review($user, $importId, 'approved', $reason),
                'reject' => $this->review($user, $importId, 'rejected', $reason),
                'publish' => $this->publish($user, $importId),
                'unpublish' => $this->unpublish($user, $importId, $reason),
                'suspend' => $this->suspend($user, $importId, $reason),
                'archive' => $this->archive($user, $importId, $reason),
                default => throw $this->problem(422, 'QUESTION_BANK_BULK_ACTION_INVALID', 'Bulk action invalid', 'The requested bulk action is not supported.'),
            };
            $afterStatus = $results[$importId]['import']['status'] ?? null;
            $this->audit(
                $user,
                $importId,
                null,
                null,
                'bulk_'.$action,
                is_string($beforeStatus) ? $beforeStatus : null,
                is_string($afterStatus) ? $afterStatus : null,
                $reason,
                ['bulk_action' => $action],
            );
        }

        return ['action' => $action, 'count' => count($results), 'results' => $results];
    }

    /** @return array<string, mixed> */
    public function importResult(string $importId, bool $replayed): array
    {
        $import = DB::table('question_bank_imports')->where('id', $importId)->first();
        if (! $import instanceof stdClass) {
            throw $this->problem(404, 'QUESTION_BANK_IMPORT_NOT_FOUND', 'Question Bank import not found', 'The Question Bank import does not exist.');
        }
        $items = DB::table('question_bank_import_items')
            ->where('question_bank_import_id', $importId)
            ->orderBy('created_at')
            ->get()
            ->map(static fn (stdClass $row): array => (array) $row)
            ->all();
        $sources = DB::table('question_bank_import_sources as links')
            ->join('question_bank_source_materials as sources', 'sources.id', '=', 'links.source_material_id')
            ->where('links.question_bank_import_id', $importId)
            ->orderBy('sources.name')
            ->get([
                'sources.id',
                'sources.source_key',
                'sources.name',
                'sources.kind',
                'sources.reference',
                'sources.rights_status',
                'sources.rights_note',
            ])
            ->map(static fn (stdClass $row): array => (array) $row)
            ->all();

        return [
            'replayed' => $replayed,
            'import' => (array) $import,
            'items' => $items,
            'sources' => $sources,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function imports(int $limit = 100): array
    {
        $rows = DB::table('question_bank_imports')
            ->orderByDesc('created_at')
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->map(static fn (stdClass $row): array => (array) $row)
            ->values()
            ->all();

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /** @return array<string, mixed> */
    public function exportJson(string $importId): array
    {
        $import = $this->readImport($importId);

        return $this->decodeMap((string) $import->raw_payload);
    }

    /** @return list<array<string, int|string|null>> */
    public function exportCsvRows(string $importId): array
    {
        $this->readImport($importId);

        $rows = DB::table('question_bank_import_items as items')
            ->leftJoin('question_bank_source_materials as sources', 'sources.id', '=', 'items.source_material_id')
            ->where('items.question_bank_import_id', $importId)
            ->orderBy('items.created_at')
            ->get([
                'items.external_id',
                'items.type',
                'items.language',
                'items.difficulty',
                'items.status',
                'items.scope_state',
                'items.source_page',
                'items.canonical_question_id',
                'sources.source_key',
            ])
            ->map(static fn (stdClass $row): array => (array) $row)
            ->values()
            ->all();

        /** @var list<array<string, int|string|null>> $rows */
        return $rows;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{errors: list<array{code: string, pointer: string, message: string}>, warnings: list<string>, unknowns: list<string>, sources: list<array{source_id: string, source_name: string}>, items: list<array<string, mixed>>}
     */
    private function validatePack(array $decoded, stdClass $request): array
    {
        $errors = [];
        $error = static function (string $code, string $pointer, string $message) use (&$errors): void {
            $errors[] = ['code' => $code, 'pointer' => $pointer, 'message' => $message];
        };

        if (($decoded['schema_version'] ?? null) !== 'modrik-question-bank-v1') {
            $error('SCHEMA_VERSION_UNSUPPORTED', '/schema_version', 'schema_version must be modrik-question-bank-v1.');
        }
        $prompt = $decoded['prompt'] ?? null;
        if (! is_array($prompt) || ($prompt['id'] ?? null) !== 'MODRIK_QUESTION_BANK_MASTER_V1' || ($prompt['version'] ?? null) !== '1.0.0') {
            $error('PROMPT_VERSION_UNSUPPORTED', '/prompt', 'The pack must use MODRIK_QUESTION_BANK_MASTER_V1 version 1.0.0.');
        }
        if (! is_string($decoded['generated_at'] ?? null) || strtotime((string) $decoded['generated_at']) === false) {
            $error('GENERATED_AT_INVALID', '/generated_at', 'generated_at must be an ISO-compatible date-time string.');
        }

        $sources = $decoded['sources'] ?? null;
        $normalizedSources = [];
        $sourceKeys = [];
        if (! is_array($sources) || ! array_is_list($sources) || $sources === [] || count($sources) > 100) {
            $error('SOURCES_INVALID', '/sources', 'sources must contain between 1 and 100 entries.');
        } else {
            foreach ($sources as $index => $source) {
                if (! is_array($source)) {
                    $error('SOURCE_INVALID', '/sources/'.$index, 'Source must be an object.');

                    continue;
                }
                $sourceId = $source['source_id'] ?? null;
                $sourceName = $source['source_name'] ?? null;
                if (! is_string($sourceId) || trim($sourceId) === '' || mb_strlen($sourceId) > 200) {
                    $error('SOURCE_ID_INVALID', '/sources/'.$index.'/source_id', 'source_id is required and limited to 200 characters.');

                    continue;
                }
                if (! is_string($sourceName) || trim($sourceName) === '' || mb_strlen($sourceName) > 500) {
                    $error('SOURCE_NAME_INVALID', '/sources/'.$index.'/source_name', 'source_name is required and limited to 500 characters.');

                    continue;
                }
                if (isset($sourceKeys[$sourceId])) {
                    $error('SOURCE_DUPLICATE', '/sources/'.$index.'/source_id', 'source_id must be unique within the pack.');

                    continue;
                }
                $sourceKeys[$sourceId] = true;
                $normalizedSources[] = ['source_id' => $sourceId, 'source_name' => trim($sourceName)];
            }
        }

        $warnings = $this->stringList($decoded['warnings'] ?? [], 100, $error, '/warnings');
        $unknowns = $this->stringList($decoded['unknowns'] ?? [], 100, $error, '/unknowns');

        $settings = $this->decodeMap((string) $request->normalized_settings);
        $scope = is_array($settings['academic_scope'] ?? null) ? $settings['academic_scope'] : [];
        $trackReference = is_string($scope['track_reference'] ?? null) ? $scope['track_reference'] : null;
        $yearLevel = is_string($scope['year_level'] ?? null) ? $scope['year_level'] : null;
        $track = $trackReference === null ? null : DB::table('academic_tracks')->where('code', $trackReference)->first();

        $items = $decoded['items'] ?? null;
        $normalizedItems = [];
        $externalIds = [];
        $packHashes = [];
        if (! is_array($items) || ! array_is_list($items) || $items === [] || count($items) > 5000) {
            $error('ITEMS_INVALID', '/items', 'items must contain between 1 and 5000 questions.');
        } else {
            foreach ($items as $index => $item) {
                $pointer = '/items/'.$index;
                if (! is_array($item)) {
                    $error('ITEM_INVALID', $pointer, 'Question item must be an object.');

                    continue;
                }
                $externalId = $item['id'] ?? null;
                $type = $item['type'] ?? null;
                $language = $item['language'] ?? null;
                $difficulty = $item['difficulty'] ?? null;
                $questionText = $item['question_text'] ?? null;
                $explanation = $item['explanation'] ?? null;
                $answerContract = $item['answer_contract'] ?? null;
                $academicScope = $item['academic_scope'] ?? null;
                $source = $item['source'] ?? null;
                $provenance = $item['provenance'] ?? null;

                if (! is_string($externalId) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/D', $externalId) !== 1) {
                    $error('ITEM_ID_INVALID', $pointer.'/id', 'Question id is invalid.');

                    continue;
                }
                if (isset($externalIds[$externalId])) {
                    $error('ITEM_ID_DUPLICATE', $pointer.'/id', 'Question id must be unique within the pack.');

                    continue;
                }
                $externalIds[$externalId] = true;
                if (! is_string($type) || ! in_array($type, self::QUESTION_TYPES, true)) {
                    $error('QUESTION_TYPE_INVALID', $pointer.'/type', 'Question type is not supported.');
                }
                if (! is_string($language) || preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $language) !== 1) {
                    $error('QUESTION_LANGUAGE_INVALID', $pointer.'/language', 'Question language is invalid.');
                }
                if (! is_string($difficulty) || ! in_array($difficulty, self::DIFFICULTIES, true)) {
                    $error('QUESTION_DIFFICULTY_INVALID', $pointer.'/difficulty', 'Question difficulty is invalid.');
                }
                if (! is_string($questionText) || trim($questionText) === '' || mb_strlen($questionText) > 20000) {
                    $error('QUESTION_TEXT_INVALID', $pointer.'/question_text', 'question_text is required and limited to 20000 characters.');
                }
                if (! is_string($explanation) || trim($explanation) === '' || mb_strlen($explanation) > 10000) {
                    $error('QUESTION_EXPLANATION_INVALID', $pointer.'/explanation', 'explanation is required and limited to 10000 characters.');
                }
                if (! is_array($answerContract) || array_is_list($answerContract)) {
                    $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract', 'answer_contract must be an object.');
                }
                if (! is_array($academicScope) || array_is_list($academicScope)) {
                    $error('ACADEMIC_SCOPE_INVALID', $pointer.'/academic_scope', 'academic_scope must be an object.');
                    $academicScope = [];
                }
                if (! is_array($source) || array_is_list($source)) {
                    $error('SOURCE_LINK_INVALID', $pointer.'/source', 'source must be an object.');
                    $source = [];
                }
                if (! is_array($provenance) || array_is_list($provenance)) {
                    $error('PROVENANCE_INVALID', $pointer.'/provenance', 'provenance must be an object.');
                    $provenance = [];
                }

                $sourceId = $source['source_id'] ?? null;
                if (! is_string($sourceId) || ! isset($sourceKeys[$sourceId])) {
                    $error('SOURCE_LINK_UNKNOWN', $pointer.'/source/source_id', 'Question source_id must reference a declared pack source.');
                }
                $sourcePage = $source['source_page'] ?? null;
                if ($sourcePage !== null && (! is_int($sourcePage) || $sourcePage < 1)) {
                    $error('SOURCE_PAGE_INVALID', $pointer.'/source/source_page', 'source_page must be null or an integer >= 1.');
                }
                if (! in_array($provenance['derivation'] ?? null, ['generated_derivative', 'source_verbatim'], true)) {
                    $error('PROVENANCE_DERIVATION_INVALID', $pointer.'/provenance/derivation', 'provenance.derivation is invalid.');
                }

                $itemTrack = $academicScope['track_reference'] ?? null;
                if ($itemTrack !== null && (! is_string($itemTrack) || $trackReference === null || ! hash_equals($trackReference, $itemTrack))) {
                    $error('ACADEMIC_TRACK_MISMATCH', $pointer.'/academic_scope/track_reference', 'Question track_reference must match the preparation request when supplied.');
                }
                $itemYear = $academicScope['academic_year'] ?? null;
                if ($itemYear !== null && (! is_string($itemYear) || $yearLevel === null || ! hash_equals($yearLevel, $itemYear))) {
                    $error('ACADEMIC_YEAR_MISMATCH', $pointer.'/academic_scope/academic_year', 'Question academic_year must match the preparation request when supplied.');
                }

                $this->validateTypeContract($type, $item, $pointer, $error);

                $contentHash = hash('sha256', $this->canonicalJson([
                    'academic_scope' => $academicScope,
                    'learning_objective' => $item['learning_objective'] ?? null,
                    'type' => $type,
                    'language' => $language,
                    'question_text' => $questionText,
                    'options' => $item['options'] ?? null,
                    'answer_contract' => $answerContract,
                ]));
                if (isset($packHashes[$contentHash])) {
                    $error('QUESTION_DUPLICATE_IN_PACK', $pointer, 'This question duplicates another item in the same pack.');
                }
                $packHashes[$contentHash] = true;
                if (DB::table('question_bank_import_items')
                    ->where('content_hash', $contentHash)
                    ->whereNotIn('status', ['rejected', 'archived'])
                    ->exists()) {
                    $error('QUESTION_DUPLICATE_EXISTING', $pointer, 'This question already exists in another active Question Bank import.');
                }

                $node = $this->resolveScopeNode($track, $academicScope);
                $objective = $item['learning_objective'] ?? null;
                $scopeState = $node instanceof stdClass ? 'mapped' : 'mapping_required';
                if (is_string($objective) && trim($objective) !== '' && $node instanceof stdClass && (string) $node->type !== 'skill') {
                    $scopeState = 'mapping_required';
                    $node = null;
                }

                $normalizedItems[] = [
                    'external_id' => $externalId,
                    'source_id' => is_string($sourceId) ? $sourceId : '',
                    'source_page' => is_int($sourcePage) ? $sourcePage : null,
                    'type' => is_string($type) ? $type : '',
                    'language' => is_string($language) ? $language : '',
                    'difficulty' => is_string($difficulty) ? $difficulty : '',
                    'curriculum_node_id' => $node instanceof stdClass ? (string) $node->id : null,
                    'scope_state' => $scopeState,
                    'content_hash' => $contentHash,
                    'payload' => $item,
                ];
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'unknowns' => $unknowns,
            'sources' => $normalizedSources,
            'items' => $normalizedItems,
        ];
    }

    /**
     * @param  callable(string, string, string): void  $error
     * @return list<string>
     */
    private function stringList(mixed $value, int $max, callable $error, string $pointer): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > $max) {
            $error('STRING_LIST_INVALID', $pointer, 'Expected a bounded list of strings.');

            return [];
        }
        $result = [];
        foreach ($value as $index => $item) {
            if (! is_string($item) || trim($item) === '' || mb_strlen($item) > 1000) {
                $error('STRING_LIST_ITEM_INVALID', $pointer.'/'.$index, 'List entry must be a non-empty bounded string.');

                continue;
            }
            $result[] = trim($item);
        }

        return $result;
    }

    /** @param callable(string, string, string): void $error */
    private function validateTypeContract(mixed $type, array $item, string $pointer, callable $error): void
    {
        $contract = $item['answer_contract'] ?? [];
        $options = $item['options'] ?? null;
        if (in_array($type, ['multiple_choice', 'multi_select', 'ordering'], true)) {
            if (! is_array($options) || ! array_is_list($options) || count($options) < 2 || count($options) > 50) {
                $error('QUESTION_OPTIONS_INVALID', $pointer.'/options', 'This question type requires between 2 and 50 options.');

                return;
            }
            $optionIds = [];
            foreach ($options as $index => $option) {
                if (! is_array($option) || ! is_string($option['id'] ?? null) || ! is_string($option['text'] ?? null)) {
                    $error('QUESTION_OPTION_INVALID', $pointer.'/options/'.$index, 'Each option requires id and text.');

                    continue;
                }
                if (isset($optionIds[$option['id']])) {
                    $error('QUESTION_OPTION_DUPLICATE', $pointer.'/options/'.$index.'/id', 'Option ids must be unique.');
                }
                $optionIds[$option['id']] = true;
            }
            if ($type === 'multiple_choice' && (! is_string($contract['correct_option_id'] ?? null) || ! isset($optionIds[$contract['correct_option_id']]))) {
                $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract/correct_option_id', 'multiple_choice requires a valid correct_option_id.');
            }
            if ($type === 'multi_select') {
                $correct = $contract['correct_option_ids'] ?? null;
                if (! is_array($correct) || ! array_is_list($correct) || $correct === []) {
                    $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract/correct_option_ids', 'multi_select requires correct_option_ids.');
                }
            }
            if ($type === 'ordering') {
                $order = $contract['correct_order'] ?? null;
                if (! is_array($order) || ! array_is_list($order) || count($order) !== count($optionIds)) {
                    $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract/correct_order', 'ordering requires a complete correct_order.');
                }
            }

            return;
        }

        if ($type === 'true_false' && ! is_bool($contract['correct'] ?? null)) {
            $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract/correct', 'true_false requires a boolean correct value.');
        } elseif ($type === 'numeric' && ! is_int($contract['value'] ?? null) && ! is_float($contract['value'] ?? null)) {
            $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract/value', 'numeric requires a numeric value.');
        } elseif (in_array($type, ['fill_blank', 'short_answer'], true)) {
            $answers = $contract['accepted_answers'] ?? null;
            if (! is_array($answers) || ! array_is_list($answers) || $answers === []) {
                $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract/accepted_answers', 'Text questions require accepted_answers.');
            }
        } elseif ($type === 'matching') {
            $pairs = $contract['correct_pairs'] ?? null;
            if (! is_array($pairs) || ! array_is_list($pairs) || $pairs === []) {
                $error('ANSWER_CONTRACT_INVALID', $pointer.'/answer_contract/correct_pairs', 'matching requires correct_pairs.');
            }
        }
    }

    /** @param  array<string, mixed>  $scope */
    private function resolveScopeNode(?stdClass $track, array $scope): ?stdClass
    {
        if (! $track instanceof stdClass) {
            return null;
        }

        foreach (['skill', 'topic', 'unit', 'subject'] as $type) {
            $code = $scope[$type] ?? null;
            if (! is_string($code) || trim($code) === '') {
                continue;
            }
            $node = DB::table('curriculum_nodes')
                ->where('academic_track_id', $track->id)
                ->where('type', $type)
                ->where('code', trim($code))
                ->where('status', 'published')
                ->first();
            if ($node instanceof stdClass) {
                return $node;
            }
        }

        return null;
    }

    private function nodeAllowedForImport(stdClass $import, string $nodeId): stdClass
    {
        if (! Str::isUlid($nodeId)) {
            throw $this->problem(422, 'QUESTION_BANK_SCOPE_MAPPING_INVALID', 'Scope mapping invalid', 'curriculum_node_id must be a ULID.');
        }
        $request = DB::table('preparation_requests')->where('id', $import->preparation_request_id)->first();
        if (! $request instanceof stdClass) {
            throw $this->problem(409, 'PREPARATION_REQUEST_NOT_FOUND', 'Preparation request not found', 'The bound preparation request is unavailable.');
        }
        $settings = $this->decodeMap((string) $request->normalized_settings);
        $scope = is_array($settings['academic_scope'] ?? null) ? $settings['academic_scope'] : [];
        $trackReference = $scope['track_reference'] ?? null;
        if (! is_string($trackReference)) {
            throw $this->problem(409, 'QUESTION_BANK_TRACK_REQUIRED', 'Academic track required', 'The preparation request has no canonical academic track.');
        }

        $node = DB::table('curriculum_nodes')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->where('curriculum_nodes.id', $nodeId)
            ->where('curriculum_nodes.status', 'published')
            ->where('academic_tracks.code', $trackReference)
            ->select('curriculum_nodes.*')
            ->first();
        if (! $node instanceof stdClass) {
            throw $this->problem(422, 'QUESTION_BANK_SCOPE_MAPPING_INVALID', 'Scope mapping invalid', 'The selected node is not a published node in the preparation track.');
        }

        return $node;
    }

    /** @param  array<string, mixed>  $payload */
    private function resolveLearningObjective(array $payload, stdClass $node, mixed $now): ?string
    {
        $objective = $payload['learning_objective'] ?? null;
        if (! is_string($objective) || trim($objective) === '') {
            return null;
        }
        if ((string) $node->type !== 'skill') {
            throw $this->problem(409, 'QUESTION_BANK_SKILL_MAPPING_REQUIRED', 'Skill mapping required', 'A learning objective can only be attached after mapping the question to a skill node.');
        }
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $objective) ?? $objective));
        $code = 'qb-'.substr(hash('sha256', $normalized), 0, 24);
        $existing = DB::table('learning_objectives')
            ->where('skill_node_id', $node->id)
            ->where('code', $code)
            ->first();
        if ($existing instanceof stdClass) {
            return (string) $existing->id;
        }

        $id = (string) Str::ulid();
        $language = is_string($payload['language'] ?? null) ? $payload['language'] : 'en';
        DB::table('learning_objectives')->insert([
            'id' => $id,
            'skill_node_id' => (string) $node->id,
            'code' => $code,
            'title' => $this->json([$language => trim($objective)]),
            'description' => null,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalQuestionValues(array $payload, stdClass $item, ?string $objectiveId, User $user, mixed $now): array
    {
        $language = (string) $item->language;
        $options = $payload['options'] ?? null;
        $canonicalOptions = null;
        if (is_array($options)) {
            $canonicalOptions = [];
            foreach ($options as $option) {
                if (is_array($option) && is_string($option['id'] ?? null) && is_string($option['text'] ?? null)) {
                    $canonicalOptions[] = [
                        'id' => $option['id'],
                        'label' => [$language => $option['text']],
                    ];
                }
            }
        }

        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $provenance = is_array($payload['provenance'] ?? null) ? $payload['provenance'] : [];
        $hints = is_array($payload['hints'] ?? null) ? array_values(array_filter($payload['hints'], 'is_string')) : [];

        return [
            'curriculum_node_id' => (string) $item->curriculum_node_id,
            'learning_objective_id' => $objectiveId,
            'content_version' => 1,
            'type' => (string) $item->type,
            'difficulty' => (string) $item->difficulty,
            'generation_kind' => 'static',
            'template_contract' => null,
            'source_provenance' => $this->json([
                'source_id' => $source['source_id'] ?? null,
                'source_page' => $source['source_page'] ?? null,
                'notes' => $source['notes'] ?? null,
                'derivation' => $provenance['derivation'] ?? null,
                'confidence' => $provenance['confidence'] ?? null,
                'generation_notes' => $provenance['generation_notes'] ?? null,
                'question_bank_import_id' => (string) $item->question_bank_import_id,
                'external_id' => (string) $item->external_id,
            ]),
            'review_state' => 'approved',
            'review_version' => 1,
            'reviewed_by' => (string) $user->getKey(),
            'reviewed_at' => $now,
            'published_by' => (string) $user->getKey(),
            'published_at' => $now,
            'prompt' => $this->json([$language => (string) ($payload['question_text'] ?? '')]),
            'options' => $canonicalOptions === null ? null : $this->json($canonicalOptions),
            'answer_contract' => $this->json($payload['answer_contract'] ?? []),
            'explanation' => $this->json([$language => (string) ($payload['explanation'] ?? '')]),
            'maximum_score' => 1,
            'assessment_metadata' => $this->json([
                'hints' => $hints,
                'question_bank_import_id' => (string) $item->question_bank_import_id,
                'external_id' => (string) $item->external_id,
            ]),
            'option_shuffle_safe' => false,
            'status' => 'published',
            'updated_at' => $now,
        ];
    }

    /** @return array<string, mixed> */
    private function deactivate(User $user, string $importId, string $action, string $targetStatus, string $questionStatus, string $reason): array
    {
        $this->contentWorkflow->assertOperator($user);
        $reason = $this->requiredReason($reason);

        DB::transaction(function () use ($user, $importId, $action, $targetStatus, $questionStatus, $reason): void {
            $import = $this->lockImport($importId);
            if ((string) $import->status !== 'published') {
                throw $this->invalidState($import, 'published');
            }
            $now = now();
            $questionIds = DB::table('question_bank_import_items')
                ->where('question_bank_import_id', $importId)
                ->whereNotNull('canonical_question_id')
                ->pluck('canonical_question_id');
            if ($questionIds->isNotEmpty()) {
                DB::table('questions')->whereIn('id', $questionIds)->update(['status' => $questionStatus, 'updated_at' => $now]);
            }
            DB::table('question_bank_import_items')->where('question_bank_import_id', $importId)->update([
                'status' => $targetStatus,
                'updated_at' => $now,
            ]);
            DB::table('question_bank_imports')->where('id', $importId)->update([
                'status' => $targetStatus,
                'suspended_at' => $targetStatus === 'suspended' ? $now : null,
                'updated_at' => $now,
            ]);
            $this->audit($user, $importId, null, null, $action, 'published', $targetStatus, $reason);
        });

        return $this->importResult($importId, false);
    }

    private function assertSourcesRightsApproved(string $importId): void
    {
        $blocked = DB::table('question_bank_import_sources as links')
            ->join('question_bank_source_materials as sources', 'sources.id', '=', 'links.source_material_id')
            ->where('links.question_bank_import_id', $importId)
            ->where('sources.rights_status', '!=', 'approved')
            ->count();
        if ($blocked > 0) {
            throw $this->problem(409, 'QUESTION_BANK_RIGHTS_APPROVAL_REQUIRED', 'Source rights approval required', 'Every linked source material must have approved rights before publication.');
        }
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 2000) {
            throw $this->problem(422, 'QUESTION_BANK_REASON_INVALID', 'Reason invalid', 'Enter a reason between 8 and 2000 characters.');
        }

        return $reason;
    }

    private function lockImport(string $importId): stdClass
    {
        if (! Str::isUlid($importId)) {
            throw $this->problem(404, 'QUESTION_BANK_IMPORT_NOT_FOUND', 'Question Bank import not found', 'The Question Bank import does not exist.');
        }
        $import = DB::table('question_bank_imports')->where('id', $importId)->lockForUpdate()->first();
        if (! $import instanceof stdClass) {
            throw $this->problem(404, 'QUESTION_BANK_IMPORT_NOT_FOUND', 'Question Bank import not found', 'The Question Bank import does not exist.');
        }

        return $import;
    }

    private function readImport(string $importId): stdClass
    {
        if (! Str::isUlid($importId)) {
            throw $this->problem(404, 'QUESTION_BANK_IMPORT_NOT_FOUND', 'Question Bank import not found', 'The Question Bank import does not exist.');
        }
        $import = DB::table('question_bank_imports')->where('id', $importId)->first();
        if (! $import instanceof stdClass) {
            throw $this->problem(404, 'QUESTION_BANK_IMPORT_NOT_FOUND', 'Question Bank import not found', 'The Question Bank import does not exist.');
        }

        return $import;
    }

    private function invalidState(stdClass $import, string $expected): ApiProblemException
    {
        return $this->problem(
            409,
            'QUESTION_BANK_STATE_INVALID',
            'Question Bank state invalid',
            'Expected '.$expected.' but import is '.(string) $import->status.'.',
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(
        ?User $user,
        ?string $importId,
        ?string $itemId,
        ?string $sourceMaterialId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $reason,
        array $metadata = [],
    ): void {
        DB::table('question_bank_workflow_audits')->insert([
            'id' => (string) Str::ulid(),
            'question_bank_import_id' => $importId,
            'question_bank_import_item_id' => $itemId,
            'source_material_id' => $sourceMaterialId,
            'actor_id' => $user?->getKey(),
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $this->json($metadata),
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function decodeMap(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw $this->problem(500, 'QUESTION_BANK_STORED_JSON_INVALID', 'Stored Question Bank data invalid', 'Stored Question Bank JSON must be an object.', true);
        }

        return $decoded;
    }

    private function canonicalJson(mixed $value): string
    {
        return $this->json($this->canonicalize($value));
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

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function problem(
        int $status,
        string $code,
        string $title,
        string $detail,
        bool $retryable = false,
    ): ApiProblemException {
        return new ApiProblemException($status, $code, $title, $detail, $retryable);
    }
}
