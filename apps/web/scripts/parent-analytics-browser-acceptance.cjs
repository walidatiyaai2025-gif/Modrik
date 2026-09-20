const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require("playwright");

const baseUrl = process.env.MODRIK_PORTAL_BASE_URL || "http://127.0.0.1:3102";
const evidenceDir = process.env.MODRIK_PARENT_EVIDENCE_DIR || path.resolve("evidence/web-parent-runtime");
const expectedSha = process.env.MODRIK_PORTAL_EXPECTED_SHA || "unknown";

const ids = {
  child: "01J000000000000000000000C1",
  foreign: "01J000000000000000000000C2",
  context: "01J00000000000000000000002",
  track: "01J000000000000000000000A1",
  subject: "01J00000000000000000000009",
  skill: "01J00000000000000000000005",
  attempt: "01J00000000000000000000006",
};

fs.mkdirSync(evidenceDir, { recursive: true });

function envelope(data) {
  return { data, meta: { request_id: "123e4567-e89b-42d3-a456-426614174000" } };
}

function problem(status, code, detail) {
  return {
    type: `https://modrik.org/problems/${code.toLowerCase()}`,
    title: status === 404 ? "Resource not found" : "Request rejected",
    status,
    code,
    detail,
    request_id: "123e4567-e89b-42d3-a456-426614174000",
    retryable: false,
  };
}

function childList() {
  return envelope({
    children: [{
      id: ids.child,
      name: "Linked learner",
      locale: "en",
      academic_context: {
        context_id: ids.context,
        year_level: "fixture-year",
        track_title: {
          en: "Grade 6 published curriculum",
          ar: "المنهج المنشور للصف السادس",
          fr: "Programme publié de 6e année",
        },
      },
    }],
  });
}

function analytics() {
  const skill = {
    skill_id: ids.skill,
    skill_reference: "SKILL:FRACTIONS",
    skill_title: { en: "Fractions", ar: "الكسور", fr: "Fractions" },
    topic: null,
    subject: {
      id: ids.subject,
      reference: "SUBJECT:MATH",
      title: { en: "Mathematics", ar: "الرياضيات", fr: "Mathématiques" },
    },
    score_percent: 55,
    display_band: "weak",
    confidence: 0.8,
    evidence_count: 4,
    last_evidence_at: "2026-09-15T07:00:00Z",
    calculated_at: "2026-09-15T07:10:00Z",
    trend: [
      { occurred_at: "2026-09-10T07:00:00Z", score_percent: 40, confidence: 0.6, state_version: 1 },
      { occurred_at: "2026-09-15T07:00:00Z", score_percent: 55, confidence: 0.8, state_version: 2 },
    ],
  };

  return envelope({
    state: "active",
    child: { id: ids.child, name: "Linked learner", locale: "en" },
    academic_context: {
      context_id: ids.context,
      academic_track_id: ids.track,
      track_reference: "TRACK:E2E-GRADE-6",
      year_level: "fixture-year",
      track_title: {
        en: "Grade 6 published curriculum",
        ar: "المنهج المنشور للصف السادس",
        fr: "Programme publié de 6e année",
      },
    },
    activity: {
      attempts_started: 3,
      attempts_completed: 2,
      answered_questions: 8,
      graded_questions: 8,
      correct_questions: 6,
      accuracy_percent: 75,
      practice_time_seconds: 720,
      active_days: 3,
      last_activity_at: "2026-09-20T07:30:00Z",
    },
    assessment_history: [{
      attempt_id: ids.attempt,
      kind: "practice",
      title: { en: "Fractions practice", ar: "تدريب الكسور", fr: "Exercice sur les fractions" },
      status: "graded",
      score: 3,
      max_score: 4,
      score_percent: 75,
      started_at: "2026-09-20T07:00:00Z",
      completed_at: "2026-09-20T07:15:00Z",
    }],
    mastery: {
      skills: [skill],
      topics: [],
      subjects: [{
        id: ids.subject,
        reference: "SUBJECT:MATH",
        title: { en: "Mathematics", ar: "الرياضيات", fr: "Mathématiques" },
        skill_count: 1,
        average_mastery_percent: 55,
      }],
      strong: [],
      attention: [skill],
    },
    revision_attention: {
      algorithm_version: "spaced-repetition-v1",
      due_count: 1,
      attention_count: 1,
      items: [{
        skill_id: ids.skill,
        skill_reference: "SKILL:FRACTIONS",
        skill_title: { en: "Fractions", ar: "الكسور", fr: "Fractions" },
        subject: skill.subject,
        display_band: "weak",
        score_percent: 55,
        last_evidence_at: "2026-09-15T07:00:00Z",
        due_at: "2026-09-18T07:00:00Z",
        due_now: true,
        algorithm_version: "spaced-repetition-v1",
        schedule_mode: "current_mastery_base_interval",
      }],
    },
  });
}

