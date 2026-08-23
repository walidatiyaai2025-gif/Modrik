import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import yaml from "yaml";

const root = path.resolve(import.meta.dirname, "..");
const readYaml = (relativePath) => yaml.parse(fs.readFileSync(path.join(root, relativePath), "utf8"));
const readJson = (relativePath) => JSON.parse(fs.readFileSync(path.join(root, relativePath), "utf8"));

const openapi = readYaml("docs/api/openapi.yaml");
const requirements = readYaml("docs/requirements/requirements-index.yaml");
const acceptance = readYaml("docs/requirements/acceptance-criteria.yaml");
const locked = readYaml("docs/requirements/locked-decisions.yaml");
const preparationSchema = readJson("contracts/content/preparation-request.schema.json");
const returnedPackSchema = readJson("contracts/content/returned-pack-manifest.schema.json");

assert.equal(openapi.openapi, "3.1.0");
assert.equal(requirements.schema_version, "1.0.0");
assert.equal(acceptance.schema_version, "1.0.0");
assert.equal(locked.schema_version, "1.0.0");
assert.equal(preparationSchema.$schema, "https://json-schema.org/draft/2020-12/schema");
assert.equal(returnedPackSchema.$schema, "https://json-schema.org/draft/2020-12/schema");

const requirementIds = new Set(requirements.requirements.map(({ id }) => id));
const acceptanceIds = new Set(acceptance.acceptance_criteria.map(({ id }) => id));
for (const criterion of acceptance.acceptance_criteria) {
  for (const requirementId of criterion.requirement_ids) {
    assert(requirementIds.has(requirementId), `Acceptance ${criterion.id} references missing ${requirementId}`);
  }
}
for (const requirement of requirements.requirements) {
  for (const acceptanceId of requirement.acceptance_criteria) {
    assert(acceptanceIds.has(acceptanceId), `Requirement ${requirement.id} references missing ${acceptanceId}`);
  }
}

const expectedRequirementIds = [
  "REQ-P0-001",
  "REQ-P0-002",
  "REQ-P0-003",
  "REQ-P0-004",
  "REQ-P0-005",
  "REQ-P0-006",
  "REQ-P0-007",
  "REQ-P0-008",
  "REQ-P0-009",
  "REQ-P0-010",
  "REQ-P0-011",
  "REQ-P0-012",
  "REQ-P0-013",
  "REQ-P0-014",
  "REQ-P0-015",
];
assert.deepEqual([...requirementIds], expectedRequirementIds);

const expectedAcceptanceIds = [
  "AC-P0-001",
  "AC-P0-002",
  "AC-P0-003",
  "AC-P0-004",
  "AC-P0-005",
  "AC-P0-006",
  "AC-P0-007",
  "AC-P0-008",
  "AC-P0-009",
  "AC-P0-010",
  "AC-P0-022",
  "AC-P0-011",
  "AC-P0-012",
  "AC-P0-013",
  "AC-P0-014",
  "AC-P0-015",
  "AC-P0-016",
  "AC-P0-017",
  "AC-P0-018",
  "AC-P0-019",
  "AC-P0-020",
  "AC-P0-021",
];
assert.deepEqual([...acceptanceIds], expectedAcceptanceIds);

const requirementById = new Map(requirements.requirements.map((requirement) => [requirement.id, requirement]));
const academicRequirement = requirementById.get("REQ-P0-002");
assert(academicRequirement);
assert(academicRequirement.acceptance_criteria.includes("AC-P0-010"));
assert(academicRequirement.acceptance_criteria.includes("AC-P0-022"));

const lockedIds = new Set(locked.decisions.map(({ id }) => id));
assert(lockedIds.has("LOCK-ACADEMIC-TRACK"));
assert(lockedIds.has("LOCK-AI-OPTIONAL"));
assert(lockedIds.has("LOCK-HOSTING-CPANEL"));
assert(lockedIds.has("LOCK-DATABASE-MARIADB"));

assert.equal(preparationSchema.additionalProperties, false);
assert.equal(returnedPackSchema.additionalProperties, false);
assert(preparationSchema.required.includes("schema_version"));
assert(preparationSchema.required.includes("preparation_request_id"));
assert(preparationSchema.required.includes("settings_hash"));
assert(preparationSchema.required.includes("academic_scope"));
assert(preparationSchema.required.includes("input_files"));
assert(preparationSchema.required.includes("output_contract"));
assert(preparationSchema.properties.academic_scope.required.includes("track_reference"));
assert(preparationSchema.properties.academic_scope.required.includes("year_level"));
assert.equal(preparationSchema.properties.academic_scope.additionalProperties, false);
assert.equal(preparationSchema.properties.input_files.items.additionalProperties, false);
assert.equal(preparationSchema.properties.output_contract.additionalProperties, false);
assert(returnedPackSchema.required.includes("manifest_version"));
assert(returnedPackSchema.required.includes("preparation_request_id"));
assert(returnedPackSchema.required.includes("settings_hash"));
assert(returnedPackSchema.required.includes("schema_version"));
assert(returnedPackSchema.required.includes("files"));
assert.equal(returnedPackSchema.properties.files.items.additionalProperties, false);

