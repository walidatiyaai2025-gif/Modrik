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
assert.equal(openapi.components.schemas.StartAttemptRequest.additionalProperties, false);
assert(!Object.hasOwn(openapi.components.schemas.StartAttemptRequest.properties, "seed"));
assert(!Object.hasOwn(openapi.components.schemas.StartAttemptRequest.properties, "question_order"));
assert.equal(openapi.components.parameters.IdempotencyKey.required, true);
assert(openapi.components.schemas.LessonResponse.properties.data.required.includes("practice_quiz_id"));
assert(openapi.components.schemas.AttemptResultResponse.properties.data.required.includes("attempt"));
assert(openapi.paths["/v1/attempts/{attemptId}/submit"].post.responses["200"].headers["Idempotency-Replayed"]);
assert.equal(openapi.components.schemas.AcademicContextMutationRequest.additionalProperties, false);
assert(openapi.components.schemas.Attempt.required.includes("academic_context_id"));
assert(openapi.paths["/v1/academic-context/reset"].post.parameters.some(({ $ref }) => $ref?.endsWith("/IdempotencyKey")));
assert(openapi.paths["/v1/academic-context/reset"].post.responses["200"].headers["Idempotency-Replayed"]);
assert(openapi.paths["/v1/admin/preparation-requests"].post.parameters.some(({ $ref }) => $ref?.endsWith("/IdempotencyKey")));
assert(openapi.paths["/v1/admin/preparation-requests"].post.responses["201"].headers["Idempotency-Replayed"]);
assert(openapi.paths["/v1/admin/preparation-imports/validate"].post.parameters.some(({ $ref }) => $ref?.endsWith("/IdempotencyKey")));
assert(openapi.paths["/v1/admin/preparation-imports/validate"].post.responses["202"].headers["Idempotency-Replayed"]);
assert(openapi.paths["/v1/admin/preparation-imports/validate"].post.responses["422"].headers["Idempotency-Replayed"]);
assert(openapi.components.schemas.PreparationRequestResponse.properties.data.required.includes("prompt"));
assert(openapi.components.schemas.PreparationRequestResponse.properties.data.required.includes("bundle"));
assert.equal(openapi.components.schemas.PreparationImportResponse.properties.data.properties.status.const, "staged");

const eventCatalog = await readYaml("docs", "events", "event-catalog.yaml");
assert.equal(eventCatalog.delivery.guarantee, "at_least_once");
assertUnique(eventCatalog.events.map(({ type }) => type), "event types");
assert(eventCatalog.events.some(({ type }) => type === "academic.context_reset"));
assert(eventCatalog.events.some(({ type }) => type === "content.preparation_imported"));

const shell = await readFile(fromRoot("deploy", "coming-soon", "index.html"), "utf8");
assert.match(shell, /https:\/\/modrik\.org\//);
assert.doesNotMatch(shell, /eduomni|mizakra|toppers/i);

console.log(`Validated ${schemas.length} schemas, ${requirements.requirements.length} requirements, ${criteria.acceptance_criteria.length} acceptance criteria, ${eventCatalog.events.length} events, OpenAPI, and golden fixtures.`);
