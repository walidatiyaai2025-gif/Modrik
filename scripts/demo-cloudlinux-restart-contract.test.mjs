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

test("remote deploy validates locked Selector desired state before any live mutation", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /cloudlinux_get_node_state_json\(\)/);
  assert.match(runner, /get --json --interpreter nodejs/);
  assert.match(runner, /validate-cloudlinux-node-state\.php/);
  assert.match(runner, /DEMO_DOMAIN="\$\{MODRIK_DEMO_DOMAIN:-demo\.modrik\.org\}"/);
  assert.match(runner, /EXPECTED_NODE_MAJOR="\$\{MODRIK_DEMO_NODE_MAJOR:-22\}"/);
  assert.match(runner, /read_cloudlinux_node_desired_state "" started/);
  assert.match(runner, /ORIGINAL_STARTUP_FILE="\$SELECTOR_TARGET_STARTUP"/);

  const validation = runner.indexOf('log "Validating locked CloudLinux/LiteSpeed desired state before any live mutation"');
  const stateRead = runner.indexOf('read_cloudlinux_node_desired_state "" started', validation);
  const backup = runner.indexOf('log "Creating code backups', stateRead);
  const mutation = runner.indexOf('log "Updating Student Web payload"', backup);

  assert.ok(validation >= 0);
  assert.ok(stateRead > validation);
  assert.ok(backup > stateRead);
  assert.ok(mutation > backup);
});

test("remote deploy resolves the exact cPanel Node runtime from generated Selector configuration", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /resolve_node_runtime_bin\(\)/);
  assert.match(runner, /PassengerNodejs/);
  assert.match(runner, /MODRIK_NODE_RUNTIME_BIN/);
  assert.match(runner, /nodevenv\/public_html\/demo\.modrik\.org\/22\/bin\/node/);

  const resolveFn = runner.indexOf("resolve_node_runtime_bin() {");
  const preflightFn = runner.indexOf("run_exact_node_startup_preflight() {");
  const resolution = runner.indexOf('node_bin="$(resolve_node_runtime_bin)"', preflightFn);

  assert.ok(resolveFn >= 0);
  assert.ok(preflightFn > resolveFn);
  assert.ok(resolution > preflightFn);
});

test("live payload must pass the direct standalone exact-Node loopback preflight before LiteSpeed activation", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /run_exact_node_startup_preflight\(\)/);
  assert.match(runner, /WEB_APPLICATION_ROOT\.txt/);
  assert.match(runner, /server_file="\$WEB_ROOT\/\$app_rel\/server\.js"/);
  assert.match(runner, /RELEASE_SHA\.txt/);
  assert.match(runner, /HOSTNAME=127\.0\.0\.1/);
  assert.match(runner, /NODE_ENV=production/);
  assert.match(runner, /MODRIK_API_BASE_URL=https:\/\/api\.demo\.modrik\.org/);
  assert.match(runner, /MODRIK_ADMIN_PORTAL_URL=https:\/\/api\.demo\.modrik\.org\/admin\/login/);
  assert.match(runner, /"\$node_bin" "\$server_file"/);
  assert.match(runner, /MODRIK deployed release: \$RELEASE_SHA/);
  assert.match(runner, /data-testid=\"modrik-web-release-badge\"/);
  assert.match(runner, /This screen could not be completed/);
  assert.match(runner, /stop_node_preflight_process "\$pid"/);

  const webCopy = runner.indexOf('cp -a "$SOURCE_ROOT/web/." "$WEB_ROOT/"');
  const laravelCaches = runner.indexOf('"$PHP_BIN" artisan view:cache', webCopy);
  const preflight = runner.indexOf("\nrun_exact_node_startup_preflight\n", laravelCaches);
  const startupSwitch = runner.indexOf('log "Switching CloudLinux/LiteSpeed startup to direct Next standalone server:', preflight);
  const compatibilityConfig = runner.indexOf('log "Configuring optional Passenger-compatible diagnostics before Student Web restart"', startupSwitch);
  const canonicalRestart = runner.indexOf('log "Requesting canonical cPanel Node.js application restart"', compatibilityConfig);

  assert.ok(webCopy >= 0);
  assert.ok(laravelCaches > webCopy);
  assert.ok(preflight > laravelCaches);
  assert.ok(startupSwitch > preflight);
  assert.ok(compatibilityConfig > startupSwitch);
  assert.ok(canonicalRestart > compatibilityConfig);
});

