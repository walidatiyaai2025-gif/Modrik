import assert from "node:assert/strict";
import test from "node:test";

import { academicTrackCopy } from "./academic-track-selector";

const engineeringTerms = [
  /\bbackend\b/i,
  /same logical operation/i,
  /academic-context reset/i,
  /الخادم/u,
  /نفس العملية المنطقية/u,
  /\bserveur\b/i,
  /même opération logique/i,
];

test("academic transition copy stays learner-first in AR EN FR", () => {
  for (const locale of ["ar", "en", "fr"] as const) {
    const labels = academicTrackCopy[locale];
    const learnerCopy = Object.values(labels).join(" ");

    for (const term of engineeringTerms) {
      assert.doesNotMatch(learnerCopy, term, `${locale} copy exposed ${term}`);
    }

    assert.ok(labels.yearLabel.length > 0);
    assert.ok(labels.yearHelp.length > 0);
    assert.ok(labels.resetBody.length > 0);
    assert.ok(labels.syncWarning.length > 0);
    assert.ok(labels.confirm.length > 0);
    assert.ok(labels.failed.length > 0);
  }
});

test("academic transition copy preserves material consequences and safe failure outcome", () => {
  assert.match(academicTrackCopy.en.resetBody, /attempts/);
  assert.match(academicTrackCopy.en.resetBody, /progress/);
  assert.match(academicTrackCopy.en.resetBody, /archived/);
  assert.match(academicTrackCopy.en.resetBody, /not deleted/);
  assert.match(academicTrackCopy.en.resetBody, /unfinished/);
  assert.match(academicTrackCopy.en.syncWarning, /pending answers and changes/);
  assert.match(academicTrackCopy.en.failed, /Nothing changed/);
  assert.match(academicTrackCopy.en.failed, /try again/);

  assert.match(academicTrackCopy.ar.resetBody, /محاولاتك/);
  assert.match(academicTrackCopy.ar.resetBody, /تقدّمك/);
  assert.match(academicTrackCopy.ar.resetBody, /أرشفة/);
  assert.match(academicTrackCopy.ar.resetBody, /لن يتم حذفها/);
  assert.match(academicTrackCopy.ar.resetBody, /غير مكتمل/);
  assert.match(academicTrackCopy.ar.syncWarning, /الإجابات والتغييرات المعلّقة/);
  assert.match(academicTrackCopy.ar.failed, /لم يتغير شيء/);
  assert.match(academicTrackCopy.ar.failed, /حاول مرة أخرى/);

  assert.match(academicTrackCopy.fr.resetBody, /tentatives/);
  assert.match(academicTrackCopy.fr.resetBody, /progression/);
  assert.match(academicTrackCopy.fr.resetBody, /archivés/);
  assert.match(academicTrackCopy.fr.resetBody, /pas supprimés/);
  assert.match(academicTrackCopy.fr.resetBody, /inachevé/);
  assert.match(academicTrackCopy.fr.syncWarning, /réponses et modifications en attente/);
  assert.match(academicTrackCopy.fr.failed, /Rien n’a changé/);
  assert.match(academicTrackCopy.fr.failed, /réessayez/i);
});
