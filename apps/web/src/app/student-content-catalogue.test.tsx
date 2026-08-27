import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const api = readFileSync(new URL("../lib/learning-api.ts", import.meta.url), "utf8");
const proxy = readFileSync(new URL("./api/learning/[...path]/route.ts", import.meta.url), "utf8");

test("student workspace discovers published content instead of a hard-coded lesson", () => {
  assert.doesNotMatch(workspace, /fixtureLessonId/);
  assert.doesNotMatch(workspace, /labels\.synthetic/);
  assert.match(workspace, /learningApi\.contentCatalogue\(\)/);
  assert.match(workspace, /catalogue\.subjects/);
  assert.match(workspace, /node\.children\.map/);
  assert.match(workspace, /openLesson\(item\.id\)/);
});

test("student workspace exposes published practice quizzes and mock exams", () => {
  assert.match(workspace, /CatalogueAssessment/);
  assert.match(workspace, /mock_exam/);
  assert.match(workspace, /learningApi\.startAttempt\(selectedAssessment\.id/);
  assert.match(workspace, /learningApi\.lesson\(lessonId\)/);
});

test("learning client and BFF expose catalogue filters without dropping query parameters", () => {
  assert.match(api, /contentCatalogue:/);
  assert.match(api, /subject_reference=/);
  assert.match(proxy, /\^content-catalogue\$/);
  assert.match(proxy, /incomingUrl\.searchParams\.entries\(\)/);
  assert.match(proxy, /upstreamUrl\.searchParams\.append/);
});
