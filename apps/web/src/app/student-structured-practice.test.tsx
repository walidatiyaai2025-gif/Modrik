import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const api = readFileSync(new URL("../lib/learning-api.ts", import.meta.url), "utf8");

test("Student Web consumes Backend multi-select contracts without renaming them", () => {
  assert.match(api, /kind: "multi_select"; options: ChoiceOption\[\]/);
  assert.match(workspace, /question\.response_contract\.kind === "multi_select"/);
  assert.match(workspace, /type="checkbox"/);
  assert.match(workspace, /Array\.isArray\(answers\[question\.attempt_question_id\]\)/);
  assert.match(workspace, /filter\(\(candidateId\) => nextSet\.has\(candidateId\)\)/);
});

test("Student Web sends boolean contracts as actual booleans and treats false as answered", () => {
  assert.match(api, /kind: "boolean"/);
  assert.match(api, /AnswerValue = string \| number \| boolean \| string\[\]/);
  assert.match(workspace, /question\.response_contract\.kind === "boolean"/);
  assert.match(workspace, /\[question\.attempt_question_id\]: true/);
  assert.match(workspace, /\[question\.attempt_question_id\]: false/);
  assert.match(workspace, /function isAnswerEmpty[\s\S]*?return false;/);
  assert.doesNotMatch(workspace, /client.*score|calculate.*mastery|generate.*plan/i);
});
