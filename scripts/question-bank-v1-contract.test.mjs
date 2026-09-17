import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";
import Ajv2020 from "ajv/dist/2020.js";
import addFormats from "ajv-formats";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const schema = JSON.parse(
  await readFile(path.join(root, "schemas/question-bank/v1/import.schema.json"), "utf8"),
);

function validator() {
  const ajv = new Ajv2020({
    allErrors: true,
    strict: true,
    strictRequired: false,
    allowUnionTypes: true,
  });
  addFormats(ajv);
  return ajv.compile(schema);
}

function validPack() {
  return {
    schema_version: "modrik-question-bank-v1",
    preparation_request_id: "01J00000000000000000000001",
    settings_hash: "a".repeat(64),
    pack_id: "01J00000000000000000000002",
    generated_at: "2026-09-17T20:30:00Z",
    prompt: {
      id: "MODRIK_QUESTION_BANK_MASTER_V1",
      version: "1.0.0",
    },
    sources: [
      {
        source_id: "source-math-y6-01",
        source_name: "Owner supplied Year 6 mathematics source",
      },
    ],
    items: [
      {
        id: "q-local-001",
        academic_scope: {
          academic_year: "Year 6",
          track_reference: null,
          subject: "Mathematics",
          unit: null,
          topic: "Fractions",
          skill: null,
        },
        learning_objective: "Identify equivalent fractions from the supplied source.",
        type: "multiple_choice",
        language: "en",
        question_text: "Which fraction is equivalent to 1/2?",
        options: [
          { id: "a", text: "2/4" },
          { id: "b", text: "3/4" },
        ],
        answer_contract: {
          correct_option_id: "a",
        },
        explanation: "2/4 simplifies to 1/2.",
        hints: ["Compare numerator and denominator by the same factor."],
        difficulty: "Easy",
        source: {
          source_id: "source-math-y6-01",
          source_page: null,
          notes: "The page number was not present in the bounded source package.",
        },
        provenance: {
          derivation: "generated_derivative",
          confidence: "high",
          generation_notes: "Derived only from the supplied fraction example.",
        },
      },
      {
        id: "q-local-002",
        academic_scope: {
          academic_year: null,
          track_reference: null,
          subject: null,
          unit: null,
          topic: null,
          skill: null,
        },
        learning_objective: null,
        type: "numeric",
        language: "ar",
        question_text: "احسب 3 + 4.",
        answer_contract: {
          value: 7,
          tolerance: 0,
        },
        explanation: "٣ + ٤ = ٧.",
        hints: [],
        difficulty: "Revision",
        source: {
          source_id: "source-math-y6-01",
          source_page: 12,
          notes: null,
        },
        provenance: {
          derivation: "generated_derivative",
          confidence: null,
          generation_notes: null,
        },
      },
    ],
    warnings: [],
    unknowns: [
      "Canonical track reference was not provided, so it remains null.",
    ],
  };
}

function semanticErrors(pack) {
  const errors = [];
  const sourceIds = pack.sources.map(({ source_id }) => source_id);
  if (new Set(sourceIds).size !== sourceIds.length) errors.push("DUPLICATE_SOURCE_ID");
  const sourceSet = new Set(sourceIds);

  const itemIds = pack.items.map(({ id }) => id);
  if (new Set(itemIds).size !== itemIds.length) errors.push("DUPLICATE_ITEM_ID");

  for (const item of pack.items) {
    if (!sourceSet.has(item.source.source_id)) errors.push("SOURCE_REFERENCE_INVALID");

    const optionIds = (item.options ?? []).map(({ id }) => id);
    if (new Set(optionIds).size !== optionIds.length) errors.push("DUPLICATE_OPTION_ID");
    const optionSet = new Set(optionIds);

    if (item.type === "multiple_choice" && !optionSet.has(item.answer_contract.correct_option_id)) {
      errors.push("ANSWER_REFERENCE_INVALID");
    }
    if (
      item.type === "multi_select"
      && !item.answer_contract.correct_option_ids.every((id) => optionSet.has(id))
    ) {
      errors.push("ANSWER_REFERENCE_INVALID");
    }
    if (
      item.type === "ordering"
      && (
        item.answer_contract.correct_order.length !== optionIds.length
        || !item.answer_contract.correct_order.every((id) => optionSet.has(id))
      )
    ) {
      errors.push("ANSWER_REFERENCE_INVALID");
    }
  }

  return errors;
}

test("modrik-question-bank-v1 accepts explicit unknowns and UTF-8 question content", () => {
  const validate = validator();
  const pack = validPack();

  assert.equal(validate(pack), true, JSON.stringify(validate.errors, null, 2));
  assert.deepEqual(semanticErrors(pack), []);
  assert.equal(pack.items[0].source.source_page, null);
  assert.equal(pack.items[1].question_text, "احسب 3 + 4.");
});

test("modrik-question-bank-v1 fails closed on unknown schema versions and properties", () => {
  const validate = validator();
  const wrongVersion = validPack();
  wrongVersion.schema_version = "modrik-question-bank-v2";
  assert.equal(validate(wrongVersion), false);

  const unexpected = validPack();
  unexpected.items[0].invented_board = "Do not accept guessed metadata";
  assert.equal(validate(unexpected), false);
});

test("modrik-question-bank-v1 enforces type-specific answer shapes and non-guessed pages", () => {
  const validate = validator();
  const wrongAnswer = validPack();
  wrongAnswer.items[0].answer_contract = { correct: true };
  assert.equal(validate(wrongAnswer), false);

  const invalidPage = validPack();
  invalidPage.items[0].source.source_page = 0;
  assert.equal(validate(invalidPage), false);

  const unsupportedType = validPack();
  unsupportedType.items[0].type = "essay";
  assert.equal(validate(unsupportedType), false);
});

test("modrik-question-bank-v1 semantic guard rejects dangling source and option references", () => {
  const danglingSource = validPack();
  danglingSource.items[0].source.source_id = "missing-source";
  assert(semanticErrors(danglingSource).includes("SOURCE_REFERENCE_INVALID"));

  const danglingAnswer = validPack();
  danglingAnswer.items[0].answer_contract.correct_option_id = "missing-option";
  assert(semanticErrors(danglingAnswer).includes("ANSWER_REFERENCE_INVALID"));

  const duplicateItem = validPack();
  duplicateItem.items[1].id = duplicateItem.items[0].id;
  assert(semanticErrors(duplicateItem).includes("DUPLICATE_ITEM_ID"));
});
