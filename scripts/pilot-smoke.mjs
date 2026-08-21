#!/usr/bin/env node

import { spawn } from "node:child_process";
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join, relative, resolve } from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

import { boundedFailureDetail, runBoundedSync } from "./pilot-smoke-process.mjs";

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const runtimeRoot = join(repoRoot, ".runtime");
const reportPath = join(runtimeRoot, "pilot-smoke-report.json");
const correlationAcceptancePath = join(repoRoot, "docs/qa/p0-observability-correlation-acceptance.md");
const args = new Set(process.argv.slice(2));
const planOnly = args.has("--plan");
const strict = args.has("--strict");
const allowedArgs = new Set(["--plan", "--strict", "--help"]);
const productSuiteTimeoutMs = 20 * 60_000;
const browserSuiteTimeoutMs = 30 * 60_000;
const fixtureStepTimeoutMs = 5 * 60_000;
const fixtureShutdownGraceMs = 2_000;
const fixtureShutdownForceMs = 1_000;
const gitCommandTimeoutMs = 5_000;

for (const arg of args) {
  if (!allowedArgs.has(arg)) {
    console.error(`Unknown pilot smoke option: ${arg}`);
    process.exit(64);
  }
}

if (args.has("--help")) {
  console.log(`MODRIK Pilot smoke harness\n\nUsage:\n  node scripts/pilot-smoke.mjs [--plan] [--strict]\n\n--plan    Validate and print the acceptance plan without executing suites.\n--strict  Exit 2 when any acceptance row is BLOCKED by an unintegrated release dependency.\n\nWithout --strict, real test failures still exit 1 while BLOCKED rows are reported without failing the command. Executable child suites are bounded; a timeout is recorded as FAIL.`);
  process.exit(0);
}

const commandSuites = {
  backend: {
    label: "Backend P0 feature suite",
    command: "php",
    commandArgs: ["artisan", "test"],
    cwd: join(repoRoot, "apps/backend"),
    timeoutMs: productSuiteTimeoutMs,
  },
  web: {
    label: "Student Web/Public/Auth test suite",
    command: "npm",
    commandArgs: ["run", "test"],
    cwd: join(repoRoot, "apps/web"),
    timeoutMs: productSuiteTimeoutMs,
  },
  mobile: {
    label: "Flutter Mobile test suite",
    command: "flutter",
    commandArgs: ["test"],
    cwd: join(repoRoot, "apps/mobile"),
    timeoutMs: productSuiteTimeoutMs,
  },
  browser: {
    label: "Integrated Web browser runtime acceptance",
    command: "bash",
    commandArgs: [join(repoRoot, "qa/web-e2e/run-browser-runtime.sh"), repoRoot],
    cwd: repoRoot,
    timeoutMs: browserSuiteTimeoutMs,
  },
};

const gates = {
  durableRecovery: {
    label: "Durable process-restart learning recovery integrated",
    check: () => existsSync(join(repoRoot, "apps/mobile/test/durable_learning_store_test.dart")),
    evidence: "apps/mobile/test/durable_learning_store_test.dart",
  },
  runtimeDiagnostics: {
    label: "Backend + Web + Mobile Runtime Inspector/correlation stack integrated",
    check: () =>
      existsSync(join(repoRoot, "apps/web/src/lib/runtime-diagnostics.test.tsx")) &&
      existsSync(join(repoRoot, "apps/mobile/test/runtime_diagnostics_test.dart")) &&
      backendContainsCanonicalCorrelationBoundary(),
    evidence: "Web runtime diagnostics test + Mobile runtime diagnostics test + Backend canonical X-Correlation-ID boundary",
  },
  correlationAcceptance: {
    label: "Issue #101 terminal composed correlation trace accepted",
    check: () => evidenceLedgerContains(
      correlationAcceptancePath,
      "OBSERVABILITY CORRELATION ACCEPTANCE COMPLETE",
    ),
    evidence: "docs/qa/p0-observability-correlation-acceptance.md with terminal same-support-reference Web/Mobile -> real Laravel Backend -> privileged diagnostics evidence",
  },
  browserRuntime: {
    label: "Executable Web browser runtime acceptance integrated",
    check: () =>
      existsSync(join(repoRoot, "qa/web-e2e/browser-runtime-acceptance.cjs")) &&
      existsSync(join(repoRoot, "qa/web-e2e/runtime-inspector-acceptance.cjs")) &&
      existsSync(join(repoRoot, "qa/web-e2e/run-browser-runtime.sh")) &&
      existsSync(join(repoRoot, ".github/workflows/web-browser-runtime-e2e.yml")),
    evidence: "Issue #108 current-tree browser runner + Runtime Inspector runner + executable wrapper + workflow",
  },
};

