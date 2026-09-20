import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const api = readFileSync(new URL("../lib/learning-api.ts", import.meta.url), "utf8");

test("Student Web consumes Backend assessment mode and hint policy without inventing hints", () => {
  assert.match(api, /mode: string;/);
  assert.match(api, /hints_allowed: boolean;/);
  assert.match(api, /hints: string\[\];/);
  assert.match(workspace, /attempt\.hints_allowed && question\.hints\.length > 0/);
  assert.match(workspace, /question\.hints\.map/);
});

test("Student Web renders only Backend-revealed post-submit explanations", () => {
  assert.match(api, /export type AttemptReview/);
  assert.match(api, /explanation: LocalizedText \| null;/);
  assert.match(workspace, /\(result\.review \?\? \[\]\)\.map/);
  assert.match(workspace, /review\.explanation \? .*localize\(review\.explanation, locale\)/s);
  assert.doesNotMatch(workspace, /calculate.*correct|client.*grading|derive.*explanation/i);
});
