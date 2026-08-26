import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const inspectorSource = readFileSync(new URL("../app/runtime-inspector.tsx", import.meta.url), "utf8");
const inspectorStyles = readFileSync(new URL("../app/runtime-inspector.module.css", import.meta.url), "utf8");
const layoutSource = readFileSync(new URL("../app/layout.tsx", import.meta.url), "utf8");
const diagnosticsSource = readFileSync(new URL("./runtime-diagnostics.ts", import.meta.url), "utf8");
const inspectorConfigSource = readFileSync(new URL("./runtime-inspector-config.ts", import.meta.url), "utf8");
const errorBoundarySource = readFileSync(new URL("../app/error.tsx", import.meta.url), "utf8");

test("Runtime Inspector keeps keyboard dialog, focus and multilingual affordances explicit", () => {
  assert.match(inspectorSource, /role="dialog"/);
  assert.match(inspectorSource, /aria-modal="true"/);
  assert.match(inspectorSource, /event\.key === "Escape"/);
  assert.match(inspectorSource, /event\.key !== "Tab"/);
  assert.match(inspectorSource, /closeRef\.current\?\.focus\(\)/);
  assert.match(inspectorSource, /const returnFocus = launcherRef\.current/);
  assert.match(inspectorSource, /returnFocus\?\.focus\(\)/);
  assert.match(inspectorSource, /copyCorrelation/);
  assert.match(inspectorSource, /فاحص التشغيل/);
  assert.match(inspectorSource, /Inspecteur d’exécution/);
});

test("governed Demo keeps the sanitized Runtime Inspector permanently available with release trace identity", () => {
  assert.match(layoutSource, /requestHost === "demo\.modrik\.org"/);
  assert.match(layoutSource, /MODRIK_RUNTIME_INSPECTOR_ENABLED: governedDemo/);
  assert.match(layoutSource, /governedDemo \? "demo"/);
  assert.match(layoutSource, /MODRIK_GIT_SHA: process\.env\.MODRIK_GIT_SHA \?\? \(governedDemo \? release : undefined\)/);
  assert.match(inspectorConfigSource, /"pilot", "demo"/);
});

test("Runtime Inspector persists only the bounded sanitized trace ring across browser sessions", () => {
  assert.match(diagnosticsSource, /window\.localStorage\.setItem\(storageKey, JSON\.stringify\(events\)\)/);
  assert.match(diagnosticsSource, /window\.sessionStorage\.removeItem\(storageKey\)/);
  assert.match(diagnosticsSource, /RUNTIME_DIAGNOSTIC_BYTE_LIMIT = 32 \* 1024/);
  assert.match(diagnosticsSource, /No request bodies,/);
});

test("Runtime Inspector styling consumes canonical MODRIK tokens without inventing palette hex values", () => {
  assert.match(inspectorStyles, /var\(--modrik-teal\)/);
  assert.match(inspectorStyles, /var\(--modrik-white\)/);
  assert.match(inspectorStyles, /var\(--modrik-ink\)/);
  assert.doesNotMatch(inspectorStyles, /#[0-9a-f]{3,8}/i);
});

test("React error boundary never renders raw error message, stack or digest", () => {
  assert.match(errorBoundarySource, /recordBrowserException\("react", error\)/);
  assert.doesNotMatch(errorBoundarySource, /error\.message|error\.stack|error\.digest/);
});
