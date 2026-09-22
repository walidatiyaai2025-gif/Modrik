import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const api = readFileSync(new URL("../lib/learning-api.ts", import.meta.url), "utf8");
const css = readFileSync(new URL("./globals.css", import.meta.url), "utf8");

test("Student Web exposes a child-first start-learning journey", () => {
  assert.match(workspace, /startFirstLesson/);
  assert.match(workspace, /subject-card-grid/);
  assert.match(workspace, /learning-card-grid/);
  assert.match(workspace, /lessonPracticeAssessment/);
  assert.match(workspace, /copy\.lessonPractice/);
  assert.doesNotMatch(workspace, /<small>\{node\.reference\}<\/small>/);
});

test("lesson completion leads directly to authoritative practice", () => {
  assert.match(workspace, /lesson\.practice_quiz_id/);
  assert.match(workspace, /openAssessment\(lessonPracticeAssessment\)/);
  assert.match(workspace, /learningApi\.startAttempt\(selectedAssessment\.id/);
});

test("submitted attempts show student answer, correct answer and explanation", () => {
  assert.match(api, /current_answer:/);
  assert.match(api, /correct_answer:/);
  assert.match(workspace, /review\.current_answer\?\.value/);
  assert.match(workspace, /correctAnswerValue\(review\.correct_answer\)/);
  assert.match(workspace, /copy\.correctAnswer/);
  assert.match(workspace, /review\.explanation/);
});

test("student journey styles are responsive and avoid narrow technical lists", () => {
  assert.match(css, /\.subject-card-grid/);
  assert.match(css, /\.learning-card/);
  assert.match(css, /\.lesson-finish-panel/);
  assert.match(css, /\.answer-review-grid/);
  assert.match(css, /@media \(max-width: 640px\)/);
});
