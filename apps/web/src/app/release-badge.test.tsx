import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const layout = readFileSync(new URL("./layout.tsx", import.meta.url), "utf8");
const css = readFileSync(new URL("./release-badge.css", import.meta.url), "utf8");

test("root layout renders immutable deployed build identity when injected", () => {
  assert.match(layout, /process\.env\.NEXT_PUBLIC_MODRIK_RELEASE_SHA/);
  assert.match(layout, /release\.slice\(0, 12\)/);
  assert.match(layout, /data-testid="modrik-web-release-badge"/);
  assert.match(layout, /title=\{`MODRIK deployed release: \$\{release\}`\}/);
  assert.match(layout, /Build \{shortRelease\}/);
});

test("release badge stays globally visible without intercepting product controls", () => {
  assert.match(css, /position: fixed;/);
  assert.match(css, /z-index: 80;/);
  assert.match(css, /pointer-events: none;/);
  assert.match(css, /white-space: nowrap;/);
});