const acceptanceRows = [
  {
    id: "registration-login-session",
    label: "Registration / login / session",
    suites: ["backend", "web", "mobile"],
    evidence: "Backend Auth lifecycle plus Web/Mobile sign-in, session restoration and revocation coverage.",
  },
  {
    id: "verification-required",
    label: "Verification-required path",
    suites: ["backend", "web", "mobile"],
    evidence: "Backend verification/resend lifecycle plus explicit accessible Web/Mobile verification UX coverage.",
  },
  {
    id: "academic-track-lifecycle",
    label: "Academic track catalogue / select / change / reset",
    suites: ["backend", "web", "mobile"],
    gates: ["durableRecovery"],
    evidence: "Academic catalogue/context lifecycle, reset semantics, client consumption and stale-state invalidation tests.",
  },
  {
    id: "dashboard-lesson-study",
    label: "Dashboard -> lesson / study",
    suites: ["web", "mobile", "fixture"],
    evidence: "Visible learning workspace coverage plus live Next Learning BFF -> Laravel fixture lesson read.",
  },
  {
    id: "practice-attempt",
    label: "Practice / attempt",
    suites: ["backend", "web", "mobile", "fixture"],
    evidence: "Authoritative Assessment/client practice tests plus live attempt/answer/submit smoke.",
  },
  {
    id: "authoritative-resume-order",
    label: "Backend-authoritative attempt resume / order",
    suites: ["backend", "web", "mobile", "fixture"],
    evidence: "Server-owned attempt snapshot/order tests, Web resume boundary, Mobile cached/online resume and live fixture attempt flow.",
  },
  {
    id: "scoring-authority",
    label: "Scoring authority",
    suites: ["backend", "web", "mobile", "fixture"],
    evidence: "Backend immutable-snapshot scoring authority plus clients that never submit scoring/seed/order authority and live graded result.",
  },
  {
    id: "offline-pending-answer",
    label: "Offline pending answer",
    suites: ["backend", "web", "mobile"],
    evidence: "Issue #14 pending-operation and offline client-boundary tests preserve one authoritative operation identity.",
  },
  {
    id: "retry-replay",
    label: "Retry / replay",
    suites: ["backend", "web", "mobile"],
    evidence: "Timeout-before-ACK, reconnect replay, conflicts and outbox retry/redrive coverage.",
  },
  {
    id: "durable-ack-no-duplicate",
    label: "Durable ACK / no duplicate",
    suites: ["backend", "web", "mobile"],
    evidence: "Durable acknowledgements, idempotent replay and client ACK removal/no-resend coverage.",
  },
  {
    id: "process-restart-recovery",
    label: "Process-restart recovery",
    suites: ["backend", "web", "mobile"],
    gates: ["durableRecovery"],
    evidence: "Durable account-scoped recovery-store reconstruction plus exact authoritative attempt/pending-operation recovery.",
  },
  {
    id: "admin-publication-safety",
    label: "Admin publication safety",
    suites: ["backend"],
    evidence: "Content preparation/publication/destructive-confirmation tests including official-role boundary, UGC exclusion and atomic retry.",
  },
  {
    id: "public-coming-soon-boundary",
    label: "Public / Coming Soon boundary",
    suites: ["web"],
    evidence: "Public route/copy/metadata tests preserve explicit owner-controlled legal/release blockers and canonical Coming Soon branding.",
  },
  {
    id: "runtime-diagnostics-correlation",
    label: "Runtime diagnostics / correlation",
    suites: ["backend", "web", "mobile"],
    gates: ["runtimeDiagnostics", "correlationAcceptance"],
    evidence: "Integrated component diagnostics plus Issue #101 terminal single composed same-support-reference trace to real Laravel and privileged diagnostics.",
  },
  {
    id: "browser-runtime-evidence",
    label: "Browser runtime evidence",
    suites: ["browser"],
    gates: ["browserRuntime"],
    evidence: "Executed Issue #108 current-tree real Chromium runtime/session/Inspector/keyboard/overflow acceptance with no required FAIL rows.",
  },
  {
    id: "locales-direction-smoke",
    label: "AR / EN / FR + RTL / LTR smoke",
    suites: ["web", "mobile", "browser"],
    gates: ["browserRuntime"],
    evidence: "Web/Mobile locale-direction tests plus Issue #108 real Chromium AR/RTL, FR/LTR and EN/LTR control coverage.",
  },
];

