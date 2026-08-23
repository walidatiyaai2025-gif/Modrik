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

test("remote deploy follows CloudLinux end-user CageFS selector semantics before direct compatibility fallback", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /CAGEFS_ENTER_BIN="\$\{MODRIK_CAGEFS_ENTER_BIN:-\/bin\/cagefs_enter\.proxied\}"/);
  assert.match(runner, /\/usr\/sbin\/cloudlinux-selector/);
  assert.match(runner, /cloudlinux_node_action\(\)/);
  assert.match(
    runner,
    /"\$CAGEFS_ENTER_BIN" "\$selector_bin" "\$action" --json --interpreter nodejs --app-root "\$app_root_rel"/,
  );
  assert.match(
    runner,
    /"\$selector_bin" "\$action" --json --interpreter nodejs --user "\$node_user" --app-root "\$app_root_rel"/,
  );

  const cagefsCall = runner.indexOf('"$CAGEFS_ENTER_BIN" "$selector_bin" "$action"');
  const directCall = runner.indexOf('"$selector_bin" "$action" --json --interpreter nodejs --user', cagefsCall);
  assert.ok(cagefsCall >= 0);
  assert.ok(directCall > cagefsCall);
});

test("remote deploy configures a private Passenger log before canonical restart", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /PASSENGER_LOG_FILE="\$\{MODRIK_PASSENGER_LOG_FILE:-\$DEPLOY_ROOT\/logs\/student-web-passenger\.log\}"/);
  assert.match(runner, /configure_cloudlinux_passenger_log\(\)/);
  assert.match(runner, /--passenger-log-file="\$PASSENGER_LOG_FILE"/);
  assert.match(runner, /chmod 600 "\$PASSENGER_LOG_FILE"/);

  const config = runner.indexOf('log "Configuring private Passenger diagnostics before Student Web restart"');
  const configureCall = runner.indexOf("configure_cloudlinux_passenger_log", config);
  const restart = runner.indexOf('log "Requesting canonical cPanel Node.js application restart"', configureCall);

  assert.ok(config >= 0);
  assert.ok(configureCall > config);
  assert.ok(restart > configureCall);
});

test("Passenger restart marker is traversable despite the runner's restrictive umask", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /umask 077/);
  assert.match(runner, /prepare_passenger_restart_marker\(\)/);
  assert.match(runner, /mkdir -p "\$WEB_ROOT\/tmp"/);
  assert.match(runner, /chmod 755 "\$WEB_ROOT\/tmp"/);
  assert.match(runner, /touch "\$WEB_ROOT\/tmp\/restart\.txt"/);
  assert.match(runner, /chmod 644 "\$WEB_ROOT\/tmp\/restart\.txt"/);

  const update = runner.indexOf('log "Updating Student Web payload"');
  const copied = runner.indexOf('cp -a "$SOURCE_ROOT/web/." "$WEB_ROOT/"', update);
  const preparedAfterCopy = runner.indexOf("prepare_passenger_restart_marker", copied);
  const canonicalRestart = runner.indexOf('log "Requesting canonical cPanel Node.js application restart"', preparedAfterCopy);
  const preparedBeforeRestart = runner.indexOf("prepare_passenger_restart_marker", canonicalRestart);
  const restartCall = runner.indexOf("\nrestart_cloudlinux_node_app\n", preparedBeforeRestart);

  assert.ok(copied > update);
  assert.ok(preparedAfterCopy > copied);
  assert.ok(canonicalRestart > preparedAfterCopy);
  assert.ok(preparedBeforeRestart > canonicalRestart);
  assert.ok(restartCall > preparedBeforeRestart);
});

test("remote deploy uses the canonical restart path before release convergence", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /restart_cloudlinux_node_app\(\)/);
  assert.match(runner, /cloudlinux_node_action restart/);
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

test("remote deploy escalates one failed restart window to stop-start recycle before failing closed", () => {
  const runner = readFileSync(runnerPath, "utf8");
  const firstWait = runner.indexOf(
    "wait-for-demo-web-release.sh",
    runner.indexOf('log "Waiting for bounded Student Web restart convergence"'),
  );
  const recycleMarker = runner.indexOf("escalating to one bounded stop/start recycle", firstWait);
  const markerRefresh = runner.indexOf("prepare_passenger_restart_marker", recycleMarker);
  const recycleCall = runner.indexOf("recycle_cloudlinux_node_app", markerRefresh);
  const recycleFn = runner.indexOf("recycle_cloudlinux_node_app() {");
  const stopCall = runner.indexOf("cloudlinux_node_action stop", recycleFn);
  const startCall = runner.indexOf("cloudlinux_node_action start", stopCall);
  const recycleAttempts = runner.indexOf("MODRIK_WEB_RECYCLE_ATTEMPTS", recycleCall);
  const recycleWait = runner.indexOf("wait-for-demo-web-release.sh", recycleAttempts);
  const terminalFailure = runner.indexOf("did not reach the requested release after the bounded stop/start recycle", recycleWait);

  assert.ok(firstWait >= 0);
  assert.ok(recycleMarker > firstWait);
  assert.ok(markerRefresh > recycleMarker);
  assert.ok(recycleCall > markerRefresh);
  assert.ok(stopCall > recycleFn);
  assert.ok(startCall > stopCall);
  assert.ok(recycleAttempts > recycleCall);
  assert.ok(recycleWait > recycleAttempts);
  assert.ok(terminalFailure > recycleWait);
});

test("final convergence failure emits redacted Passenger diagnostics before rollback failure", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /emit_passenger_startup_diagnostics\(\)/);
  assert.match(runner, /MODRIK_PASSENGER_DIAG_BEGIN/);
  assert.match(runner, /\[REDACTED\]/);
  assert.match(runner, /tail -n 120 "\$PASSENGER_LOG_FILE"/);

  const recycleWait = runner.indexOf(
    "wait-for-demo-web-release.sh",
    runner.indexOf("MODRIK_WEB_RECYCLE_ATTEMPTS"),
  );
  const diagnostics = runner.indexOf("emit_passenger_startup_diagnostics", recycleWait);
  const terminalFailure = runner.indexOf("did not reach the requested release after the bounded stop/start recycle", diagnostics);

  assert.ok(recycleWait >= 0);
  assert.ok(diagnostics > recycleWait);
  assert.ok(terminalFailure > diagnostics);
});

test("failed live Web mutation restores the pre-deploy payload and a readable restart marker without recording deployment success", () => {
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
  const restore = recovery.indexOf('tar -xzf "$BACKUP_DIR/web.tar.gz" -C "$WEB_ROOT"');
  const marker = recovery.indexOf("prepare_passenger_restart_marker", restore);
  const restart = recovery.indexOf("restart_cloudlinux_node_app", marker);

  assert.ok(restore >= 0);
  assert.ok(marker > restore);
  assert.ok(restart > marker);
  assert.doesNotMatch(recovery, /artisan (migrate:rollback|down)/);

  const verification = runner.indexOf('log "Verifying public health and portal runtime markers"');
  const currentRelease = runner.indexOf("current-release.txt", verification);
  const successFlag = runner.indexOf("DEPLOY_SUCCEEDED=1", currentRelease);
  assert.ok(currentRelease > verification);
  assert.ok(successFlag > currentRelease);
});
