import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const copy = readFileSync(new URL("./student-copy.ts", import.meta.url), "utf8");
const api = readFileSync(new URL("../lib/learning-api.ts", import.meta.url), "utf8");

test("Student Web Home renders the three adaptive surfaces from the Backend contract", () => {
  assert.match(workspace, /learningApi\.adaptiveStudy\(\)/);
  assert.match(workspace, /data-student-home="today-mission"/);
  assert.match(workspace, /data-student-home="needs-practice"/);
  assert.match(workspace, /data-student-home="my-mistakes"/);
  assert.match(workspace, /adaptiveStudy\.today_mission/);
  assert.match(workspace, /adaptiveStudy\.needs_practice/);
  assert.match(workspace, /adaptiveStudy\.mistakes/);
  assert.match(api, /"learning:adaptive-study"/);
  assert.match(api, /"adaptive-study"/);
});

test("adaptive targets open only Backend-returned available assessments and refresh after submit", () => {
  assert.match(workspace, /assessment\.available_question_count < 1/);
  assert.match(workspace, /openAssessment\(\{[\s\S]*id: assessment\.id,[\s\S]*kind: assessment\.kind,[\s\S]*title: assessment\.title/);
  assert.match(workspace, /learningApi\.submit/);
  assert.match(workspace, /Promise\.all\(\[[\s\S]*learningApi\.progress\(\),[\s\S]*learningApi\.adaptiveStudy\(\)/);
  assert.doesNotMatch(workspace, /calculate.*mastery|generate.*plan|client.*score/i);
});

test("adaptive Home truth states and labels are localized", () => {
  assert.match(copy, /todayMission: "Today's Mission"/);
  assert.match(copy, /todayMission: "مهمة اليوم"/);
  assert.match(copy, /todayMission: "Mission du jour"/);
  assert.match(copy, /adaptiveDisabled:/);
  assert.match(copy, /adaptiveDegraded:/);
  assert.match(copy, /missionEmpty:/);
  assert.match(copy, /needsPracticeEmpty:/);
  assert.match(copy, /mistakesEmpty:/);
});
