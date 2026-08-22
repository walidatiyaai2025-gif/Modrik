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
url="\${!#}"
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

test("Demo release smoke fails closed when the Student route is unreachable", () => {
  const result = runSmoke({ studentExit: "22" });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /MODRIK_DEPLOY_STUDENT_UNREACHABLE/);
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

test("cPanel deployment workflow keeps the exact release smoke mandatory", () => {
  const workflow = readFileSync(workflowPath, "utf8");
  assert.match(workflow, /name: External post-deploy release smoke/);
  assert.match(workflow, /RELEASE_SHA: \$\{\{ steps\.release\.outputs\.sha \}\}/);
  assert.match(workflow, /bash scripts\/verify-demo-deployment-smoke\.sh "\$RELEASE_SHA"/);
});
