import assert from "node:assert/strict";
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import test from "node:test";

import { scanRuntimeMocks } from "./validate-no-runtime-mocks.mjs";

function withFixtureTree(callback) {
  const root = mkdtempSync(join(tmpdir(), "modrik-runtime-mock-guard-"));
  try {
    callback(root);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
}

function write(root, path, content) {
  const fullPath = join(root, path);
  mkdirSync(fullPath.slice(0, fullPath.lastIndexOf("/")), { recursive: true });
  writeFileSync(fullPath, content, "utf8");
}

test("accepts production runtime source without fixture/mock authority", () => {
  withFixtureTree((root) => {
    write(root, "apps/backend/app/Auth/SessionService.php", "<?php final class SessionService {}\n");
    write(root, "apps/web/src/lib/session.ts", "export const sessionAuthority = 'laravel';\n");
    write(root, "apps/mobile/lib/session.dart", "const sessionAuthority = 'laravel';\n");

    assert.deepEqual(scanRuntimeMocks(root), []);
  });
});

test("allows fixture-data metadata that does not grant runtime auth authority", () => {
  withFixtureTree((root) => {
    write(
      root,
      "apps/backend/app/Filament/Pages/AssessmentOperations.php",
      "<?php $enabled = config('modrik.fixture.enabled'); $query->where('academic_tracks.is_fixture', false);\n",
    );

    assert.deepEqual(scanRuntimeMocks(root), []);
  });
});

test("rejects a fixture-auth marker introduced into runtime source", () => {
  withFixtureTree((root) => {
    write(root, "apps/web/src/lib/session.ts", "export const flag = 'MODRIK_FIXTURE_MODE';\n");

    const violations = scanRuntimeMocks(root);
    assert.equal(violations.length, 1);
    assert.match(violations[0], /apps\/web\/src\/lib\/session\.ts/);
    assert.match(violations[0], /MODRIK_FIXTURE_MODE/);
  });
});

test("rejects legacy fixture auth aliases even without environment markers", () => {
  withFixtureTree((root) => {
    write(root, "apps/backend/routes/api.php", "<?php Route::middleware('auth.fixture')->get('/v1/session', fn () => []);\n");

    const violations = scanRuntimeMocks(root);
    assert.equal(violations.length, 1);
    assert.match(violations[0], /auth\\\.fixture|auth\.fixture/);
  });
});

test("rejects runtime test-double filenames even without a marker string", () => {
  withFixtureTree((root) => {
    write(root, "apps/backend/app/Learning/FakeRepository.php", "<?php final class FakeRepository {}\n");

    const violations = scanRuntimeMocks(root);
    assert.equal(violations.length, 1);
    assert.match(violations[0], /runtime test-double filename/);
  });
});

test("rejects fixture directories hidden inside production runtime roots", () => {
  withFixtureTree((root) => {
    write(root, "apps/backend/app/fixtures/FixtureBearerAuthentication.php", "<?php // runtime fixture bypass\n");

    const violations = scanRuntimeMocks(root);
    assert.ok(violations.length >= 1);
    assert.match(violations.join("\n"), /apps\/backend\/app\/fixtures\/FixtureBearerAuthentication\.php/);
  });
});

test("allows fixture markers in test-only trees outside production runtime roots", () => {
  withFixtureTree((root) => {
    write(root, "apps/web/tests/fixture-auth.test.ts", "const marker = 'MODRIK_FIXTURE_BEARER_TOKEN';\n");
    write(root, "apps/backend/tests/Feature/FixtureBearerAuthentication.php", "<?php // test-only fixture\n");

    assert.deepEqual(scanRuntimeMocks(root), []);
  });
});
