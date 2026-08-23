import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { readdir, readFile, stat } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import Ajv2020 from "ajv/dist/2020.js";
import addFormats from "ajv-formats";
import { parse as parseYaml } from "yaml";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const fromRoot = (...parts) => path.join(root, ...parts);
const readJson = async (...parts) => JSON.parse(await readFile(fromRoot(...parts), "utf8"));
const readYaml = async (...parts) => parseYaml(await readFile(fromRoot(...parts), "utf8"));
const sha256 = (value) => createHash("sha256").update(value).digest("hex");
const canonicalize = (value) => {
  if (Array.isArray(value)) return `[${value.map(canonicalize).join(",")}]`;
  if (value && typeof value === "object") {
    return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonicalize(value[key])}`).join(",")}}`;
  }
  return JSON.stringify(value);
};

async function filesBelow(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = await Promise.all(entries.map(async (entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? filesBelow(target) : [target];
  }));
  return files.flat();
}

function assertUnique(values, label) {
  assert.equal(new Set(values).size, values.length, `${label} must be unique`);
}

function validateQuestionSemantics(question) {
  if (question.type === "single_choice") {
    const optionIds = new Set(question.options.map(({ id }) => id));
    assert(optionIds.has(question.answer_contract.correct_option_id), "CONTENT_REFERENCE_INVALID");
  }
  if (question.type === "multiple_choice") {
    const optionIds = new Set(question.options.map(({ id }) => id));
    assert(question.answer_contract.correct_option_ids.every((id) => optionIds.has(id)), "CONTENT_REFERENCE_INVALID");
  }
}

function validatePackSemantics(pack, settings) {
  assert.equal(canonicalize(pack.academic_scope), canonicalize(settings.academic_scope), "CONTENT_SCOPE_MISMATCH");
  const nodeReferences = pack.curriculum_nodes.map(({ reference }) => reference);
  assertUnique(nodeReferences, "curriculum node references");
  const nodeSet = new Set(nodeReferences);
  for (const node of pack.curriculum_nodes) {
    if (node.parent_reference !== null) assert(nodeSet.has(node.parent_reference), "CONTENT_REFERENCE_INVALID");
  }
  const lessonIds = pack.lessons.map(({ id }) => id);
  const questionIds = pack.questions.map(({ id }) => id);
  const quizIds = pack.quizzes.map(({ id }) => id);
  assertUnique(lessonIds, "lesson IDs");
  assertUnique(questionIds, "question IDs");
  assertUnique(quizIds, "quiz IDs");
  const questionSet = new Set(questionIds);
  for (const lesson of pack.lessons) {
    assert(nodeSet.has(lesson.curriculum_node_reference), "CONTENT_REFERENCE_INVALID");
    const positions = lesson.blocks.map(({ position }) => position);
    assertUnique(positions, `lesson ${lesson.id} block positions`);
    assert.deepEqual([...positions].sort((a, b) => a - b), positions, `lesson ${lesson.id} blocks must be ordered`);
  }
  for (const question of pack.questions) {
    assert(nodeSet.has(question.curriculum_node_reference), "CONTENT_REFERENCE_INVALID");
    validateQuestionSemantics(question);
  }
  for (const quiz of pack.quizzes) {
    assert(nodeSet.has(quiz.curriculum_node_reference), "CONTENT_REFERENCE_INVALID");
    assert(quiz.question_ids.every((id) => questionSet.has(id)), "CONTENT_REFERENCE_INVALID");
  }
}

function validateIndexLinks(requirements, criteria) {
  const requirementIds = requirements.requirements.map(({ id }) => id);
  const criterionIds = criteria.acceptance_criteria.map(({ id }) => id);
  assertUnique(requirementIds, "requirement IDs");
  assertUnique(criterionIds, "acceptance-criterion IDs");
  const requirementSet = new Set(requirementIds);
  const criterionSet = new Set(criterionIds);
  for (const requirement of requirements.requirements) {
    assert.equal(requirement.priority, "P0");
    assert(requirement.acceptance_criteria.every((id) => criterionSet.has(id)), `${requirement.id} references an unknown AC`);
  }
  for (const criterion of criteria.acceptance_criteria) {
    assert(criterion.requirement_ids.every((id) => requirementSet.has(id)), `${criterion.id} references an unknown REQ`);
  }
}

const schemaFiles = (await filesBelow(fromRoot("schemas"))).filter((file) => file.endsWith(".schema.json"));
const schemas = await Promise.all(schemaFiles.map((file) => readFile(file, "utf8").then(JSON.parse)));
const ajv = new Ajv2020({ allErrors: true, strict: true, strictRequired: false, allowUnionTypes: true });
addFormats(ajv);
for (const schema of schemas) ajv.addSchema(schema);

const validateById = (id, value, label) => {
  const validate = ajv.getSchema(id);
  assert(validate, `Schema was not registered: ${id}`);
  assert(validate(value), `${label}: ${ajv.errorsText(validate.errors, { separator: "\n" })}`);
};

