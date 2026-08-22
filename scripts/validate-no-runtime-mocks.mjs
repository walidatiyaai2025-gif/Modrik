#!/usr/bin/env node

import { existsSync, readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, extname, join, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const runtimeRoots = [
  "apps/backend/app",
  "apps/backend/bootstrap",
  "apps/backend/config",
  "apps/backend/routes",
  "apps/web/src",
  "apps/mobile/lib",
];
const forbiddenText = [
  /MODRIK_FIXTURE_MODE/,
  /MODRIK_FIXTURE_BEARER_TOKEN/,
  /modrik\.fixture/,
  /auth\.fixture/,
  /FixtureBearerAuthentication/,
];
const sourceExtensions = new Set([".php", ".ts", ".tsx", ".js", ".jsx", ".mjs", ".mts", ".dart"]);
const violations = [];

for (const root of runtimeRoots) {
  const absoluteRoot = join(repoRoot, root);
  if (!existsSync(absoluteRoot)) continue;
  walk(absoluteRoot);
}

if (violations.length > 0) {
  console.error("Runtime mock/fixture guard failed:");
  for (const violation of violations) console.error(`- ${violation}`);
  process.exit(1);
}

console.log("Runtime mock/fixture guard passed: no fixture-auth bypass exists in application runtime source.");

function walk(path) {
  const stat = statSync(path);
  if (stat.isDirectory()) {
    const name = path.split(/[\\/]/).at(-1) ?? "";
    if (["tests", "test", "__tests__"].includes(name)) return;
    for (const entry of readdirSync(path)) walk(join(path, entry));
    return;
  }

  const rel = relative(repoRoot, path).replaceAll("\\", "/");
  if (/\.test\.|\.spec\./.test(rel)) return;
  if (!sourceExtensions.has(extname(path))) return;

  const basename = path.split(/[\\/]/).at(-1) ?? "";
  if (/(FixtureBearerAuthentication|MockRepository|FakeRepository|StubRepository)/i.test(basename)) {
    violations.push(`${rel}: runtime double filename`);
  }

  const content = readFileSync(path, "utf8");
  for (const pattern of forbiddenText) {
    if (pattern.test(content)) violations.push(`${rel}: forbidden runtime fixture-auth marker ${pattern}`);
  }
}
