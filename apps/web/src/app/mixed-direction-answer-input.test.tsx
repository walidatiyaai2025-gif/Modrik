import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

import { directionForLocale } from "./student-copy";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");

test("free-text practice answers auto-detect direction inside AR RTL and EN LTR shells", () => {
  assert.equal(directionForLocale("ar"), "rtl");
  assert.equal(directionForLocale("en"), "ltr");

  assert.match(
    workspace,
    /<section lang=\{locale\} dir=\{direction\} className="student-shell">/,
  );
  assert.match(
    workspace,
    /<input\s+className="text-answer"\s+dir="auto"[\s\S]*?value=\{answers\[question\.attempt_question_id\] \?\? ""\}/,
  );
  assert.doesNotMatch(
    workspace,
    /className="text-answer"\s+dir="(?:rtl|ltr)"/,
  );
});
