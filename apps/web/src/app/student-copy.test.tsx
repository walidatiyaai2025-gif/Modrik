import assert from "node:assert/strict";
import test from "node:test";
import type { Locale } from "@/lib/learning-api";
import { directionForLocale, localize, studentCopy } from "./student-copy";

test("AR EN FR copy has the same complete key set and correct direction", () => {
  const locales: Locale[] = ["ar", "en", "fr"];
  const expectedKeys = Object.keys(studentCopy.en).sort();

  for (const locale of locales) {
    assert.deepEqual(Object.keys(studentCopy[locale]).sort(), expectedKeys);
    assert.ok(Object.values(studentCopy[locale]).every((value) => value.trim().length > 0));
  }

  assert.equal(directionForLocale("ar"), "rtl");
  assert.equal(directionForLocale("en"), "ltr");
  assert.equal(directionForLocale("fr"), "ltr");
});

test("mixed-content localization prefers requested language then deterministic fallback", () => {
  assert.equal(localize({ ar: "قوة", en: "Force" }, "ar"), "قوة");
  assert.equal(localize({ ar: "قوة", en: "Force" }, "fr"), "Force");
  assert.equal(localize({ fr: "Énergie" }, "en"), "Énergie");
  assert.equal(localize({}, "en"), "");
});