validateManifest();

const suiteResults = new Map();
const gateResults = Object.fromEntries(
  Object.entries(gates).map(([id, gate]) => [id, { status: gate.check() ? "PASS" : "BLOCKED", evidence: gate.evidence }]),
);

if (planOnly) {
  printPlan();
  process.exit(0);
}

for (const suiteId of [...new Set(acceptanceRows.flatMap((row) => row.suites))]) {
  if (suiteId === "browser" && gateResults.browserRuntime.status !== "PASS") {
    suiteResults.set(suiteId, {
      status: "BLOCKED",
      exit_code: null,
      duration_ms: 0,
      command: "qa/web-e2e/run-browser-runtime.sh",
      detail: "Issue #108 browser runtime acceptance is not integrated on this Git tree.",
    });
  } else if (suiteId === "fixture") {
    suiteResults.set(suiteId, await runFixtureSmoke());
  } else {
    suiteResults.set(suiteId, runCommandSuite(suiteId));
  }
}

const rows = acceptanceRows.map((row) => evaluateRow(row));
const summary = {
  pass: rows.filter((row) => row.status === "PASS").length,
  fail: rows.filter((row) => row.status === "FAIL").length,
  blocked: rows.filter((row) => row.status === "BLOCKED").length,
};
const report = {
  schema: "modrik.pilot-smoke-report.v1",
  generated_at: new Date().toISOString(),
  git_head: gitHead(),
  strict,
  summary,
  suites: Object.fromEntries(suiteResults),
  gates: gateResults,
  rows,
};

mkdirSync(runtimeRoot, { recursive: true });
writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");
printMatrix(rows, summary);
console.log(`\nMachine-readable report: ${relative(repoRoot, reportPath)}`);

if (summary.fail > 0) process.exit(1);
if (strict && summary.blocked > 0) process.exit(2);

function validateManifest() {
  const suiteIds = new Set([...Object.keys(commandSuites), "fixture"]);
  const gateIds = new Set(Object.keys(gates));
  const rowIds = new Set();
  for (const row of acceptanceRows) {
    if (rowIds.has(row.id)) throw new Error(`Duplicate acceptance row id: ${row.id}`);
    rowIds.add(row.id);
    if (!row.label || !row.evidence || row.suites.length === 0) throw new Error(`Incomplete acceptance row: ${row.id}`);
    for (const suite of row.suites) {
      if (!suiteIds.has(suite)) throw new Error(`Unknown suite ${suite} in row ${row.id}`);
    }
    for (const gate of row.gates ?? []) {
      if (!gateIds.has(gate)) throw new Error(`Unknown gate ${gate} in row ${row.id}`);
    }
  }
}

function runCommandSuite(id) {
  const suite = commandSuites[id];
  console.log(`\n=== ${suite.label} ===`);
  const started = Date.now();
  const execution = runBoundedSync(suite.command, suite.commandArgs, {
    cwd: suite.cwd,
    env: process.env,
    stdio: "inherit",
    shell: process.platform === "win32",
    timeoutMs: suite.timeoutMs,
  });
  const failureDetail = boundedFailureDetail(execution, suite.label);
  if (failureDetail) console.error(failureDetail);

  return {
    status: failureDetail ? "FAIL" : "PASS",
    exit_code: failureDetail ? (execution.timedOut ? 124 : (execution.result.status ?? 1)) : 0,
    duration_ms: Date.now() - started,
    command: [suite.command, ...suite.commandArgs].join(" "),
    timeout_ms: suite.timeoutMs,
    ...(failureDetail ? { detail: failureDetail } : {}),
  };
}

