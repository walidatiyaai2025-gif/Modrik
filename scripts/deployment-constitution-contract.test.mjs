import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const read = (relative) => readFileSync(path.join(root, relative), "utf8");

const constitution = read("docs/project/DEPLOYMENT_CONSTITUTION.md");
const agents = read("AGENTS.md");
const control = read("PROJECT_CONTROL.md");
const runbook = read("deploy/demo/DEPLOY_CPANEL.md");
const packager = read("scripts/package-demo-cpanel.sh");
const remote = read("deploy/demo/deploy-demo-cpanel-remote.sh");

test("deployment constitution is referenced by agent and project governance", () => {
  assert.match(constitution, /GOV-DEPLOY-001/);
  assert.match(agents, /Deployment constitution — `GOV-DEPLOY-001`/);
  assert.match(agents, /docs\/project\/DEPLOYMENT_CONSTITUTION\.md/);
  assert.match(control, /Deployment governance — `GOV-DEPLOY-001`/);
  assert.match(control, /docs\/project\/DEPLOYMENT_CONSTITUTION\.md/);
});

test("LiteSpeed canonical startup is one root-level NAME.js server", () => {
  assert.match(constitution, /Canonical CloudLinux startup file: \*\*root-level `server\.js`\*\*/);
  assert.match(constitution, /`WEB_APPLICATION_ROOT\.txt` must contain `\.`/);
  assert.match(constitution, /nested startup registration such as `apps\/web\/server\.js` is prohibited/);
  assert.match(constitution, /`startup\.cjs` may exist only as a compatibility\/rollback bridge/);
  assert.match(runbook, /If it contains `\.`, the canonical startup is `server\.js`/);
  assert.match(packager, /printf '\.\\n' > "\$OUT_ROOT\/web\/WEB_APPLICATION_ROOT\.txt"/);
  assert.match(packager, /cat > "\$OUT_ROOT\/web\/server\.js"/);
  assert.match(packager, /require\(modrikPath\.join\(modrikAppRoot, "server\.js"\)\)/);
  assert.match(packager, /require\("\.\/server\.js"\)/);
  assert.match(remote, /DIRECT_STARTUP_FILE="server\.js"/);
  assert.match(remote, /cloudlinux_set_startup_file "\$DIRECT_STARTUP_FILE"/);
});

test("release identity remains artifact-owned and exact-SHA gated", () => {
  assert.match(constitution, /without mutable per-release cPanel environment edits/);
  assert.match(constitution, /canonical root `server\.js` injects the packaged release identity/);
  assert.match(packager, /RELEASE_SHA\.txt/);
  assert.match(packager, /process\.env\.MODRIK_RELEASE_SHA = modrikRelease/);
  assert.match(remote, /MODRIK deployed release: \$RELEASE_SHA/);
  assert.match(remote, /wait-for-demo-web-release\.sh/);
  assert.match(control, /exact canonical `main` SHA is the immutable release identity/);
});

test("deployment rollback covers payload and runtime registration", () => {
  assert.match(
    constitution,
    /restore previous Web payload;[\s\S]*restore previous startup-file registration;/,
  );
  assert.match(remote, /ORIGINAL_STARTUP_FILE/);
  assert.match(remote, /cloudlinux_set_startup_file "\$ORIGINAL_STARTUP_FILE"/);
  assert.match(remote, /tar -xzf "\$BACKUP_DIR\/web\.tar\.gz"/);
});

test("manual restart and blind restart-only success are constitutionally forbidden", () => {
  assert.match(constitution, /Manual cPanel restart is an emergency diagnostic or recovery tool only/);
  assert.match(constitution, /blind repeated restart\/stop\/start loops/);
  assert.match(agents, /Manual cPanel restart is emergency diagnostic\/recovery only/);
  assert.match(runbook, /Manual cPanel \*\*RESTART\*\* is emergency diagnostic\/recovery only/);
});
