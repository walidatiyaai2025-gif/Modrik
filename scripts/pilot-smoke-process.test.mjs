import assert from "node:assert/strict";
import { mkdtempSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import process from "node:process";
import test from "node:test";

import { boundedFailureDetail, runBoundedProcess, runBoundedSync } from "./pilot-smoke-process.mjs";

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

test(
  "bounded Pilot process owns and removes descendants left behind by a successful child",
  { skip: process.platform === "win32" ? "POSIX process-group regression" : false },
  async () => {
    const tempRoot = mkdtempSync(join(tmpdir(), "modrik-pilot-process-"));
    const pidFile = join(tempRoot, "grandchild.pid");
    try {
      const script = [
        "const { spawn } = require('node:child_process');",
        "const fs = require('node:fs');",
        "const child = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], { stdio: 'ignore' });",
        `fs.writeFileSync(${JSON.stringify(pidFile)}, String(child.pid));`,
        "child.unref();",
      ].join("\n");

      const execution = await runBoundedProcess(process.execPath, ["-e", script], {
        timeoutMs: 5_000,
        cleanupGraceMs: 500,
        cleanupForceMs: 1_000,
        stdio: "ignore",
      });

      assert.equal(execution.timedOut, false);
      assert.equal(execution.result.status, 0);
      assert.equal(execution.cleanupError, null);
      assert.equal(execution.residualTreeTerminated, true);
      assert.equal(boundedFailureDetail(execution, "process-tree probe"), null);

      const grandchildPid = Number(readFileSync(pidFile, "utf8"));
      assert.equal(Number.isInteger(grandchildPid) && grandchildPid > 0, true);
      await assertProcessGone(grandchildPid);
    } finally {
      rmSync(tempRoot, { recursive: true, force: true });
    }
  },
);

async function assertProcessGone(pid) {
  const deadline = Date.now() + 2_000;
  while (Date.now() <= deadline) {
    try {
      process.kill(pid, 0);
    } catch (error) {
      if (error?.code === "ESRCH") return;
      throw error;
    }
    await new Promise((resolve) => setTimeout(resolve, 25));
  }
  assert.fail(`expected descendant process ${pid} to be gone`);
}