test("failed exact-Node startup emits only bounded redacted private diagnostics and then fails closed", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /student-web-node-preflight\.log/);
  assert.match(runner, /chmod 600 "\$log_file"/);
  assert.match(runner, /emit_node_startup_preflight_diagnostics\(\)/);
  assert.match(runner, /MODRIK_NODE_PREFLIGHT_DIAG_BEGIN/);
  assert.match(runner, /\[REDACTED\]/);
  assert.match(runner, /tail -n 120 "\$log_file"/);
  assert.match(runner, /tail -c 16000/);

  const preflightFn = runner.indexOf("run_exact_node_startup_preflight() {");
  const cleanup = runner.indexOf('stop_node_preflight_process "$pid"', preflightFn);
  const diagnostics = runner.indexOf('emit_node_startup_preflight_diagnostics "$log_file"', cleanup);
  const failure = runner.indexOf("failed the direct standalone exact-Node startup preflight before LiteSpeed activation", diagnostics);

  assert.ok(cleanup > preflightFn);
  assert.ok(diagnostics > cleanup);
  assert.ok(failure > diagnostics);
});

test("remote deploy switches the registered startup to the direct Next standalone server and verifies Selector read-back", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /cloudlinux_set_startup_file\(\)/);
  assert.match(runner, /--startup-file "\$startup_file"/);
  assert.match(runner, /DIRECT_STARTUP_FILE="\$WEB_APP_REL\/server\.js"/);
  assert.match(runner, /cloudlinux_set_startup_file "\$DIRECT_STARTUP_FILE"/);
  assert.match(runner, /read_cloudlinux_node_desired_state "\$startup_file" any/);
  assert.match(runner, /CloudLinux startup-file read-back converged/);
  assert.match(runner, /PassengerStartupFile/);

  const preflight = runner.indexOf("\nrun_exact_node_startup_preflight\n");
  const directResolve = runner.indexOf('DIRECT_STARTUP_FILE="$WEB_APP_REL/server.js"', preflight);
  const switchCall = runner.indexOf('cloudlinux_set_startup_file "$DIRECT_STARTUP_FILE"', directResolve);
  const restart = runner.indexOf('log "Requesting canonical cPanel Node.js application restart"', switchCall);

  assert.ok(preflight >= 0);
  assert.ok(directResolve > preflight);
  assert.ok(switchCall > directResolve);
  assert.ok(restart > switchCall);
});

test("Passenger-compatible log is optional and cannot block an otherwise valid LiteSpeed activation", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /PASSENGER_LOG_FILE="\$\{MODRIK_PASSENGER_LOG_FILE:-\$DEPLOY_ROOT\/logs\/student-web-passenger\.log\}"/);
  assert.match(runner, /configure_cloudlinux_passenger_log\(\)/);
  assert.match(runner, /--passenger-log-file="\$PASSENGER_LOG_FILE"/);
  assert.match(runner, /chmod 600 "\$PASSENGER_LOG_FILE"/);
  assert.match(runner, /Optional Passenger-compatible log configuration unavailable/);

  const config = runner.indexOf('log "Configuring optional Passenger-compatible diagnostics before Student Web restart"');
  const configureCall = runner.indexOf("configure_cloudlinux_passenger_log", config);
  const warning = runner.indexOf("Optional Passenger-compatible log configuration unavailable", configureCall);
  const restart = runner.indexOf('log "Requesting canonical cPanel Node.js application restart"', warning);

  assert.ok(config >= 0);
  assert.ok(configureCall > config);
  assert.ok(warning > configureCall);
  assert.ok(restart > warning);
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