const settings = await readJson("tests", "fixtures", "content-pack", "v1", "valid", "preparation-settings.json");
const pack = await readJson("tests", "fixtures", "content-pack", "v1", "valid", "content-pack.json");
const manifest = await readJson("tests", "fixtures", "content-pack", "v1", "valid", "manifest.json");
const invalidManifest = await readJson("tests", "fixtures", "content-pack", "v1", "invalid-binding", "manifest.json");
const invalidQuestion = await readJson("tests", "fixtures", "content-pack", "v1", "invalid-question.json");

validateById("https://schemas.modrik.org/preparation/settings.schema.json", settings, "preparation settings");
validateById("https://schemas.modrik.org/content-pack/v1/content-pack.schema.json", pack, "content pack");
validateById("https://schemas.modrik.org/content-pack/v1/manifest.schema.json", manifest, "manifest");
validateById("https://schemas.modrik.org/content-pack/v1/manifest.schema.json", invalidManifest, "invalid binding fixture schema");
validateById("https://schemas.modrik.org/content-pack/v1/question.schema.json", invalidQuestion, "invalid question fixture schema");

const expectedSettingsHash = sha256(canonicalize(settings));
assert.equal(manifest.settings_hash, expectedSettingsHash, "valid fixture settings hash mismatch");
assert.notEqual(invalidManifest.settings_hash, expectedSettingsHash, "invalid binding fixture must mismatch settings");
assert.equal(manifest.preparation_request_id, "01J00000000000000000000001");
assert.equal(manifest.pack_id, pack.pack_id);

const validDirectory = fromRoot("tests", "fixtures", "content-pack", "v1", "valid");
let declaredBytes = 0;
for (const file of manifest.files) {
  const normalized = path.posix.normalize(file.path);
  assert.equal(normalized, file.path, `unsafe fixture path ${file.path}`);
  assert(!normalized.startsWith("../") && !path.posix.isAbsolute(normalized), `unsafe fixture path ${file.path}`);
  const target = path.join(validDirectory, ...normalized.split("/"));
  const bytes = await readFile(target);
  const fileStats = await stat(target);
  assert.equal(file.bytes, fileStats.size, `${file.path} byte count mismatch`);
  assert.equal(file.sha256, sha256(bytes), `${file.path} hash mismatch`);
  declaredBytes += file.bytes;
}
assert.equal(manifest.archive_limits.declared_file_count, manifest.files.length);
assert.equal(manifest.archive_limits.declared_uncompressed_bytes, declaredBytes);
validatePackSemantics(pack, settings);
assert.throws(() => validateQuestionSemantics(invalidQuestion), /CONTENT_REFERENCE_INVALID/);

const requirements = await readYaml("docs", "requirements", "requirements-index.yaml");
const criteria = await readYaml("docs", "requirements", "acceptance-criteria.yaml");
const decisions = await readYaml("docs", "requirements", "locked-decisions.yaml");
validateIndexLinks(requirements, criteria);
assertUnique(decisions.locked_decisions.map(({ id }) => id), "locked-decision IDs");

