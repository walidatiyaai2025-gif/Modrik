import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const inspectorSource = readFileSync(new URL("../app/runtime-inspector.tsx", import.meta.url), "utf8");
const inspectorStyles = readFileSync(new URL("../app/runtime-inspector.module.css", import.meta.url), "utf8");
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
