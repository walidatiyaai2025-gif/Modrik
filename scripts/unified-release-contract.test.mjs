import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const schema = JSON.parse(readFileSync(new URL("../deploy/unified/release-manifest.schema.json", import.meta.url)));
const workflow = readFileSync(new URL("../.github/workflows/unified-release-package.yml", import.meta.url), "utf8");
const packager = readFileSync(new URL("./package-unified-release.sh", import.meta.url), "utf8");

test("unified manifest locks runtime, checksum and rollback policy", () => {
  assert.deepEqual(schema.properties.package_format_version, { const: 1 });
  assert.equal(schema.properties.checksums_file.const, "checksums.json");
  assert.equal(schema.properties.runtime.properties.php.pattern, "^8\\.4(?:\\.|$)");
  assert.equal(schema.properties.runtime.properties.node.pattern, "^22(?:\\.|$)");
  assert.equal(schema.properties.rollback_policy.properties.automatic_database_down.const, false);
  assert.equal(schema.properties.rollback_policy.properties.database_failure_state.const, "requires_operator");
});

test("package is root-shaped deterministic and uploaded under the canonical name", () => {
  assert.match(packager, /modrik-release-\$\{VERSION\}\.zip/);
  assert.match(packager, /zip -Xq/);
  assert.match(workflow, /\.runtime\/modrik-release-\*\.zip/);
  assert.doesNotMatch(workflow, /modrik-unified-\*\.zip/);
});