const openapi = await readYaml("docs", "api", "openapi.yaml");
assert.equal(openapi.openapi, "3.1.0");
assert.equal(openapi.paths["/v1/auth/register"].post.operationId, "registerAccount");
assert.deepEqual(openapi.paths["/v1/auth/register"].post.security, []);
assert.equal(openapi.components.schemas.AuthRegisterRequest.additionalProperties, false);
assert.equal(openapi.components.schemas.AuthRegisterRequest.properties.password.minLength, 12);
assert.equal(openapi.components.schemas.AuthRegisterRequest.properties.password.writeOnly, true);
assert.equal(openapi.paths["/v1/auth/login"].post.operationId, "loginAccount");
assert.deepEqual(openapi.paths["/v1/auth/login"].post.security, []);
assert.equal(openapi.paths["/v1/auth/password/recovery"].post.operationId, "requestPasswordRecovery");
assert.deepEqual(openapi.paths["/v1/auth/password/recovery"].post.security, []);
assert.equal(openapi.paths["/v1/auth/password/reset"].post.operationId, "resetAccountPassword");
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
for (const notificationPath of ["/v1/notifications", "/v1/notifications/read-all", "/v1/notifications/{notificationId}/read"]) {
  const method = notificationPath === "/v1/notifications" ? "get" : "put";
  assert.equal(
    openapi.paths[notificationPath][method].responses["200"].headers["Cache-Control"].schema.const,
    "no-store, private",
  );
}
assert.equal(openapi.components.parameters.NotificationId.required, true);
assert.deepEqual(openapi.components.schemas.NotificationLocalizedText.required, ["ar", "en", "fr"]);
assert.equal(openapi.components.schemas.StudentNotification.additionalProperties, false);
assert.deepEqual(
  openapi.components.schemas.StudentNotification.properties.action.oneOf[1].enum,
  ["study", "practice", "progress", "academic", "account"],
);
for (const forbiddenNotificationField of ["provider", "fcm_token", "apns_token", "device_token", "delivery_status"]) {
  assert(!Object.hasOwn(openapi.components.schemas.StudentNotification.properties, forbiddenNotificationField));
}
assert.equal(openapi.components.schemas.StudentNotificationInboxResponse.properties.data.properties.items.maxItems, 100);
assert.equal(openapi.components.schemas.StudentNotificationInboxResponse.properties.data.properties.unread_count.minimum, 0);
assert.equal(openapi.components.schemas.StudentNotificationReadAllResponse.properties.data.properties.unread_count.const, 0);
assert(openapi.paths["/v1/admin/preparation-requests"].post.parameters.some(({ $ref }) => $ref?.endsWith("/IdempotencyKey")));
assert(openapi.paths["/v1/admin/preparation-requests"].post.responses["201"].headers["Idempotency-Replayed"]);
assert(openapi.paths["/v1/admin/preparation-imports/validate"].post.parameters.some(({ $ref }) => $ref?.endsWith("/IdempotencyKey")));
assert(openapi.paths["/v1/admin/preparation-imports/validate"].post.responses["202"].headers["Idempotency-Replayed"]);
assert(openapi.paths["/v1/admin/preparation-imports/validate"].post.responses["422"].headers["Idempotency-Replayed"]);
assert(openapi.components.schemas.PreparationRequestResponse.properties.data.required.includes("prompt"));
assert(openapi.components.schemas.PreparationRequestResponse.properties.data.required.includes("bundle"));
assert.equal(openapi.components.schemas.PreparationImportResponse.properties.data.properties.status.const, "staged");
assert.equal(openapi.paths["/v1/advertising/decisions/{placementCode}"].get.operationId, "getAdvertisingDecision");
assert(openapi.components.schemas.AdvertisingDecisionResponse.properties.data.required.includes("advertising_allowed"));
assert(openapi.components.schemas.AdvertisingDecisionResponse.properties.data.properties.reason_code.enum.includes("GLOBAL_KILL_SWITCH"));
assert(openapi.components.schemas.AdvertisingDecisionResponse.properties.data.properties.reason_code.enum.includes("NO_AD_ZONE"));
assert.equal(openapi.paths["/v1/advertising/decisions/{placementCode}"].get.responses["200"].headers["Cache-Control"].schema.const, "no-store, private");

const eventCatalog = await readYaml("docs", "events", "event-catalog.yaml");
assert.equal(eventCatalog.delivery.guarantee, "at_least_once");
assert.equal(eventCatalog.delivery.worker_command, "php artisan modrik:outbox-dispatch --limit=100");
assert.deepEqual(eventCatalog.delivery.batch_limit_range, [1, 500]);
assert.equal(eventCatalog.delivery.retry.maximum_attempts, 5);
assertUnique(eventCatalog.events.map(({ type }) => type), "event types");
assert(eventCatalog.events.some(({ type }) => type === "academic.context_reset"));
assert(eventCatalog.events.some(({ type }) => type === "content.preparation_imported"));
assert(eventCatalog.events.some(({ type }) => type === "safety.advertising_decision_evaluated"));

const optionalAiBoundary = await readYaml("docs", "security", "optional-ai-boundary.yaml");
assert.equal(optionalAiBoundary.enabled_by_default, false);
assert.equal(optionalAiBoundary.core_dependency, "none");
assert.equal(optionalAiBoundary.transport, "none");
assert.deepEqual(optionalAiBoundary.providers, []);
assertUnique(optionalAiBoundary.allowed_context_fields, "optional-AI allowed context fields");
assertUnique(optionalAiBoundary.prohibited_context_fields, "optional-AI prohibited context fields");
assert.equal(
  optionalAiBoundary.allowed_context_fields.some((field) => optionalAiBoundary.prohibited_context_fields.includes(field)),
  false,
  "optional-AI allowed and prohibited context fields must not overlap",
);
for (const field of ["user_id", "email", "birth_date", "student_answer", "access_token", "session_cookie"]) {
  assert(optionalAiBoundary.prohibited_context_fields.includes(field), `${field} must remain prohibited from optional-AI context`);
}
const modrikConfig = await readFile(fromRoot("apps", "backend", "config", "modrik.php"), "utf8");
const backendEnvironment = await readFile(fromRoot("apps", "backend", ".env.example"), "utf8");
assert.match(modrikConfig, /MODRIK_PAID_AI_ENABLED', false/);
assert.match(backendEnvironment, /^MODRIK_PAID_AI_ENABLED=false$/m);

const shell = await readFile(fromRoot("deploy", "coming-soon", "index.html"), "utf8");
assert.match(shell, /https:\/\/modrik\.org\//);
assert.doesNotMatch(shell, /eduomni|mizakra|toppers/i);

console.log(`Validated ${schemas.length} schemas, ${requirements.requirements.length} requirements, ${criteria.acceptance_criteria.length} acceptance criteria, ${eventCatalog.events.length} events, OpenAPI, and golden fixtures.`);
