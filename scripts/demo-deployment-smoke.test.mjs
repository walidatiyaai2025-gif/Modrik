import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { chmodSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const smokeScript = path.join(root, "scripts", "verify-demo-deployment-smoke.sh");
const workflowPath = path.join(root, ".github", "workflows", "deploy-demo-cpanel.yml");
const remoteRunnerPath = path.join(root, "deploy", "demo", "deploy-demo-cpanel-remote.sh");
const restartWaitPath = path.join(root, "deploy", "demo", "wait-for-demo-web-release.sh");
const packageScriptPath = path.join(root, "scripts", "package-demo-cpanel.sh");
const release = "0123456789abcdef0123456789abcdef01234567";
const shortRelease = release.slice(0, 12);

const webReleaseBadge = `<div data-testid="modrik-web-release-badge" title="MODRIK deployed release: ${release}">Build ${shortRelease}</div>`;
const landingBody = `${webReleaseBadge}<main data-testid="modrik-landing-page"><a data-testid="modrik-student-portal-entry" href="/student">Student</a></main>`;
const studentBody = `${webReleaseBadge}<div data-testid="modrik-student-portal"><section class="auth-shell"></section></div>`;

function runSmoke({
  apiExit = "0",
  webExit = "0",
  studentExit = "0",
  adminExit = "0",
  webBody = landingBody,
  studentPortalBody = studentBody,
  adminBody = `<span data-testid="modrik-release-badge" title="MODRIK deployed release: ${release}">Build ${shortRelease}</span>`,
  releaseSha = release,
} = {}) {
  const directory = mkdtempSync(path.join(tmpdir(), "modrik-demo-smoke-"));
  const curl = path.join(directory, "curl");

  writeFileSync(curl, `#!/usr/bin/env bash
set -euo pipefail
raw_url="\${!#}"
url="\${raw_url%%\\?*}"
case "$url" in
  "https://demo.test/up")
    exit "\${FAKE_API_EXIT:-0}"
    ;;
  "https://demo.test/")
    if [[ "\${FAKE_WEB_EXIT:-0}" != "0" ]]; then exit "\${FAKE_WEB_EXIT}"; fi
    printf '%s' "\${FAKE_WEB_BODY:-}"
    ;;
  "https://demo.test/student")
    if [[ "\${FAKE_STUDENT_EXIT:-0}" != "0" ]]; then exit "\${FAKE_STUDENT_EXIT}"; fi
    printf '%s' "\${FAKE_STUDENT_BODY:-}"
    ;;
  "https://demo.test/admin/login")
    if [[ "\${FAKE_ADMIN_EXIT:-0}" != "0" ]]; then exit "\${FAKE_ADMIN_EXIT}"; fi
    printf '%s' "\${FAKE_ADMIN_BODY:-}"
    ;;
  *)
    exit 64
    ;;
esac
`);
  chmodSync(curl, 0o755);

  try {
    return spawnSync("bash", [smokeScript, releaseSha], {
      cwd: root,
      encoding: "utf8",
      env: {
        ...process.env,
        PATH: `${directory}:${process.env.PATH ?? ""}`,
        MODRIK_DEMO_API_UP_URL: "https://demo.test/up",
        MODRIK_DEMO_WEB_URL: "https://demo.test/",
        MODRIK_DEMO_STUDENT_URL: "https://demo.test/student",
        MODRIK_DEMO_ADMIN_LOGIN_URL: "https://demo.test/admin/login",
        FAKE_API_EXIT: apiExit,
        FAKE_WEB_EXIT: webExit,
        FAKE_STUDENT_EXIT: studentExit,
        FAKE_ADMIN_EXIT: adminExit,
        FAKE_WEB_BODY: webBody,
        FAKE_STUDENT_BODY: studentPortalBody,
        FAKE_ADMIN_BODY: adminBody,
      },
    });
  } finally {
    rmSync(directory, { recursive: true, force: true });
  }
}

function runRestartWait({ staleLandingAttempts = 0, maxAttempts = 3, originIp = "" } = {}) {
  const directory = mkdtempSync(path.join(tmpdir(), "modrik-demo-restart-"));
  const curl = path.join(directory, "curl");
  const counter = path.join(directory, "landing-count");
  const staleRelease = "a".repeat(40);
  const staleShort = staleRelease.slice(0, 12);
  const staleLandingBody = `<div data-testid="modrik-web-release-badge" title="MODRIK deployed release: ${staleRelease}">Build ${staleShort}</div><main data-testid="modrik-landing-page"><a data-testid="modrik-student-portal-entry" href="/student">Student</a></main>`;

  writeFileSync(curl, `#!/usr/bin/env bash
set -euo pipefail
raw_url="\${!#}"
url="\${raw_url%%\\?*}"
case "$url" in
  "https://demo.test/")
    count=0
    if [[ -f "\${FAKE_COUNTER_FILE}" ]]; then count="$(cat "\${FAKE_COUNTER_FILE}")"; fi
    count=$((count + 1))
    printf '%s' "$count" > "\${FAKE_COUNTER_FILE}"
    if (( count <= FAKE_STALE_LANDING_ATTEMPTS )); then
      printf '%s' "\${FAKE_STALE_LANDING_BODY}"
    else
      printf '%s' "\${FAKE_FRESH_LANDING_BODY}"
    fi
    ;;
  "https://demo.test/student")
    printf '%s' "\${FAKE_FRESH_STUDENT_BODY}"
    ;;
  *)
    exit 64
    ;;
esac
`);
  chmodSync(curl, 0o755);

  try {
    return spawnSync("bash", [restartWaitPath, release], {
      cwd: root,
      encoding: "utf8",
      env: {
        ...process.env,
        PATH: `${directory}:${process.env.PATH ?? ""}`,
        MODRIK_DEMO_WEB_URL: "https://demo.test/",
        MODRIK_DEMO_STUDENT_URL: "https://demo.test/student",
        MODRIK_DEMO_ORIGIN_IP: originIp,
        MODRIK_WEB_RESTART_ATTEMPTS: String(maxAttempts),
        MODRIK_WEB_RESTART_DELAY_SECONDS: "0",
        FAKE_COUNTER_FILE: counter,
        FAKE_STALE_LANDING_ATTEMPTS: String(staleLandingAttempts),
        FAKE_STALE_LANDING_BODY: staleLandingBody,
        FAKE_FRESH_LANDING_BODY: landingBody,
        FAKE_FRESH_STUDENT_BODY: studentBody,
      },
    });
  } finally {
    rmSync(directory, { recursive: true, force: true });
  }
}

test("Demo release smoke accepts matching Landing, Student and Admin portal identities", () => {
  const result = runSmoke();
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, new RegExp(`MODRIK_DEMO_RELEASE_SMOKE_OK release=${shortRelease} portals=landing,student,admin`));
  assert.doesNotMatch(result.stdout, /data-testid|<div|<span|<main|<section/);
});

