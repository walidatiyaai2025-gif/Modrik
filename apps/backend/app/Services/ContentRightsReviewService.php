<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContentRightsReviewService
{
    /** @var list<string> */
    private const OPERATOR_ROLES = ['admin', 'content_team'];

    /**
     * @return array<string, mixed>
     */
    public function review(User $user, string $importId, string $decision, ?string $evidenceReference, ?string $note): array
    {
        if (! in_array((string) $user->role, self::OPERATOR_ROLES, true)) {
            throw $this->problem(403, 'CONTENT_OPERATOR_FORBIDDEN', 'Content operator access required', 'Only Admin or Content Team may review content rights.');
        }
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw $this->problem(422, 'CONTENT_RIGHTS_DECISION_INVALID', 'Rights decision invalid', 'Use approved or rejected.');
        }

        $evidenceReference = is_string($evidenceReference) ? trim($evidenceReference) : '';
        $note = is_string($note) ? trim($note) : '';
        if ($decision === 'approved' && $evidenceReference === '') {
            throw $this->problem(422, 'CONTENT_RIGHTS_EVIDENCE_REQUIRED', 'Rights evidence required', 'Approval requires a concrete evidence reference; do not claim rights without evidence.');
        }
        if ($decision === 'rejected' && $note === '') {
            throw $this->problem(422, 'CONTENT_RIGHTS_REJECTION_REASON_REQUIRED', 'Rights rejection reason required', 'A rejection reason is required.');
        }
        if (mb_strlen($evidenceReference) > 500 || mb_strlen($note) > 2000) {
            throw $this->problem(422, 'CONTENT_RIGHTS_REVIEW_TOO_LONG', 'Rights review fields are too long', 'Evidence references are limited to 500 characters and notes to 2000 characters.');
        }

        return DB::transaction(function () use ($user, $importId, $decision, $evidenceReference, $note): array {
            $import = DB::table('preparation_imports')->where('id', $importId)->lockForUpdate()->first();
            if (! $import instanceof \stdClass) {
                throw $this->problem(404, 'CONTENT_IMPORT_NOT_FOUND', 'Content import not found', 'The staged content import does not exist.');
            }
            if ((string) $import->status !== 'rights_review') {
                throw $this->problem(409, 'CONTENT_RIGHTS_REVIEW_STATE_INVALID', 'Rights review state invalid', 'Rights decisions are only available while the import is awaiting rights review.');
            }

            $now = now();
            $targetStatus = $decision === 'approved' ? 'staged' : 'rights_review';
            DB::table('preparation_imports')->where('id', $importId)->update([
                'rights_review_status' => $decision,
                'rights_evidence_reference' => $evidenceReference === '' ? null : $evidenceReference,
                'rights_review_note' => $note === '' ? null : $note,
                'rights_reviewed_by' => (string) $user->getKey(),
                'rights_reviewed_at' => $now,
                'status' => $targetStatus,
                'operation_state' => $decision === 'approved' ? 'ready' : 'blocked',
                'operation_checkpoint' => 'rights_review_'.$decision,
                'updated_at' => $now,
            ]);

            DB::table('content_workflow_audits')->insert([
                'id' => (string) Str::ulid(),
                'preparation_request_id' => is_string($import->preparation_request_id) ? $import->preparation_request_id : null,
                'preparation_import_id' => $importId,
                'actor_id' => (string) $user->getKey(),
                'action' => 'rights_review_'.$decision,
                'from_status' => 'rights_review',
                'to_status' => $targetStatus,
                'reason' => $note === '' ? null : $note,
                'metadata' => json_encode([
                    'rights_status' => is_string($import->rights_status) ? $import->rights_status : null,
                    'evidence_reference' => $evidenceReference === '' ? null : $evidenceReference,
                    'decision' => $decision,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);

            DB::table('outbox_events')->insert([
                'id' => (string) Str::ulid(),
                'aggregate_type' => 'preparation_import',
                'aggregate_id' => $importId,
                'event_type' => 'content.rights_reviewed',
                'payload' => json_encode([
                    'preparation_request_id' => is_string($import->preparation_request_id) ? $import->preparation_request_id : null,
                    'decision' => $decision,
                    'rights_status' => is_string($import->rights_status) ? $import->rights_status : null,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'preparation_import_id' => $importId,
                'rights_review_status' => $decision,
                'status' => $targetStatus,
                'reviewed_at' => $now->toIso8601String(),
            ];
        });
    }

    private function problem(int $status, string $code, string $title, string $detail): ApiProblemException
    {
        return new ApiProblemException($status, $code, $title, $detail);
    }
}
