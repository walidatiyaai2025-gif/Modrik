import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const workflowPath = new URL('../.github/workflows/deploy-demo-cpanel.yml', import.meta.url);
const workflow = fs.readFileSync(workflowPath, 'utf8');

function externalSmokeBlock() {
  const marker = '      - name: External post-deploy smoke\n';
  const start = workflow.indexOf(marker);
  assert.notEqual(start, -1, 'External post-deploy smoke step must remain present.');
  return workflow.slice(start);
}

test('Demo deployment remains manual and checks out canonical main', () => {
  assert.match(workflow, /on:\n  workflow_dispatch:/);
  assert.match(workflow, /- name: Check out canonical main[\s\S]*?ref: main/);
  assert.match(workflow, /- name: Resolve immutable release SHA[\s\S]*?git rev-parse HEAD/);
});

test('external smoke keeps Backend health and verifies exact Web and Admin Build identity', () => {
  const smoke = externalSmokeBlock();

  assert.match(smoke, /RELEASE_SHA: \$\{\{ steps\.release\.outputs\.sha \}\}/);
  assert.match(smoke, /https:\/\/api\.demo\.modrik\.org\/up/);
  assert.match(smoke, /https:\/\/demo\.modrik\.org\//);
  assert.match(smoke, /https:\/\/api\.demo\.modrik\.org\/admin\/login/);

  assert.match(
    smoke,
    /node scripts\/assert-release-badge\.mjs "\$web_body" "modrik-web-release-badge" "\$RELEASE_SHA" "MODRIK_WEB_RELEASE_MISMATCH"/,
  );
  assert.match(
    smoke,
    /node scripts\/assert-release-badge\.mjs "\$admin_body" "modrik-release-badge" "\$RELEASE_SHA" "MODRIK_ADMIN_RELEASE_MISMATCH"/,
  );
  assert.match(smoke, /MODRIK_ADMIN_REACHABILITY_FAILURE/);
});

test('external smoke captures response bodies to temporary files and never prints them', () => {
  const smoke = externalSmokeBlock();

  assert.match(smoke, /web_body="\$RUNNER_TEMP\/modrik-demo-web\.html"/);
  assert.match(smoke, /admin_body="\$RUNNER_TEMP\/modrik-demo-admin\.html"/);
  assert.match(smoke, /--output "\$web_body"/);
  assert.match(smoke, /--output "\$admin_body"/);
  assert.doesNotMatch(smoke, /cat\s+"?\$(?:web_body|admin_body)"?/);
  assert.doesNotMatch(smoke, /head\s+[^\n]*\$(?:web_body|admin_body)/);
  assert.doesNotMatch(smoke, /tail\s+[^\n]*\$(?:web_body|admin_body)/);
});