test("Demo release smoke fails closed when the Web build identity is stale", () => {
  const stale = "aaaaaaaaaaaa";
  const result = runSmoke({
    webBody: `<div data-testid="modrik-web-release-badge">Build ${stale}</div>`,
  });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_WEB_RELEASE_MISMATCH/);
});

test("Demo release smoke fails closed when Landing loses the Student Portal route", () => {
  const result = runSmoke({ webBody: `${webReleaseBadge}<main data-testid="modrik-landing-page"></main>` });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_LANDING_PORTAL_MISMATCH/);
});

test("Demo release smoke fails closed when the Student route is unreachable without leaking its body", () => {
  const result = runSmoke({ studentExit: "22", studentPortalBody: "SECRET_STUDENT_BODY" });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_STUDENT_UNREACHABLE/);
  assert.doesNotMatch(`${result.stdout}${result.stderr}`, /SECRET_STUDENT_BODY/);
});

test("Demo release smoke fails closed when Student serves a stale release", () => {
  const bad = "b".repeat(40);
  const result = runSmoke({
    studentPortalBody: `<div data-testid="modrik-web-release-badge" title="MODRIK deployed release: ${bad}">Build ${bad.slice(0, 12)}</div><div data-testid="modrik-student-portal"><section class="auth-shell"></section></div>`,
  });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_STUDENT_RELEASE_MISMATCH/);
});

test("Demo release smoke rejects a Student route that serves Landing content", () => {
  const result = runSmoke({ studentPortalBody: landingBody });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_STUDENT_PORTAL_MISMATCH/);
});

test("Demo release smoke classifies an unreachable Admin login without printing its body", () => {
  const result = runSmoke({ adminExit: "22", adminBody: "SECRET_BODY_SHOULD_NOT_PRINT" });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_ADMIN_UNREACHABLE/);
  assert.doesNotMatch(`${result.stdout}${result.stderr}`, /SECRET_BODY_SHOULD_NOT_PRINT/);
});