async function runFixtureSmoke() {
  console.log("\n=== Live Student Web BFF -> Laravel fixture smoke ===");
  const started = Date.now();
  const tempRoot = join(runtimeRoot, `pilot-fixture-${process.pid}`);
  const databasePath = join(tempRoot, "pilot.sqlite");
  mkdirSync(tempRoot, { recursive: true });
  writeFileSync(databasePath, "", "utf8");

  const port = Number(process.env.MODRIK_PILOT_SMOKE_PORT ?? 18127);
  const baseUrl = `http://127.0.0.1:${port}`;
  const fixtureToken = "modrik-pilot-fixture-token";
  const backendEnv = {
    ...process.env,
    APP_ENV: "testing",
    APP_KEY: "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=",
    DB_CONNECTION: "sqlite",
    DB_DATABASE: databasePath,
    MODRIK_FIXTURE_MODE: "true",
    MODRIK_FIXTURE_BEARER_TOKEN: fixtureToken,
    MODRIK_IDEMPOTENCY_SECRET: "modrik-pilot-idempotency-secret",
    CACHE_STORE: "array",
    QUEUE_CONNECTION: "sync",
  };

  let server;
  try {
    const migrate = runBoundedSync("php", ["artisan", "migrate:fresh", "--seed", "--force"], {
      cwd: join(repoRoot, "apps/backend"),
      env: backendEnv,
      stdio: "inherit",
      shell: process.platform === "win32",
      timeoutMs: fixtureStepTimeoutMs,
    });
    const migrateFailure = boundedFailureDetail(migrate, "Laravel fixture migrate/seed");
    if (migrateFailure) {
      return fixtureFailure(started, migrateFailure, migrate.timedOut ? 124 : (migrate.result.status ?? 1));
    }

    server = spawn("php", ["artisan", "serve", "--host=127.0.0.1", `--port=${port}`], {
      cwd: join(repoRoot, "apps/backend"),
      env: backendEnv,
      stdio: ["ignore", "ignore", "inherit"],
      shell: process.platform === "win32",
    });

    await waitForBackend(`${baseUrl}/up`, server);
    const smoke = runBoundedSync("npm", ["run", "smoke:fixture"], {
      cwd: join(repoRoot, "apps/web"),
      env: {
        ...process.env,
        MODRIK_API_BASE_URL: baseUrl,
        MODRIK_FIXTURE_MODE: "true",
        MODRIK_FIXTURE_BEARER_TOKEN: fixtureToken,
      },
      stdio: "inherit",
      shell: process.platform === "win32",
      timeoutMs: fixtureStepTimeoutMs,
    });
    const smokeFailure = boundedFailureDetail(smoke, "Web fixture smoke");
    if (smokeFailure) {
      return fixtureFailure(started, smokeFailure, smoke.timedOut ? 124 : (smoke.result.status ?? 1));
    }
    return {
      status: "PASS",
      exit_code: 0,
      duration_ms: Date.now() - started,
      command: "Laravel migrate:fresh --seed + server + npm run smoke:fixture",
      step_timeout_ms: fixtureStepTimeoutMs,
    };
  } catch (error) {
    return fixtureFailure(started, error instanceof Error ? error.message : String(error), 1);
  } finally {
    await stopServer(server);
    rmSync(tempRoot, { recursive: true, force: true });
  }
}

function fixtureFailure(started, detail, exitCode) {
  console.error(`Fixture smoke failure: ${detail}`);
  return {
    status: "FAIL",
    exit_code: exitCode,
    duration_ms: Date.now() - started,
    command: "Laravel migrate:fresh --seed + server + npm run smoke:fixture",
    step_timeout_ms: fixtureStepTimeoutMs,
    detail,
  };
}

async function waitForBackend(url, server) {
  for (let attempt = 0; attempt < 80; attempt += 1) {
    if (server.exitCode !== null) throw new Error(`Laravel fixture server exited early with code ${server.exitCode}`);
    try {
      const response = await fetch(url, { signal: AbortSignal.timeout(500) });
      if (response.ok) return;
    } catch {
      // Server is still starting.
    }
    await new Promise((resolvePromise) => setTimeout(resolvePromise, 125));
  }
  throw new Error(`Laravel fixture server did not become ready at ${url}`);
}