async function installParentBoundary(page, locale) {
  await page.route("**/api/auth/session", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(envelope({
        user_id: "01J000000000000000000000P1",
        locale,
        roles: ["parent"],
      })),
    });
  });

  await page.route("**/api/learning/parent/children", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(childList()),
    });
  });

  await page.route("**/api/learning/parent/children/*/analytics", async (route) => {
    const pathname = new URL(route.request().url()).pathname;
    if (pathname.endsWith(`/${ids.child}/analytics`)) {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(analytics()),
      });
      return;
    }
    await route.fulfill({
      status: 404,
      contentType: "application/problem+json",
      body: JSON.stringify(problem(404, "RESOURCE_NOT_FOUND", "The child analytics resource is unavailable.")),
    });
  });
}

async function setTextScale(page) {
  await page.evaluate(() => {
    const rule = "html { font-size: 200% !important; }";
    let inserted = false;
    for (const sheet of Array.from(document.styleSheets)) {
      try {
        sheet.insertRule(rule, sheet.cssRules.length);
        inserted = true;
        break;
      } catch {
        // Continue to another same-origin stylesheet.
      }
    }
    if (!inserted) document.documentElement.style.fontSize = "200%";
  });
}

async function geometry(page) {
  return page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
    bodyScrollWidth: document.body.scrollWidth,
    bodyClientWidth: document.body.clientWidth,
  }));
}

async function runCase(browser, spec) {
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  const page = await context.newPage();
  await installParentBoundary(page, spec.locale);

  await page.goto(`${baseUrl}/parent`, { waitUntil: "domcontentloaded" });
  const workspace = page.locator('[data-parent-analytics="workspace"]');
  await workspace.waitFor({ state: "visible", timeout: 15000 });
  await page.getByText("Linked learner", { exact: true }).first().waitFor({ state: "visible" });

  if (spec.zoom === 2) await setTextScale(page);

  const expectedDirection = spec.locale === "ar" ? "rtl" : "ltr";
  const direction = await workspace.getAttribute("dir");
  if (direction !== expectedDirection) {
    throw new Error(`${spec.name}: direction expected ${expectedDirection}, got ${direction}`);
  }

  const bounds = await geometry(page);
  if (bounds.scrollWidth > bounds.clientWidth + 1 || bounds.bodyScrollWidth > bounds.bodyClientWidth + 1) {
    throw new Error(`${spec.name}: horizontal overflow document=${bounds.scrollWidth}/${bounds.clientWidth} body=${bounds.bodyScrollWidth}/${bounds.bodyClientWidth}`);
  }

  const selector = workspace.locator("select");
  if (await selector.count() !== 1 || await selector.inputValue() !== ids.child) {
    throw new Error(`${spec.name}: linked child selector mismatch`);
  }

  if (await workspace.getByText("75%", { exact: true }).count() < 1) {
    throw new Error(`${spec.name}: authoritative accuracy missing`);
  }
  if (await workspace.getByText("55%", { exact: true }).count() < 1) {
    throw new Error(`${spec.name}: mastery aggregate missing`);
  }

  const bodyText = (await workspace.innerText()).toLowerCase();
  if (bodyText.includes("leaderboard") || bodyText.includes("sibling rank")) {
    throw new Error(`${spec.name}: competitive sibling comparison surfaced`);
  }

  const foreignStatus = await page.evaluate(async (childId) => {
    const response = await fetch(`/api/learning/parent/children/${childId}/analytics`, {
      headers: { Accept: "application/json, application/problem+json" },
      cache: "no-store",
    });
    return response.status;
  }, ids.foreign);
  if (foreignStatus !== 404) {
    throw new Error(`${spec.name}: foreign child access did not fail closed`);
  }

  const localeButton = workspace.getByRole("button", { name: spec.locale.toUpperCase(), exact: true });
  if (await localeButton.getAttribute("aria-pressed") !== "true") {
    throw new Error(`${spec.name}: locale control mismatch`);
  }

  await page.screenshot({ path: path.join(evidenceDir, `${spec.name}.png`), fullPage: true });
  await context.close();

  return {
    name: spec.name,
    status: "PASS",
    locale: spec.locale,
    viewport: [spec.width, spec.height],
    textScale: spec.zoom,
    direction,
    geometry: bounds,
    authorized_child_id: ids.child,
    foreign_child_status: foreignStatus,
  };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];
  try {
    const cases = [
      { name: "parent-desktop-en-412", locale: "en", width: 412, height: 915, zoom: 1 },
      { name: "parent-mobile-fr-390-200", locale: "fr", width: 390, height: 844, zoom: 2 },
      { name: "parent-mobile-ar-360-200", locale: "ar", width: 360, height: 800, zoom: 2 },
    ];

    for (const spec of cases) results.push(await runCase(browser, spec));

    fs.writeFileSync(path.join(evidenceDir, "acceptance.json"), JSON.stringify({
      schema_version: "modrik.parent-analytics-browser.v1",
      status: "PASS",
      expected_sha: expectedSha,
      cases: results,
    }, null, 2) + "\n");
    console.log(`MODRIK Parent Analytics browser acceptance: ${results.length} PASS / 0 FAIL`);
  } catch (error) {
    fs.writeFileSync(path.join(evidenceDir, "acceptance.json"), JSON.stringify({
      schema_version: "modrik.parent-analytics-browser.v1",
      status: "FAIL",
      expected_sha: expectedSha,
      cases: results,
      error: error instanceof Error ? error.message : String(error),
    }, null, 2) + "\n");
    console.error(error);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
