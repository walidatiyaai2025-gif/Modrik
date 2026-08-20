import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import {
  legalBlockerIds,
  legalBlockers,
  publicDirection,
  publicHref,
  publicLocales,
  publicPageKeys,
  publicPages,
} from "./content";

const expectedSlugs = [
  "about",
  "account-deletion",
  "admin-guide",
  "contact",
  "content-policy",
  "cookies",
  "disclaimer",
  "goal",
  "help",
  "landing",
  "mission",
  "privacy",
  "safety",
  "support",
  "terms",
  "vision",
];

function allPageText() {
  return publicPageKeys
    .flatMap((key) => {
      const page = publicPages[key];
      return [
        ...Object.values(page.title),
        ...Object.values(page.summary),
        ...Object.values(page.seoDescription),
        ...page.sections.flatMap((section) => [
          ...Object.values(section.title),
          ...section.paragraphs.flatMap((paragraph) => Object.values(paragraph)),
          ...(section.bullets ?? []).flatMap((bullet) => Object.values(bullet)),
        ]),
      ];
    })
    .join("\n");
}

test("publishes exactly the Issue #32 route set with stable locale links", () => {
  assert.deepEqual(
    publicPageKeys.map((key) => publicPages[key].slug).sort(),
    expectedSlugs,
  );
  assert.equal(new Set(expectedSlugs).size, expectedSlugs.length);
  assert.equal(publicHref("privacy", "en"), "/privacy");
  assert.equal(publicHref("privacy", "ar"), "/privacy?lang=ar");
  assert.equal(publicHref("privacy", "fr"), "/privacy?lang=fr");
});

test("every public surface has complete AR EN FR copy and correct direction", () => {
  assert.equal(publicDirection("ar"), "rtl");
  assert.equal(publicDirection("en"), "ltr");
  assert.equal(publicDirection("fr"), "ltr");

  for (const key of publicPageKeys) {
    const page = publicPages[key];
    for (const locale of publicLocales) {
      assert.ok(page.title[locale].trim(), `${key} title missing ${locale}`);
      assert.ok(page.summary[locale].trim(), `${key} summary missing ${locale}`);
      assert.ok(page.seoDescription[locale].trim(), `${key} SEO description missing ${locale}`);
      for (const section of page.sections) {
        assert.ok(section.title[locale].trim(), `${key}/${section.id} title missing ${locale}`);
        for (const paragraph of section.paragraphs) {
          assert.ok(paragraph[locale].trim(), `${key}/${section.id} paragraph missing ${locale}`);
        }
      }
    }
  }
});

test("all owner-controlled legal facts remain explicit blockers", () => {
  const required = [
    "LEGAL_ENTITY_CONTROLLER",
    "PUBLIC_CONTACT",
    "JURISDICTION",
    "PROCESSING_BASES",
    "VENDOR_INVENTORY",
    "INTERNATIONAL_TRANSFERS",
    "RETENTION_SCHEDULE",
    "AGE_GUARDIAN_POLICY",
    "SAFETY_ESCALATION_CONTACT",
    "COPYRIGHT_TAKEDOWN_CONTACT",
    "SUPPORT_CHANNEL_HOURS",
    "POLICY_EFFECTIVE_DATE",
    "POLICY_VERSION",
  ];
  assert.deepEqual([...legalBlockerIds], required);

  for (const blockerId of legalBlockerIds) {
    assert.match(legalBlockers[blockerId].en, /^BLOCKED —/);
    assert.match(legalBlockers[blockerId].ar, /^محجوب —/);
    assert.match(legalBlockers[blockerId].fr, /^BLOQUÉ —/);
  }

  for (const key of publicPageKeys.filter((pageKey) => publicPages[pageKey].template)) {
    const blockers = publicPages[key].sections.flatMap((section) => section.blockers ?? []);
    assert.ok(blockers.length > 0, `${key} template must expose at least one blocker`);
    assert.equal(publicPages[key].indexable, false, `${key} template must remain noindex`);
  }
});

test("public copy contains no fabricated scale, partnership or approval claims", () => {
  const copy = allPageText();
  assert.doesNotMatch(copy, /\b\d+[,+]?\s*(students|learners|schools)\b/i);
  assert.doesNotMatch(copy, /\btrusted by\b/i);
  assert.doesNotMatch(copy, /\bpartner(ed)? with (schools?|cambridge|pearson)\b/i);
  assert.doesNotMatch(copy, /\bapproved by (cambridge|pearson|an exam board)\b/i);
  assert.doesNotMatch(copy, /\bguaranteed (grade|score|result|admission)\b/i);
});

test("Web reuses the canonical Coming Soon horizontal logo byte-for-byte", () => {
  const webLogo = readFileSync(new URL("../../public/brand/logo-horizontal.svg", import.meta.url), "utf8");
  const canonicalLogo = readFileSync(
    new URL("../../../../deploy/coming-soon/assets/logo-horizontal.svg", import.meta.url),
    "utf8",
  );
  assert.equal(webLogo, canonicalLogo);
});
