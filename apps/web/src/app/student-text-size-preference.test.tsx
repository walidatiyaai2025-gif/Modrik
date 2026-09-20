import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const copy = readFileSync(new URL("./student-copy.ts", import.meta.url), "utf8");
const css = readFileSync(new URL("./globals.css", import.meta.url), "utf8");

test("Student Web text-size preference is bounded, persisted, and restored", () => {
  assert.match(workspace, /const textScaleStorageKey = "modrik\.student\.text-scale"/);
  assert.match(workspace, /type TextScalePreference = "normal" \| "large" \| "largest"/);
  assert.match(workspace, /normal: 100/);
  assert.match(workspace, /large: 125/);
  assert.match(workspace, /largest: 150/);
  assert.match(workspace, /useSyncExternalStore/);
  assert.match(workspace, /localStorage\.getItem\(textScaleStorageKey\)/);
  assert.match(workspace, /localStorage\.setItem\(textScaleStorageKey, next\)/);
  assert.match(workspace, /style\.setProperty\([\s\S]*"font-size"/);
});

test("Student Web exposes localized text-size controls without altering learning authority", () => {
  assert.match(workspace, /className="text-size-switcher"/);
  assert.match(workspace, /aria-pressed=\{textScale === preference\}/);
  assert.match(copy, /textSize: "Text size"/);
  assert.match(copy, /textSize: "حجم النص"/);
  assert.match(copy, /textSize: "Taille du texte"/);
  assert.doesNotMatch(workspace, /textScale.*mastery|textScale.*score|textScale.*plan/i);
});

test("Student preference controls retain narrow-layout containment", () => {
  assert.match(css, /\.student-preference-controls/);
  assert.match(css, /\.text-size-switcher/);
  assert.match(css, /flex-wrap: wrap/);
  assert.match(css, /max-width: 100%/);
});
