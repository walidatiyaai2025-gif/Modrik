import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const copy = readFileSync(new URL("./student-copy.ts", import.meta.url), "utf8");
const css = readFileSync(new URL("./globals.css", import.meta.url), "utf8");

test("Student Web text-size preference is bounded, persisted, and restored", () => {
  assert.match(workspace, /const textScaleStorageKey = "modrik\.student\.text-scale"/);
  assert.match(workspace, /type TextScalePreference = "small" \| "normal" \| "large" \| "extraLarge"/);
  assert.match(workspace, /small: 87\.5/);
  assert.match(workspace, /normal: 100/);
  assert.match(workspace, /large: 125/);
  assert.match(workspace, /extraLarge: 150/);
  assert.match(workspace, /value === "largest"\) return "extraLarge"/);
  assert.match(workspace, /useSyncExternalStore/);
  assert.match(workspace, /localStorage\.getItem\(textScaleStorageKey\)/);
  assert.match(workspace, /localStorage\.setItem\(textScaleStorageKey, next\)/);
  assert.match(workspace, /style\.setProperty\([\s\S]*"font-size"/);
});

test("Student Web exposes localized text-size controls without altering learning authority", () => {
  assert.match(workspace, /className="text-size-switcher"/);
  assert.match(workspace, /aria-pressed=\{textScale === preference\}/);
  assert.match(copy, /textSize: "Text size"/);
  assert.match(copy, /textSizeSmall: "Small text size"/);
  assert.match(copy, /textSizeExtraLarge: "Extra Large text size"/);
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


test("Student Web typography meets #359 question and touch-target baselines", () => {
  assert.match(css, /\.question-card legend \{[\s\S]*font-size: 1\.125rem/);
  assert.match(css, /\.answer-option \{[\s\S]*min-height: 3rem/);
  assert.match(css, /\.student-shell\[lang="ar"\][\s\S]*line-height: 1\.7/);
});
