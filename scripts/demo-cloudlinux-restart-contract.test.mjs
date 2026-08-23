import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const runnerPath = path.join(root, "deploy", "demo", "deploy-demo-cpanel-remote.sh");

test("remote deploy script remains valid bash", () => {
  const result = spawnSync("bash", ["-n", runnerPath], { encoding: "utf8" });
  assert.equal(result.status, 0, result.stderr || result.stdout);
});

test("remote deploy uses the cPanel CloudLinux Node.js restart path before release convergence", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /restart_cloudlinux_node_app\(\)/);
  assert.match(runner, /cloudlinux-selector/);
  assert.match(runner, /restart --json --interpreter nodejs --user "\$node_user" --app-root "\$app_root_rel"/);
  assert.match(runner, /CloudLinux Node\.js Selector restart did not report success/);
  assert.match(runner, /touch "\$WEB_ROOT\/tmp\/restart\.txt"/);

  const restartSection = runner.indexOf('log "Requesting canonical cPanel Node.js application restart"');
  const restartCall = runner.indexOf("\nrestart_cloudlinux_node_app\n", restartSection);
  const convergence = runner.indexOf("wait-for-demo-web-release.sh", restartCall);
  const deploymentSuccess = runner.indexOf("current-release.txt");

  assert.ok(restartSection >= 0);
  assert.ok(restartCall > restartSection);
  assert.ok(convergence > restartCall);
  assert.ok(deploymentSuccess > convergence);
});

test("remote deploy performs exactly one bounded restart retry before failing closed", () => {
  const runner = readFileSync(runnerPath, "utf8");
  const firstWait = runner.indexOf("wait-for-demo-web-release.sh", runner.indexOf('log "Waiting for bounded Student Web restart convergence"'));
  const retryMarker = runner.indexOf("Initial Student Web convergence window expired", firstWait);
  const retryRestart = runner.indexOf("restart_cloudlinux_node_app", retryMarker);
  const retryAttempts = runner.indexOf("MODRIK_WEB_RESTART_RETRY_ATTEMPTS", retryRestart);
  const retryWait = runner.indexOf("wait-for-demo-web-release.sh", retryAttempts);
  const terminalFailure = runner.indexOf("did not reach the requested release after the bounded restart retry", retryWait);

  assert.ok(firstWait >= 0);
  assert.ok(retryMarker > firstWait);
  assert.ok(retryRestart > retryMarker);
  assert.ok(retryAttempts > retryRestart);
  assert.ok(retryWait > retryAttempts);
  assert.ok(terminalFailure > retryWait);
});

test("failed live Web mutation restores the pre-deploy payload without recording deployment success", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /recover_previous_web_on_failure\(\)/);
  assert.match(runner, /trap recover_previous_web_on_failure EXIT/);
  assert.match(runner, /WEB_BACKUP_READY=1/);
  assert.match(runner, /WEB_MUTATED=1/);
  assert.match(runner, /tar -xzf "\$BACKUP_DIR\/web\.tar\.gz" -C "\$WEB_ROOT"/);
  assert.match(runner, /Pre-deploy Student Web payload restored and canonical restart requested/);

  const recoveryStart = runner.indexOf("recover_previous_web_on_failure() {");
  const recoveryEnd = runner.indexOf("\n}\n\ntrap recover_previous_web_on_failure EXIT", recoveryStart);
  const recovery = runner.slice(recoveryStart, recoveryEnd);
  assert.doesNotMatch(recovery, /artisan (migrate:rollback|down)/);

  const verification = runner.indexOf('log "Verifying public health and portal runtime markers"');
  const currentRelease = runner.indexOf("current-release.txt", verification);
  const successFlag = runner.indexOf("DEPLOY_SUCCEEDED=1", currentRelease);
  assert.ok(currentRelease > verification);
  assert.ok(successFlag > currentRelease);
});