async function stopServer(server) {
  if (!server || server.exitCode !== null || server.signalCode !== null) return;

  const gracefulExit = waitForChildExit(server, fixtureShutdownGraceMs);
  server.kill("SIGTERM");
  if (await gracefulExit) return;

  console.error("Fixture server did not exit after SIGTERM; escalating to SIGKILL.");
  const forcedExit = waitForChildExit(server, fixtureShutdownForceMs);
  server.kill("SIGKILL");
  if (!(await forcedExit)) {
    console.error("Fixture server did not confirm exit after SIGKILL before the cleanup deadline.");
  }
}

function waitForChildExit(server, timeoutMs) {
  return new Promise((resolvePromise) => {
    if (server.exitCode !== null || server.signalCode !== null) {
      resolvePromise(true);
      return;
    }

    let settled = false;
    const finish = (exited) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      server.off("exit", onExit);
      resolvePromise(exited);
    };
    const onExit = () => finish(true);
    const timer = setTimeout(() => finish(false), timeoutMs);
    server.once("exit", onExit);
  });
}

function evaluateRow(row) {
  const failedSuites = row.suites.filter((suite) => suiteResults.get(suite)?.status === "FAIL");
  const blockedGates = (row.gates ?? []).filter((gate) => gateResults[gate]?.status !== "PASS");
  const status = failedSuites.length > 0 ? "FAIL" : blockedGates.length > 0 ? "BLOCKED" : "PASS";
  return {
    id: row.id,
    label: row.label,
    status,
    suites: row.suites,
    blocked_gates: blockedGates,
    evidence: row.evidence,
  };
}

function printPlan() {
  console.log("MODRIK P0 Pilot acceptance plan");
  console.log("================================");
  for (const row of acceptanceRows) {
    const blocked = (row.gates ?? []).filter((gate) => gateResults[gate].status !== "PASS");
    const state = blocked.length > 0 ? `BLOCKED(${blocked.join(",")})` : "READY";
    console.log(`${state.padEnd(28)} ${row.label}`);
  }
  console.log("\nPlan validation passed. No product tests were executed.");
}

function printMatrix(rows, summary) {
  console.log("\nMODRIK P0 Pilot smoke matrix");
  console.log("============================");
  for (const row of rows) {
    const suffix = row.blocked_gates.length > 0 ? ` [${row.blocked_gates.join(", ")}]` : "";
    console.log(`${row.status.padEnd(8)} ${row.label}${suffix}`);
  }
  console.log(`\nPASS=${summary.pass} FAIL=${summary.fail} BLOCKED=${summary.blocked}`);
}

function gitHead() {
  const execution = runBoundedSync("git", ["rev-parse", "HEAD"], {
    cwd: repoRoot,
    encoding: "utf8",
    shell: process.platform === "win32",
    timeoutMs: gitCommandTimeoutMs,
  });
  return boundedFailureDetail(execution, "git rev-parse") ? "unknown" : execution.result.stdout.trim();
}

function backendContainsCanonicalCorrelationBoundary() {
  for (const root of [join(repoRoot, "apps/backend/app"), join(repoRoot, "apps/backend/tests")]) {
    if (treeContains(root, "X-Correlation-ID")) return true;
  }
  return false;
}

function evidenceLedgerContains(path, terminalPhrase) {
  if (!existsSync(path)) return false;
  try {
    return readFileSync(path, "utf8").includes(terminalPhrase);
  } catch {
    return false;
  }
}

function treeContains(root, needle) {
  if (!existsSync(root)) return false;
  const entries = readdirSync(root, { withFileTypes: true });
  for (const entry of entries) {
    const path = join(root, entry.name);
    if (entry.isDirectory()) {
      if (treeContains(path, needle)) return true;
      continue;
    }
    if (!entry.isFile() || !/\.(php|md|yaml|yml|json)$/i.test(entry.name)) continue;
    try {
      if (readFileSync(path, "utf8").includes(needle)) return true;
    } catch {
      // Ignore unreadable generated/non-text files; canonical source/tests remain readable.
    }
  }
  return false;
}
