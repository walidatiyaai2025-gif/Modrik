import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspaceSource = readFileSync(
  new URL("./learning-workspace.tsx", import.meta.url),
  "utf8",
);
const apiSource = readFileSync(
  new URL("../lib/learning-api.ts", import.meta.url),
  "utf8",
);

function sliceFunction(source: string, startMarker: string, endMarker: string): string {
  const start = source.indexOf(startMarker);
  const end = source.indexOf(endMarker, start + startMarker.length);
  assert.ok(start >= 0, `missing function marker: ${startMarker}`);
  assert.ok(end > start, `missing function end marker: ${endMarker}`);
  return source.slice(start, end);
}

test("offline before initial load fails closed without fabricating client authority", () => {
  const load = sliceFunction(
    workspaceSource,
    "const load = useCallback(async () => {",
    "useEffect(() => {",
  );

  assert.match(load, /if \(!navigator\.onLine\) \{\s*setState\("offline"\);\s*return;/);
  assert.match(load, /learningApi\.session\(\)/);
  assert.match(load, /learningApi\.academicContext\(\)/);
  assert.doesNotMatch(load, /seed\s*=|questionOrder\s*=|score\s*=/);
});

test("browser restart restores the exact stored attempt through backend authority", () => {
  assert.match(
    workspaceSource,
    /const activeAttemptStorageKey = "modrik\.student\.active-attempt";/,
  );
  assert.match(
    workspaceSource,
    /const storedAttemptId = window\.localStorage\.getItem\(activeAttemptStorageKey\);/,
  );
  assert.match(
    workspaceSource,
    /const candidate = await learningApi\.attempt\(storedAttemptId\);/,
  );
  assert.match(workspaceSource, /restoredAttempt = candidate;/);
  assert.match(workspaceSource, /applyAttempt\(restoredAttempt\);/);

  const applyAttempt = sliceFunction(
    workspaceSource,
    "const applyAttempt = useCallback((nextAttempt: Attempt | null) => {",
    "const handleError = useCallback",
  );
  assert.doesNotMatch(applyAttempt, /\.sort\(|shuffle|random/i);
});

test("timeout-before-ACK keeps the same command key until a successful acknowledgement", () => {
  const commandKey = sliceFunction(
    workspaceSource,
    "function commandKey(scope: string) {",
    "function acknowledge(scope: string) {",
  );
  assert.match(commandKey, /window\.localStorage\.getItem\(storageKey\)/);
  assert.match(commandKey, /if \(existing\) return existing;/);
  assert.match(commandKey, /window\.localStorage\.setItem\(storageKey, value\)/);

  const submitPractice = sliceFunction(
    workspaceSource,
    "async function submitPractice() {",
    "const progressAverage = useMemo",
  );
  const answerCall = submitPractice.indexOf("const saved = await learningApi.answer(");
  const keyUse = submitPractice.indexOf("commandKey(scope)", answerCall);
  const acknowledgement = submitPractice.indexOf("acknowledge(scope);", answerCall);
  assert.ok(answerCall >= 0);
  assert.ok(keyUse > answerCall);
  assert.ok(acknowledgement > keyUse);

  // Error handling occurs after the awaited request and therefore cannot clear
  // the persisted command key before the server response is accepted.
  const catchBlock = submitPractice.indexOf("} catch (error) {", acknowledgement);
  assert.ok(catchBlock > acknowledgement);
});

test("409 stale/multi-device conflict reloads the authoritative attempt", () => {
  const reconcileConflict = sliceFunction(
    workspaceSource,
    "async function reconcileConflict() {",
    "async function submitPractice() {",
  );
  assert.match(reconcileConflict, /learningApi\.attempt\(attempt\.id\)/);
  assert.match(reconcileConflict, /applyAttempt\(authoritativeAttempt\)/);
  assert.doesNotMatch(reconcileConflict, /\.sort\(|shuffle|random/i);

  const submitPractice = sliceFunction(
    workspaceSource,
    "async function submitPractice() {",
    "const progressAverage = useMemo",
  );
  assert.match(
    submitPractice,
    /error instanceof LearningApiError && error\.status === 409/,
  );
  assert.match(submitPractice, /await reconcileConflict\(\)/);
});

test("academic transition clears only client learning pointers then reloads backend state", () => {
  const transition = sliceFunction(
    workspaceSource,
    "async function handleAcademicTransition() {",
    "async function startPractice() {",
  );

  const removeAttempt = transition.indexOf(
    "window.localStorage.removeItem(activeAttemptStorageKey);",
  );
  const clearAttempt = transition.indexOf("applyAttempt(null);");
  const reload = transition.indexOf("await load();");
  assert.ok(removeAttempt >= 0);
  assert.ok(clearAttempt > removeAttempt);
  assert.ok(reload > clearAttempt);
  assert.doesNotMatch(transition, /localStorage\.clear\(/);
});

test("Web command payloads preserve backend assessment and revision authority", () => {
  assert.match(
    apiSource,
    /startAttempt: \(quizId: string, idempotencyKey: string\) =>[\s\S]*?command\("POST", \{ quiz_id: quizId \}, idempotencyKey\)/,
  );
  assert.match(
    apiSource,
    /command\("PUT", \{ expected_revision: expectedRevision, value \}, idempotencyKey\)/,
  );

  const startAttemptBlock = sliceFunction(
    apiSource,
    "startAttempt: (quizId: string, idempotencyKey: string) =>",
    "attempt: (attemptId: string)",
  );
  assert.doesNotMatch(startAttemptBlock, /seed|question_order|question_ids|score/);
});