test("remote deploy uses one canonical restart before release convergence", () => {
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

test("remote deploy escalates one failed restart window to one stop-start recycle before failing closed", () => {
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
  const terminalFailure = runner.indexOf("did not reach the requested release after direct Next startup plus bounded stop/start recycle", recycleWait);

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

test("final convergence failure emits LiteSpeed stderr/process diagnostics before compatibility diagnostics and rollback", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /emit_litespeed_startup_diagnostics\(\)/);
  assert.match(runner, /stderr\.log/);
  assert.match(runner, /lsnode\|node/);
  assert.match(runner, /MODRIK_LITESPEED_STDERR_DIAG_BEGIN/);
  assert.match(runner, /MODRIK_LITESPEED_PROCESS_DIAG_BEGIN/);
  assert.match(runner, /emit_passenger_startup_diagnostics\(\)/);
  assert.match(runner, /\[REDACTED\]/);

  const recycleWait = runner.indexOf(
    "wait-for-demo-web-release.sh",
    runner.indexOf("MODRIK_WEB_RECYCLE_ATTEMPTS"),
  );
  const litespeedDiagnostics = runner.indexOf("emit_litespeed_startup_diagnostics", recycleWait);
  const compatibilityDiagnostics = runner.indexOf("emit_passenger_startup_diagnostics", litespeedDiagnostics);
  const terminalFailure = runner.indexOf("did not reach the requested release after direct Next startup plus bounded stop/start recycle", compatibilityDiagnostics);

  assert.ok(recycleWait >= 0);
  assert.ok(litespeedDiagnostics > recycleWait);
  assert.ok(compatibilityDiagnostics > litespeedDiagnostics);
  assert.ok(terminalFailure > compatibilityDiagnostics);
});

test("failed live Web mutation restores payload, original startup registration and readable restart marker without recording deployment success", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /recover_previous_web_on_failure\(\)/);
  assert.match(runner, /trap recover_previous_web_on_failure EXIT/);
  assert.match(runner, /WEB_BACKUP_READY=1/);
  assert.match(runner, /WEB_MUTATED=1/);
  assert.match(runner, /ORIGINAL_STARTUP_FILE="\$SELECTOR_TARGET_STARTUP"/);
  assert.match(runner, /original-startup-file\.txt/);
  assert.match(runner, /tar -xzf "\$BACKUP_DIR\/web\.tar\.gz" -C "\$WEB_ROOT"/);
  assert.match(runner, /cloudlinux_set_startup_file "\$ORIGINAL_STARTUP_FILE"/);
  assert.match(runner, /Pre-deploy Student Web payload restored and canonical restart requested/);

  const recoveryStart = runner.indexOf("recover_previous_web_on_failure() {");
  const recoveryEnd = runner.indexOf("\n}\n\ntrap recover_previous_web_on_failure EXIT", recoveryStart);
  const recovery = runner.slice(recoveryStart, recoveryEnd);
  const restore = recovery.indexOf('tar -xzf "$BACKUP_DIR/web.tar.gz" -C "$WEB_ROOT"');
  const restoreStartup = recovery.indexOf('cloudlinux_set_startup_file "$ORIGINAL_STARTUP_FILE"', restore);
  const marker = recovery.indexOf("prepare_passenger_restart_marker", restoreStartup);
  const restart = recovery.indexOf("restart_cloudlinux_node_app", marker);

  assert.ok(restore >= 0);
  assert.ok(restoreStartup > restore);
  assert.ok(marker > restoreStartup);
  assert.ok(restart > marker);
  assert.doesNotMatch(recovery, /artisan (migrate:rollback|down)/);

  const verification = runner.indexOf('log "Verifying public health and portal runtime markers"');
  const currentRelease = runner.indexOf("current-release.txt", verification);
  const successFlag = runner.indexOf("DEPLOY_SUCCEEDED=1", currentRelease);
  assert.ok(currentRelease > verification);
  assert.ok(successFlag > currentRelease);
});
