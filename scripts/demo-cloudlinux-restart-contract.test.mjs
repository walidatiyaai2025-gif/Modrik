import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const runnerPath = path.join(root, "deploy", "demo", "deploy-demo-cpanel-remote.sh");

test("remote deploy uses the cPanel CloudLinux Node.js restart path before release convergence", () => {
  const runner = readFileSync(runnerPath, "utf8");

  assert.match(runner, /restart_cloudlinux_node_app\(\)/);
  assert.match(runner, /cloudlinux-selector/);
  assert.match(runner, /restart --json --interpreter nodejs --user "\$node_user" --app-root "\$app_root_rel"/);
  assert.match(runner, /CloudLinux Node\.js Selector restart did not report success/);
  assert.match(runner, /touch "\$WEB_ROOT\/tmp\/restart\.txt"/);

  const restartCall = runner.indexOf("restart_cloudlinux_node_app");
  const convergence = runner.indexOf("wait-for-demo-web-release.sh", restartCall);
  const deploymentSuccess = runner.indexOf("current-release.txt");

  assert.ok(restartCall >= 0);
  assert.ok(convergence > restartCall);
  assert.ok(deploymentSuccess > convergence);
});
