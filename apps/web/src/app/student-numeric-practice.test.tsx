import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const api = readFileSync(new URL("../lib/learning-api.ts", import.meta.url), "utf8");

test("Student Web renders Backend numeric practice contracts explicitly", () => {
  assert.match(api, /\{ kind: "numeric" \}/);
  assert.match(workspace, /question\.response_contract\.kind === "numeric"/);
  assert.match(workspace, /type="number"/);
  assert.match(workspace, /inputMode="decimal"/);
  assert.match(workspace, /step="any"/);
});

test("numeric practice answers cross the API boundary as finite numbers and hydrate without losing Backend type", () => {
  assert.match(
    workspace,
    /question\.response_contract\.kind === "numeric"[\s\S]*?!Number\.isFinite\(Number\(answers\[question\.attempt_question_id\]\)\)/,
  );
  assert.match(
    workspace,
    /const answerValue = question\.response_contract\.kind === "numeric"[\s\S]*?\? Number\(rawAnswer\)[\s\S]*?: rawAnswer;/,
  );
  assert.match(workspace, /question\.current_answer\?\.value \?\? ""/);
  assert.match(workspace, /nextSavedAnswers\[questionId\] = saved\.value/);
  assert.match(workspace, /textInputValue\(answers\[question\.attempt_question_id\]\)/);
  assert.doesNotMatch(workspace, /client.*score|calculate.*mastery|generate.*plan/i);
});
