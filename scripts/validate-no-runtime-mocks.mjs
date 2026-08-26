#!/usr/bin/env node

import { existsSync, readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, extname, join, relative, resolve } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

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
  /auth\.fixture/i,
  /FixtureBearerAuthentication/,
];
const forbiddenRuntimeDoubleNames = /(FixtureBearerAuthentication|MockRepository|FakeRepository|StubRepository)/i;
const sourceExtensions = new Set([".php", ".ts", ".tsx", ".js", ".jsx", ".mjs", ".mts", ".dart"]);

export function scanRuntimeMocks(root = repoRoot) {
  const violations = [];

  for (const runtimeRoot of runtimeRoots) {
    const absoluteRoot = join(root, runtimeRoot);
    if (!existsSync(absoluteRoot)) continue;
    walk(absoluteRoot, root, violations);
  }

  return violations;
}

function walk(path, root, violations) {
  const stat = statSync(path);
  if (stat.isDirectory()) {
    for (const entry of readdirSync(path)) walk(join(path, entry), root, violations);
    return;
  }

  const rel = relative(root, path).replaceAll("\\", "/");
  if (/\.test\.|\.spec\./.test(rel)) return;
  if (!sourceExtensions.has(extname(path))) return;

  const basename = path.split(/[\\/]/).at(-1) ?? "";
  if (forbiddenRuntimeDoubleNames.test(basename)) {
    violations.push(`${rel}: runtime test-double filename`);
  }

  const content = readFileSync(path, "utf8");
  for (const pattern of forbiddenText) {
    if (pattern.test(content)) violations.push(`${rel}: forbidden runtime fixture/mock marker ${pattern}`);
  }
}

function runCli() {
  const violations = scanRuntimeMocks();
  if (violations.length > 0) {
    console.error("Runtime mock/fixture guard failed:");
    for (const violation of violations) console.error(`- ${violation}`);
    process.exitCode = 1;
    return;
  }

  console.log("Runtime mock/fixture guard passed: Demo/production application source contains no fixture-auth or runtime test-double fallback.");
}

if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url) {
  runCli();
}
