import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const css = readFileSync(new URL("./globals.css", import.meta.url), "utf8");

test("Learning responsive CSS shrinks intrinsic containers instead of hiding overflow", () => {
  assert.match(
    css,
    /\.student-shell,\s*\.student-shell \*\s*\{[\s\S]*?box-sizing:\s*border-box;/,
  );
  assert.match(
    css,
    /\.student-shell,\s*\.student-frame,[\s\S]*?\.study-layout,[\s\S]*?\.progress-workspace,[\s\S]*?\.empty-panel\s*\{[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;/,
  );
  assert.match(
    css,
    /\.nav-item > span:last-child,[\s\S]*?\.mastery-summary,[\s\S]*?\.progress-card > div > \*\s*\{[\s\S]*?overflow-wrap:\s*anywhere;/,
  );
  assert.doesNotMatch(css, /\.student-(?:shell|frame|stage|main)[^{]*\{[^}]*overflow-x:\s*(?:hidden|clip)/);
});

test("Learning locale and navigation controls wrap under 200 percent text pressure", () => {
  assert.match(
    css,
    /\.locale-switcher\s*\{[\s\S]*?flex:\s*0 1 auto;[\s\S]*?flex-wrap:\s*wrap;[\s\S]*?max-inline-size:\s*100%;[\s\S]*?min-inline-size:\s*0;/,
  );
  assert.match(
    css,
    /\.locale-switcher button\s*\{[\s\S]*?flex:\s*1 1 2\.8rem;[\s\S]*?max-inline-size:\s*100%;/,
  );
  assert.match(
    css,
    /@media \(max-width: 680px\)[\s\S]*?\.student-nav\s*\{[\s\S]*?grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/,
  );
  assert.match(
    css,
    /@media \(max-width: 680px\)[\s\S]*?\.locale-switcher\s*\{[\s\S]*?align-self:\s*stretch;[\s\S]*?inline-size:\s*100%;/,
  );
});

test("French dashboard copy may break inside the narrow hero instead of widening the page", () => {
  assert.match(
    css,
    /\.dashboard-hero h2,[\s\S]*?\.dashboard-hero p:not\(\.eyebrow\),[\s\S]*?\.progress-card > div > \*\s*\{[\s\S]*?overflow-wrap:\s*anywhere;/,
  );
  assert.match(
    css,
    /\.dashboard-hero,\s*\.dashboard-hero > \*,[\s\S]*?\.progress-card > div > \*\s*\{[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;/,
  );
});

test("Dashboard actions and academic reset copy wrap instead of imposing min-content width", () => {
  assert.match(
    css,
    /\.dashboard-stack button\s*\{[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;[\s\S]*?overflow-wrap:\s*anywhere;[\s\S]*?white-space:\s*normal;/,
  );
  assert.match(
    css,
    /\.next-actions button > \*,[\s\S]*?\.answer-option > span,[\s\S]*?\.text-answer-label\s*\{[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;/,
  );
  assert.match(
    css,
    /\.next-actions strong,[\s\S]*?\.reset-consequence p,[\s\S]*?\.answer-option > span\s*\{[\s\S]*?overflow-wrap:\s*anywhere;[\s\S]*?white-space:\s*normal;/,
  );
});

test("Collapsed Learning grids use a zero min-content floor", () => {
  assert.match(
    css,
    /@media \(max-width: 900px\)[\s\S]*?\.student-frame,\s*\.dashboard-columns,\s*\.study-layout,\s*\.practice-layout\s*\{[\s\S]*?grid-template-columns:\s*minmax\(0, 1fr\);/,
  );
  assert.match(css, /\.progress-card > div\s*\{[\s\S]*?flex-wrap:\s*wrap;/);
  assert.match(
    css,
    /@media \(max-width: 680px\)[\s\S]*?\.mastery-summary\s*\{[\s\S]*?width:\s*100%;/,
  );
});
