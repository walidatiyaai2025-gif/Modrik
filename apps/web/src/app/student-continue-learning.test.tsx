import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const copy = readFileSync(new URL("./student-copy.ts", import.meta.url), "utf8");

test("Student Web Home exposes Continue Learning only from the authoritative restored attempt", () => {
  assert.match(workspace, /type WorkspaceView = "home" \| "catalogue"/);
  assert.match(workspace, /useState<WorkspaceView>\("home"\)/);
  assert.match(workspace, /attempt\?\.status === "in_progress"/);
  assert.match(workspace, /data-student-home="continue-learning"/);
  assert.match(workspace, /onClick=\{\(\) => setView\("practice"\)\}/);
  assert.match(workspace, /question\.current_answer !== null/);
});

test("Continue Learning remains presentation-only and localized", () => {
  assert.match(copy, /continueLearning: "Continue Learning"/);
  assert.match(copy, /continueLearning: "متابعة التعلّم"/);
  assert.match(copy, /continueLearning: "Continuer l’apprentissage"/);
  assert.doesNotMatch(workspace, /generate.*plan|calculate.*mastery|client.*score/i);
});