test("Demo release smoke fails closed when Admin serves the wrong release", () => {
  const result = runSmoke({
    adminBody: `<span data-testid="modrik-release-badge" title="MODRIK deployed release: ${"b".repeat(40)}">Build ${"b".repeat(12)}</span>`,
  });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_ADMIN_RELEASE_MISMATCH/);
});

test("Demo release smoke rejects an invalid immutable release SHA before network access", () => {
  const result = runSmoke({ releaseSha: "not-a-sha" });
  assert.equal(result.status, 2);
  assert.match(result.stderr, /MODRIK_DEPLOY_RELEASE_SHA_INVALID/);
});

test("bounded Web restart wait accepts stale then fresh Passenger content", () => {
  const result = runRestartWait({ staleLandingAttempts: 1, maxAttempts: 3 });
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, new RegExp(`MODRIK_DEMO_WEB_RELEASE_READY release=${shortRelease} attempt=2`));
  assert.doesNotMatch(`${result.stdout}${result.stderr}`, /data-testid|<div|<main|<section/);
});

test("bounded Web restart wait can verify the exact cPanel origin independently of public DNS", () => {
  const result = runRestartWait({ originIp: "65.21.208.232" });
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, new RegExp(`MODRIK_DEMO_WEB_ORIGIN_RELEASE_READY release=${shortRelease} attempt=1`));
});

test("bounded Web restart wait fails closed when Passenger remains permanently stale", () => {
  const result = runRestartWait({ staleLandingAttempts: 99, maxAttempts: 2 });
  assert.equal(result.status, 1);
  assert.match(result.stderr, new RegExp(`MODRIK_DEPLOY_WEB_RESTART_TIMEOUT release=${shortRelease} attempts=2`));
  assert.doesNotMatch(`${result.stdout}${result.stderr}`, /data-testid|<div|<main|<section/);
});

test("release probes bypass intermediary caches and the deploy bridge passes the cPanel origin", () => {
  const smoke = readFileSync(smokeScript, "utf8");
  const wait = readFileSync(restartWaitPath, "utf8");
  const workflow = readFileSync(workflowPath, "utf8");

  assert.match(smoke, /Cache-Control: no-cache, no-store, max-age=0/);
  assert.match(smoke, /modrik_release_probe=/);
  assert.match(wait, /Cache-Control: no-cache, no-store, max-age=0/);
  assert.match(wait, /--resolve "demo\.modrik\.org:443:\$ORIGIN_IP"/);
  assert.match(workflow, /putenv\('MODRIK_DEMO_ORIGIN_IP=' \. \$originIp\)/);
  assert.match(workflow, /MODRIK_DEMO_PUBLIC_IPV4=/);
});

test("cPanel deployment workflow keeps the exact release smoke mandatory", () => {
  const workflow = readFileSync(workflowPath, "utf8");
  assert.match(workflow, /name: External post-deploy release smoke/);
  assert.match(workflow, /RELEASE_SHA: \$\{\{ steps\.release\.outputs\.sha \}\}/);
  assert.match(workflow, /bash scripts\/verify-demo-deployment-smoke\.sh "\$RELEASE_SHA"/);
});

test("remote cPanel runner waits for Web restart convergence before recording deployment success", () => {
  const runner = readFileSync(remoteRunnerPath, "utf8");
  const packageScript = readFileSync(packageScriptPath, "utf8");
  assert.match(packageScript, /wait-for-demo-web-release\.sh/);
  assert.match(runner, /Waiting for bounded Student Web restart convergence/);
  assert.match(runner, /touch "\$WEB_ROOT\/tmp\/restart\.txt"/);
  assert.match(runner, /wait-for-demo-web-release\.sh/);
  assert.match(runner, /modrik-landing-page/);
  assert.match(runner, /modrik-student-portal-entry/);
  assert.match(runner, /modrik-student-portal/);
  assert.match(runner, /Student Portal Auth runtime did not render after copy/);
  assert.match(runner, /Student Portal served Landing content after copy/);
  assert.match(runner, /current-release\.txt/);
  assert.ok(runner.indexOf("wait-for-demo-web-release.sh") < runner.indexOf("current-release.txt"));
  assert.ok(runner.indexOf("modrik-student-portal") < runner.indexOf("current-release.txt"));
});
