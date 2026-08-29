import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const selector = readFileSync(new URL("./academic-track-selector.tsx", import.meta.url), "utf8");

test("academic catalogue preserves Backend-curated year and track ordering", () => {
  assert.match(selector, /const nextTracks = await learningApi\.academicTracks\(\);[\s\S]*?setTracks\(nextTracks\)/);
  assert.match(selector, /for \(const track of tracks\)[\s\S]*?options\.set\(track\.year\.key, track\.year\.label\)/);
  assert.match(selector, /Array\.from\(options, \(\[key, label\]\) => \(\{ key, label \}\)\)/);
  assert.match(selector, /tracks\.filter\(\(track\) => track\.year\.key === selectedYearKey\)/);
  assert.doesNotMatch(selector, /\.sort\(/);
});

test("academic catalogue renders the Backend-provided localized year label instead of deriving one client-side", () => {
  assert.match(selector, /track\.year\.label/);
  assert.doesNotMatch(selector, /year\.key\.replace|parseInt\(.*year|Number\(.*year/i);
});
