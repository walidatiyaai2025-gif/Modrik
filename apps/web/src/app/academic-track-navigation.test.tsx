import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

import { studentCopy } from "./student-copy";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");
const selector = readFileSync(new URL("./academic-track-selector.tsx", import.meta.url), "utf8");

test("academic track is a first-class student navigation destination", () => {
  assert.match(workspace, /type WorkspaceView = [^;]*"academic"/);
  assert.match(
    workspace,
    /aria-current=\{view === "academic" \? "page" : undefined\}[\s\S]*?onClick=\{\(\) => setView\("academic"\)\}[\s\S]*?labels\.academicTrack/,
  );
  assert.match(workspace, /view === "academic"/);
  assert.match(workspace, /labels\.academicTrackTitle/);
  assert.match(workspace, /<AcademicTrackSelector/);

  assert.equal(studentCopy.en.academicTrack, "Academic track");
  assert.equal(studentCopy.ar.academicTrack, "المسار الأكاديمي");
  assert.equal(studentCopy.fr.academicTrack, "Parcours académique");
});

test("published catalogue stays primary while destructive track reset remains isolated to academic settings", () => {
  assert.match(workspace, /view === "catalogue"/);
  assert.match(workspace, /setView\("catalogue"\)/);
  assert.match(
    workspace,
    /context\?\.state !== "active" \? \([\s\S]*?<AcademicTrackSelector[\s\S]*?\) : view === "academic" \? \([\s\S]*?<AcademicTrackSelector/,
  );

  const catalogueStart = workspace.indexOf(') : view === "catalogue" ? (');
  const studyStart = workspace.indexOf(') : view === "study" ? (', catalogueStart);
  assert.ok(catalogueStart >= 0 && studyStart > catalogueStart);
  const catalogueView = workspace.slice(catalogueStart, studyStart);
  assert.match(catalogueView, /catalogue\.subjects\.map/);
  assert.doesNotMatch(catalogueView, /<AcademicTrackSelector/);
});

test("student track change reuses the existing catalogue and reset authority", () => {
  assert.match(selector, /learningApi\.academicTracks\(\)/);
  assert.match(selector, /labels\.yearLabel/);
  assert.match(selector, /yearOptions\.map/);
  assert.match(selector, /visibleTracks\.map/);
  assert.match(selector, /track\.year\.key === nextYearKey/);
  assert.match(selector, /learningApi\.resetAcademicContext\(selectedTrack\.id, key\)/);
  assert.match(selector, /learningApi\.activateAcademicContext\(selectedTrack\.id, key\)/);
  assert.match(selector, /selectedId === currentTrackId \|\| !confirmed/);
  assert.match(selector, /type="checkbox"/);
  assert.doesNotMatch(selector, /type="text"[^>]*academic_track/i);
  assert.doesNotMatch(workspace, /academic_track_id\s*[:=]/);
});

test("track-change copy explains archive-not-delete and no history migration in AR EN FR", () => {
  for (const locale of ["ar", "en", "fr"] as const) {
    const labels = studentCopy[locale];
    assert.ok(labels.academicTrackTitle.length > 0);
    assert.ok(labels.academicTrackSubtitle.length > 0);
    assert.ok(labels.trackChangeSafetyTitle.length > 0);
    assert.ok(labels.trackChangeSafetyBody.length > 0);
    assert.ok(labels.resetBody.length > 0);
    assert.ok(labels.resetGap.length > 0);
  }

  assert.match(studentCopy.en.trackChangeSafetyBody, /archived/);
  assert.match(studentCopy.en.trackChangeSafetyBody, /not moved/);
  assert.match(studentCopy.ar.trackChangeSafetyBody, /مؤرشفين/);
  assert.match(studentCopy.ar.trackChangeSafetyBody, /لا يتم نقل/);
  assert.match(studentCopy.fr.trackChangeSafetyBody, /archivés/);
  assert.match(studentCopy.fr.trackChangeSafetyBody, /ne sont pas transférées/);
});