assert.equal(openapi.paths["/health"].get.operationId, "healthCheck");
assert.deepEqual(openapi.paths["/health"].get.security, []);
assert.equal(openapi.paths["/v1/auth/register"].post.operationId, "registerAccount");
assert.deepEqual(openapi.paths["/v1/auth/register"].post.security, []);
assert.equal(openapi.paths["/v1/auth/login"].post.operationId, "loginAccount");
assert.deepEqual(openapi.paths["/v1/auth/login"].post.security, []);
assert.equal(openapi.paths["/v1/auth/email/verify"].post.operationId, "verifyEmail");
assert.deepEqual(openapi.paths["/v1/auth/email/verify"].post.security, []);
assert.equal(openapi.paths["/v1/auth/password/recovery"].post.operationId, "requestPasswordRecovery");
assert.deepEqual(openapi.paths["/v1/auth/password/recovery"].post.security, []);
assert.equal(openapi.paths["/v1/auth/password/reset"].post.operationId, "resetPassword");
assert.deepEqual(openapi.paths["/v1/auth/password/reset"].post.security, []);
assert.equal(openapi.paths["/v1/auth/email/verification"].post.operationId, "resendEmailVerification");
assert.equal(openapi.paths["/v1/auth/reauthenticate"].post.operationId, "reauthenticateAccount");
assert.equal(openapi.paths["/v1/auth/password"].put.operationId, "changeAccountPassword");
assert.equal(openapi.paths["/v1/auth/sessions"].get.operationId, "listAccountSessions");
assert.equal(openapi.paths["/v1/auth/sessions"].delete.operationId, "revokeAllAccountSessions");
assert.equal(openapi.paths["/v1/auth/sessions/current"].delete.operationId, "revokeCurrentAccountSession");
assert.equal(openapi.paths["/v1/auth/sessions/others"].delete.operationId, "revokeOtherAccountSessions");
assert.equal(openapi.paths["/v1/auth/account"].delete.operationId, "deleteAccount");
assert.equal(openapi.components.schemas.AuthDeleteRequest.properties.confirmation.const, "DELETE");
assert.deepEqual(openapi.components.parameters.AuthProvider.schema.enum, ["google", "apple"]);
assert.equal(openapi.paths["/v1/auth/providers/{provider}/login-intents"].post.operationId, "createProviderLoginIntent");
assert.deepEqual(openapi.paths["/v1/auth/providers/{provider}/login-intents"].post.security, []);
assert.equal(openapi.paths["/v1/auth/providers/{provider}/link-intents"].post.operationId, "createProviderLinkIntent");
assert.equal(openapi.paths["/v1/auth/providers/{provider}/callback"].post.operationId, "completeProviderIntent");
assert.deepEqual(openapi.paths["/v1/auth/providers/{provider}/callback"].post.security, []);
assert.equal(openapi.components.schemas.ProviderCallbackRequest.additionalProperties, false);
assert.equal(openapi.components.schemas.ProviderCallbackRequest.properties.id_token.writeOnly, true);
assert.equal(openapi.paths["/v1/auth/providers/{provider}"].delete.operationId, "unlinkProviderIdentity");
assert.equal(openapi.components.schemas.StartAttemptRequest.additionalProperties, false);
assert(!Object.hasOwn(openapi.components.schemas.StartAttemptRequest.properties, "seed"));
assert(!Object.hasOwn(openapi.components.schemas.StartAttemptRequest.properties, "question_order"));
assert.equal(openapi.components.parameters.IdempotencyKey.required, true);
assert(openapi.components.schemas.LessonResponse.properties.data.required.includes("practice_quiz_id"));
assert(openapi.components.schemas.AttemptResultResponse.properties.data.required.includes("attempt"));
assert(openapi.paths["/v1/attempts/{attemptId}/submit"].post.responses["200"].headers["Idempotency-Replayed"]);
assert.equal(openapi.paths["/v1/academic-tracks"].get.operationId, "listAvailableAcademicTracks");
assert.equal(openapi.paths["/v1/academic-tracks"].get.parameters, undefined);
assert.equal(openapi.paths["/v1/academic-tracks"].get.responses["200"].headers["Cache-Control"].schema.const, "no-store, private");
assert.equal(openapi.components.schemas.AcademicTrackCatalogueItem.additionalProperties, false);
assert.deepEqual(openapi.components.schemas.AcademicTrackCatalogueItem.required, ["id", "year", "labels"]);
assert.deepEqual(openapi.components.schemas.AcademicTrackCatalogueItem.properties.year.required, ["key", "label"]);
assert.equal(openapi.components.schemas.AcademicTrackCatalogueItem.properties.year.additionalProperties, false);
assert.deepEqual(openapi.components.schemas.AcademicTrackLabels.required, ["ar", "en", "fr"]);
assert.equal(openapi.components.schemas.AcademicTrackLabels.additionalProperties, false);
for (const internalField of ["code", "board_reference", "syllabus_version", "year_level", "is_fixture", "sort_order"]) {
  assert(!Object.hasOwn(openapi.components.schemas.AcademicTrackCatalogueItem.properties, internalField));
}
assert.equal(openapi.components.schemas.AcademicContextMutationRequest.additionalProperties, false);
assert(openapi.components.schemas.Attempt.required.includes("academic_context_id"));
assert(openapi.paths["/v1/academic-context/reset"].post.parameters.some(({ $ref }) => $ref?.endsWith("/IdempotencyKey")));
assert(openapi.paths["/v1/academic-context/reset"].post.responses["200"].headers["Idempotency-Replayed"]);
assert.equal(openapi.paths["/v1/sync/answers"].post.operationId, "syncAttemptAnswers");
assert.equal(openapi.paths["/v1/sync/answers"].post.parameters, undefined);
assert.equal(openapi.components.schemas.AnswerSyncRequest.additionalProperties, false);
assert.equal(openapi.components.schemas.AnswerSyncRequest.properties.operations.minItems, 1);
assert.equal(openapi.components.schemas.AnswerSyncRequest.properties.operations.maxItems, 100);
assert.equal(openapi.components.schemas.AnswerSyncOperation.additionalProperties, false);
assert.deepEqual(
  openapi.components.schemas.AnswerSyncOperation.required,
  ["operation_id", "attempt_id", "attempt_question_id", "expected_revision", "value"],
);
assert.equal(openapi.components.schemas.AnswerSyncOperation.properties.operation_id.minLength, 16);
assert.equal(openapi.components.schemas.AnswerSyncOperation.properties.operation_id.maxLength, 128);
assert(openapi.components.schemas.AnswerSyncAcknowledgement.required.includes("replayed"));
assert(openapi.components.schemas.AnswerSyncAcknowledgement.required.includes("answer_revision"));
assert(openapi.components.schemas.AnswerSyncAcknowledgement.properties.code.enum.includes("SYNC_OPERATION_ID_REUSED"));
assert(openapi.components.schemas.AnswerSyncAcknowledgement.properties.code.enum.includes("ANSWER_REVISION_CONFLICT"));
assert.equal(openapi.paths["/v1/notifications"].get.operationId, "listStudentNotifications");
assert.equal(openapi.paths["/v1/notifications/read-all"].put.operationId, "markAllStudentNotificationsRead");
assert.equal(openapi.paths["/v1/notifications/{notificationId}/read"].put.operationId, "markStudentNotificationRead");
assert(openapi.paths["/v1/notifications/{notificationId}/read"].put.responses["404"]);
assert.equal(openapi.components.schemas.StudentNotification.additionalProperties, false);
assert.deepEqual(openapi.components.schemas.StudentNotification.required, ["id", "type", "title", "body", "occurred_at", "read_at"]);
assert.equal(openapi.components.schemas.StudentNotification.properties.read_at.type[0], "string");
assert.equal(openapi.components.schemas.StudentNotification.properties.read_at.type[1], "null");
assert(openapi.paths["/v1/advertising/decisions/{placementCode}"].get.responses["200"]);
assert.equal(openapi.paths["/v1/admin/preparation-requests"].post.operationId, "createPreparationRequest");
assert.equal(openapi.paths["/v1/admin/preparation-imports/validate"].post.operationId, "validatePreparationImport");
assert.equal(openapi.components.schemas.PreparationRequest.additionalProperties, false);
assert.equal(openapi.components.schemas.PreparationRequest.properties.academic_scope.additionalProperties, false);
assert.equal(openapi.components.schemas.PreparationRequest.properties.output_contract.additionalProperties, false);
assert.equal(openapi.components.schemas.PreparationRequest.properties.input_files.items.additionalProperties, false);
assert.equal(openapi.components.schemas.ReturnedPackManifest.additionalProperties, false);
assert.equal(openapi.components.schemas.ReturnedPackManifest.properties.files.items.additionalProperties, false);

console.log(`Validated ${requirementIds.size} requirements, ${acceptanceIds.size} acceptance criteria, locked decisions, API, and content contracts.`);
