import assert from "node:assert/strict";
import process from "node:process";
import test from "node:test";

import { boundedFailureDetail, runBoundedSync } from "./pilot-smoke-process.mjs";

test("bounded Pilot child process is terminated and classified as timeout failure", () => {
  const execution = runBoundedSync(
    process.execPath,
    ["-e", "setTimeout(() => {}, 5_000)"],
    {
      timeoutMs: 100,
      stdio: "ignore",
    },
  );

  assert.equal(execution.timedOut, true);
  assert.equal(execution.result.status, null);
  assert.match(boundedFailureDetail(execution, "timeout probe"), /exceeded the 100 ms timeout and was terminated/);
});

test("bounded Pilot child process preserves successful exit status", () => {
  const execution = runBoundedSync(process.execPath, ["-e", "process.exit(0)"], {
    timeoutMs: 5_000,
    stdio: "ignore",
  });

  assert.equal(execution.timedOut, false);
  assert.equal(execution.result.status, 0);
  assert.equal(boundedFailureDetail(execution, "success probe"), null);
});
