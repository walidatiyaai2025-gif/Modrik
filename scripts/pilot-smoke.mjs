#!/usr/bin/env node

import { spawn, spawnSync } from "node:child_process";
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join, relative, resolve } from "node:path";
import process from "node:process";

const repoRoot = resolve(dirname(new URL(import.meta.url).pathname), "..");
const runtimeRoot = join(repoRoot, ".runtime");
const reportPath = join(runtimeRoot, "pilot-smoke-report.json");
const args = new Set(process.argv.slice(2));
const planOnly = args.has("--plan");
const strict = args.has("--strict");
const allowedArgs = new Set(["--plan", "--strict", "--help"]);

for (const arg of args) {
  if (!allowedArgs.has(arg)) {
    console.error(`Unknown pilot smoke option: ${arg}`);
    process.exit(64);
  }
}

if (args.has("--help")) {
  console.log(`MODRIK Pilot smoke harness\n\nUsage:\n  node scripts/pilot-smoke.mjs [--plan] [--strict]\n\n--plan    Validate and print the acceptance plan without executing suites.\n--strict  Exit 2 when any acceptance row is BLOCKED by an unintegrated release dependency.\n\nWithout --strict, real test failures still exit 1 while BLOCKED rows are reported without failing the command.`);
  process.exit(0);
}

const commandSuites = {
  backend: {
    label: "Backend P0 feature suite",
    command: "php",
    commandArgs: ["artisan", "test"],
    cwd: join(repoRoot, "apps/backend"),
  },
  web: {
    label: "Student Web/Public/Auth test suite",
    command: "npm",
    commandArgs: ["run", "test"],
    cwd: join(repoRoot, "apps/web"),
  },
  mobile: {
    label: "Flutter Mobile test suite",
    command: "flutter",
    commandArgs: ["test"],
    cwd: join(repoRoot, "apps/mobile"),
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
};

const acceptanceRows = [
  {
    id: "public-surfaces",
    label: "Public /, trust, help and guide surfaces",
    suites: ["web"],
    evidence: "Public content/render/metadata tests run inside the complete Web test suite.",
  },
  {
    id: "web-auth-session",
    label: "Web sign-in and session restoration",
    suites: ["backend", "web"],
    evidence: "Auth lifecycle + Web Auth/session tests.",
  },
  {
    id: "mobile-auth-session",
    label: "Mobile sign-in and session restoration",
    suites: ["backend", "mobile"],
    evidence: "Auth lifecycle + Mobile Auth/session/widget tests.",
  },
  {
    id: "academic-context",
    label: "Academic-track selection and change",
    suites: ["backend", "web", "mobile"],
    evidence: "Academic context/catalogue lifecycle and client-consumption tests.",
  },
  {
    id: "lesson-read",
    label: "Published lesson read",
    suites: ["fixture"],
    evidence: "Live Next Learning BFF -> Laravel fixture smoke reads a published lesson.",
  },
  {
    id: "practice",
    label: "Practice start, persisted resume and submit",
    suites: ["backend", "fixture", "mobile"],
    evidence: "Authoritative Assessment/learning tests plus live attempt/answer/submit smoke.",
  },
  {
    id: "progress",
    label: "Progress after graded practice",
    suites: ["backend", "fixture"],
    evidence: "Live fixture smoke reads progress after authoritative grading.",
  },
  {
    id: "offline-recovery",
    label: "Offline interruption and process-restart recovery",
    suites: ["backend", "mobile"],
    gates: ["durableRecovery"],
    evidence: "Offline Sync/authority tests plus durable account-scoped recovery-store tests after integration.",
  },
  {
    id: "session-loss-recovery",
    label: "Login/session-loss recovery",
    suites: ["backend", "web", "mobile"],
    evidence: "Session revocation/401 recovery and client credential-clearing tests.",
  },
  {
    id: "academic-change-recovery",
    label: "Academic-track change recovery and stale-state invalidation",
    suites: ["backend", "web", "mobile"],
    gates: ["durableRecovery"],
    evidence: "Academic reset semantics plus durable cache/pending-operation invalidation after integration.",
  },
  {
    id: "admin-publication",
    label: "Admin preparation -> validate -> approve -> publish",
    suites: ["backend"],
    evidence: "Content preparation/publication/destructive-confirmation Feature tests.",
  },
  {
    id: "runtime-diagnostics",
    label: "Runtime diagnostics and Inspector evidence",
    suites: ["backend", "web", "mobile"],
    gates: ["runtimeDiagnostics"],
    evidence: "Canonical Backend correlation boundary plus Web/Mobile bounded Runtime Inspector tests after integration.",
  },
  {
    id: "locales-direction",
    label: "AR/EN/FR and RTL/LTR visible client flows",
    suites: ["web", "mobile"],
    evidence: "Web copy/direction tests and Flutter locale/direction widget tests.",
  },
  {
    id: "compact-large-text",
    label: "Compact and 200%/large-text client evidence",
    suites: ["web", "mobile"],
    evidence: "Responsive Web tests plus Flutter compact/large-text widget coverage on the integrated client tree.",
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
  if (suiteId === "fixture") {
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
  const result = spawnSync(suite.command, suite.commandArgs, {
    cwd: suite.cwd,
    env: process.env,
    stdio: "inherit",
    shell: process.platform === "win32",
  });
  return {
    status: result.status === 0 ? "PASS" : "FAIL",
    exit_code: result.status ?? 1,
    duration_ms: Date.now() - started,
    command: [suite.command, ...suite.commandArgs].join(" "),
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
    const migrate = spawnSync("php", ["artisan", "migrate:fresh", "--seed", "--force"], {
      cwd: join(repoRoot, "apps/backend"),
      env: backendEnv,
      stdio: "inherit",
      shell: process.platform === "win32",
    });
    if (migrate.status !== 0) {
      return fixtureFailure(started, "Laravel fixture migrate/seed failed", migrate.status ?? 1);
    }

    server = spawn("php", ["artisan", "serve", "--host=127.0.0.1", `--port=${port}`], {
      cwd: join(repoRoot, "apps/backend"),
      env: backendEnv,
      stdio: ["ignore", "ignore", "inherit"],
      shell: process.platform === "win32",
    });

    await waitForBackend(`${baseUrl}/up`, server);
    const smoke = spawnSync("npm", ["run", "smoke:fixture"], {
      cwd: join(repoRoot, "apps/web"),
      env: {
        ...process.env,
        MODRIK_API_BASE_URL: baseUrl,
        MODRIK_FIXTURE_BEARER_TOKEN: fixtureToken,
      },
      stdio: "inherit",
      shell: process.platform === "win32",
    });
    if (smoke.status !== 0) {
      return fixtureFailure(started, "Web fixture smoke failed", smoke.status ?? 1);
    }
    return {
      status: "PASS",
      exit_code: 0,
      duration_ms: Date.now() - started,
      command: "Laravel migrate:fresh --seed + server + npm run smoke:fixture",
    };
  } catch (error) {
    return fixtureFailure(started, error instanceof Error ? error.message : String(error), 1);
  } finally {
    if (server && !server.killed) server.kill("SIGTERM");
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
  const result = spawnSync("git", ["rev-parse", "HEAD"], {
    cwd: repoRoot,
    encoding: "utf8",
    shell: process.platform === "win32",
  });
  return result.status === 0 ? result.stdout.trim() : "unknown";
}

function backendContainsCanonicalCorrelationBoundary() {
  for (const root of [join(repoRoot, "apps/backend/app"), join(repoRoot, "apps/backend/tests")]) {
    if (treeContains(root, "X-Correlation-ID")) return true;
  }
  return false;
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
