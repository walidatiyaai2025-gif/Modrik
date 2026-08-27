"use strict";

const crypto = require("node:crypto");
const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const appDir = path.join(targetDir, "apps", "web");
const evidenceDir = path.resolve(process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".runtime", "web-browser-evidence"));
const candidate = process.env.MODRIK_E2E_CANDIDATE || "current-tree-catalogue-core";
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3200);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4200);
const baseURL = `http://127.0.0.1:${appPort}`;

const ids = {
  user: "01J00000000000000000000001",
  context: "01J00000000000000000000002",
  lesson: "01J00000000000000000000003",
  quiz: "01J00000000000000000000004",
  node: "01J00000000000000000000005",
  attempt: "01J00000000000000000000006",
  question: "01J00000000000000000000007",
  session: "01J00000000000000000000008",
  subject: "01J00000000000000000000009",
  unit: "01J00000000000000000000010",
  topic: "01J00000000000000000000011",
  trackA: "01J000000000000000000000A1",
  trackB: "01J000000000000000000000A2",
};

const viewports = [
  { name: "desktop-en", width: 1440, height: 1000, locale: "en", textScale: 1 },
  { name: "desktop-ar", width: 1024, height: 900, locale: "ar", textScale: 1 },
  { name: "tablet-fr", width: 768, height: 900, locale: "fr", textScale: 1 },
  { name: "mobile-en-390", width: 390, height: 844, locale: "en", textScale: 1 },
  { name: "mobile-fr-360-200", width: 360, height: 800, locale: "fr", textScale: 2 },
  { name: "mobile-ar-320-200", width: 320, height: 720, locale: "ar", textScale: 2 },
];

const evidence = {
  schema_version: "modrik.web.catalogue-browser-runtime-evidence.v1",
  candidate,
  expected_sha: process.env.MODRIK_E2E_EXPECTED_SHA || null,
  observed_sha: process.env.MODRIK_E2E_OBSERVED_SHA || null,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  text_scale_method: "cssom-root-font-size-200-percent",
  security: {
    traces_recorded: false,
    screenshots_recorded: false,
    videos_recorded: false,
    dom_dumps_recorded: false,
    request_response_bodies_recorded: false,
    credentials_recorded: false,
  },
  cases: [],
};

const failures = [];
let caseId = 0;

function fail(code) {
  throw new Error(code);
}

function check(value, code) {
  if (!value) fail(code);
}

function safeFailureCode(error) {
  const message = error instanceof Error ? error.message : "";
  return /^E2E_[A-Z0-9_:-]+$/.test(message) ? message : "E2E_BROWSER_ASSERTION_FAILED";
}

async function runCase(name, metadata, fn) {
  const started = Date.now();
  try {
    await fn();
    evidence.cases.push({ id: ++caseId, name, status: "PASS", duration_ms: Date.now() - started, ...metadata });
  } catch (error) {
    const failureCode = safeFailureCode(error);
    failures.push(`${name}:${failureCode}`);
    evidence.cases.push({ id: ++caseId, name, status: "FAIL", failure_code: failureCode, duration_ms: Date.now() - started, ...metadata });
  }
}

function envelope(data) {
  return { data, meta: { request_id: "catalogue-browser-e2e" } };
}

function problem(status, code, detail, requestId = "catalogue-browser-e2e") {
  return {
    type: `https://modrik.org/problems/${code.toLowerCase()}`,
    title: status === 401 ? "Authentication required" : "Request rejected",
    status,
    code,
    detail,
    request_id: requestId,
    retryable: status >= 500,
  };
}

function sendJson(res, status, payload, type = "application/json") {
  const body = JSON.stringify(payload);
  res.writeHead(status, {
    "Content-Type": type,
    "Cache-Control": "no-store",
    "Content-Length": Buffer.byteLength(body),
  });
  res.end(body);
}

function tracks() {
  return [
    {
      id: ids.trackA,
      year: { key: "fixture-year", label: "Fixture year 6" },
      labels: {
        en: "Published curriculum track with an intentionally extended learner-facing label for responsive browser acceptance",
        ar: "مسار منهج منشور ذو تسمية طويلة مقصودة للتحقق من الاستجابة وإمكانية الوصول في المتصفح",
        fr: "Parcours de programme publié avec un libellé volontairement long pour la validation responsive du navigateur",
      },
    },
    {
      id: ids.trackB,
      year: { key: "fixture-year", label: "Fixture year 6" },
      labels: {
        en: "Alternative published curriculum track used only for reset confirmation browser acceptance",
        ar: "مسار منهج منشور بديل يستخدم فقط للتحقق من تأكيد تغيير المسار في المتصفح",
        fr: "Parcours de programme publié alternatif utilisé uniquement pour vérifier la confirmation du changement",
      },
    },
  ];
}

function contentCatalogue() {
  return {
    state: "active",
    context: {
      context_id: ids.context,
      academic_track_id: mockState.activeTrackId,
      track_reference: "TRACK:E2E-GRADE-6",
      year_level: "fixture-year",
      track_title: {
        en: "Grade 6 published curriculum",
        ar: "المنهج المنشور للصف السادس",
        fr: "Programme publié de 6e année",
      },
    },
    subjects: [
      {
        id: ids.subject,
        reference: "SUBJECT:ARABIC-E2E",
        type: "subject",
        title: { en: "Arabic language", ar: "اللغة العربية", fr: "Langue arabe" },
        lessons: [],
        assessments: [],
        children: [
          {
            id: ids.unit,
            reference: "UNIT:E2E-1",
            type: "unit",
            title: { en: "Unit one", ar: "الوحدة الأولى", fr: "Unité un" },
            lessons: [],
            assessments: [],
            children: [
              {
                id: ids.topic,
                reference: "TOPIC:E2E-1",
                type: "topic",
                title: { en: "Reading and language", ar: "القراءة واللغة", fr: "Lecture et langue" },
                lessons: [
                  {
                    id: ids.lesson,
                    slug: "published-e2e-lesson",
                    content_version: 1,
                    title: { en: "Published Arabic lesson", ar: "درس اللغة العربية المنشور", fr: "Leçon d’arabe publiée" },
                    published_at: "2026-08-27T00:00:00Z",
                  },
                ],
                assessments: [
                  {
                    id: ids.quiz,
                    kind: "practice",
                    blueprint_version: 1,
                    title: { en: "Published practice", ar: "تدريب منشور", fr: "Exercice publié" },
                  },
                ],
                children: [],
              },
            ],
          },
        ],
      },
    ],
    counts: { subjects: 1, lessons: 1, assessments: 1 },
  };
}

function freshMockState(locale = "en") {
  return {
    locale,
    sessionMode: "authenticated",
    sessionDelayMs: 0,
    academicContextStatus: 200,
    academicContextDelayMs: 0,
    academicTracksStatus: 200,
    contentCatalogueStatus: 200,
    accountSessionsStatus: 200,
    activeTrackId: ids.trackA,
    questionSentinel: crypto.randomUUID(),
  };
}

const mockState = freshMockState();
function resetMock(locale = "en") {
  Object.assign(mockState, freshMockState(locale));
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function hasBearer(req) {
  const header = req.headers.authorization;
  return typeof header === "string" && header.startsWith("Bearer ") && header.length > 7;
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on("data", (chunk) => chunks.push(chunk));
    req.on("end", () => resolve(Buffer.concat(chunks).toString("utf8")));
    req.on("error", reject);
  });
}

function attemptPayload() {
  return {
    id: ids.attempt,
    academic_context_id: ids.context,
    quiz_id: ids.quiz,
    status: "in_progress",
    blueprint_version: 1,
    ordering_algorithm: "modrik-fy-v1",
    started_at: "2026-08-27T00:05:00Z",
    completed_at: null,
    archived_at: null,
    questions: [{
      attempt_question_id: ids.question,
      position: 1,
      type: "short_text",
      prompt: { en: mockState.questionSentinel, ar: mockState.questionSentinel, fr: mockState.questionSentinel },
      response_contract: { kind: "short_text", max_length: 64 },
      current_answer: null,
    }],
  };
}

async function handleMock(req, res) {
  const pathname = new URL(req.url, `http://127.0.0.1:${mockPort}`).pathname;

  if (pathname === "/v1/session") {
    if (mockState.sessionDelayMs) await sleep(mockState.sessionDelayMs);
    if (mockState.sessionMode === "error") {
      return sendJson(res, 503, problem(503, "AUTH_SERVICE_UNAVAILABLE", "Acceptance upstream unavailable."), "application/problem+json");
    }
    if (mockState.sessionMode !== "authenticated" || !hasBearer(req)) {
      return sendJson(res, 401, problem(401, "AUTHENTICATION_REQUIRED", "Authentication required."), "application/problem+json");
    }
    return sendJson(res, 200, envelope({ user_id: ids.user, locale: mockState.locale, roles: ["student"] }));
  }

  if (pathname === "/v1/auth/sessions") {
    if (mockState.accountSessionsStatus !== 200) {
      return sendJson(res, mockState.accountSessionsStatus, problem(mockState.accountSessionsStatus, "AUTH_SERVICE_UNAVAILABLE", "Acceptance sessions unavailable."), "application/problem+json");
    }
    return sendJson(res, 200, envelope({ sessions: [{
      id: ids.session,
      name: "Browser acceptance session",
      authenticated_at: "2026-08-27T00:00:00Z",
      last_used_at: "2026-08-27T00:00:00Z",
      expires_at: "2026-08-28T00:00:00Z",
      created_at: "2026-08-27T00:00:00Z",
      is_current: true,
    }] }));
  }

  if (pathname.startsWith("/v1/auth/")) {
    return sendJson(res, 503, problem(503, "PROVIDER_CONFIGURATION_PENDING", "Acceptance provider path disabled."), "application/problem+json");
  }

  if (pathname === "/v1/academic-tracks") {
    if (mockState.academicTracksStatus !== 200) {
      const code = mockState.academicTracksStatus === 401 ? "AUTHENTICATION_REQUIRED" : "LEARNING_SERVICE_UNAVAILABLE";
      return sendJson(res, mockState.academicTracksStatus, problem(mockState.academicTracksStatus, code, "Academic track catalogue unavailable."), "application/problem+json");
    }
    return sendJson(res, 200, envelope({ tracks: tracks() }));
  }

  if (pathname === "/v1/academic-context") {
    if (mockState.academicContextDelayMs) await sleep(mockState.academicContextDelayMs);
    if (mockState.academicContextStatus !== 200) {
      return sendJson(res, mockState.academicContextStatus, problem(mockState.academicContextStatus, "LEARNING_SERVICE_UNAVAILABLE", "Academic context unavailable."), "application/problem+json");
    }
    return sendJson(res, 200, envelope({
      state: "active",
      context_id: ids.context,
      academic_track_id: mockState.activeTrackId,
      year_level: "fixture-year",
      activated_at: "2026-08-27T00:00:00Z",
    }));
  }

  if (pathname === "/v1/academic-context/reset" || pathname === "/v1/academic-context/activate") {
    try {
      const payload = JSON.parse(await readBody(req));
      if (typeof payload.academic_track_id === "string") mockState.activeTrackId = payload.academic_track_id;
    } catch {
      // Acceptance transition input is optional for this harness.
    }
    return sendJson(res, 200, envelope({
      state: "active",
      context_id: ids.context,
      academic_track_id: mockState.activeTrackId,
      year_level: "fixture-year",
      activated_at: "2026-08-27T00:00:00Z",
    }));
  }

  if (pathname === "/v1/content-catalogue") {
    if (mockState.contentCatalogueStatus !== 200) {
      return sendJson(res, mockState.contentCatalogueStatus, problem(mockState.contentCatalogueStatus, "LEARNING_SERVICE_UNAVAILABLE", "Published catalogue unavailable."), "application/problem+json");
    }
    return sendJson(res, 200, envelope(contentCatalogue()));
  }

  if (pathname === `/v1/lessons/${ids.lesson}`) {
    return sendJson(res, 200, envelope({
      id: ids.lesson,
      curriculum_node_id: ids.node,
      content_version: 1,
      title: { en: "Published Arabic lesson", ar: "درس اللغة العربية المنشور", fr: "Leçon d’arabe publiée" },
      practice_quiz_id: ids.quiz,
      blocks: [{
        id: "01J00000000000000000000012",
        position: 1,
        type: "heading",
        content: { en: "Published lesson content", ar: "محتوى الدرس المنشور", fr: "Contenu de la leçon publiée" },
      }],
    }));
  }

  if (pathname === "/v1/progress") {
    return sendJson(res, 200, envelope([{
      academic_context_id: ids.context,
      curriculum_node_id: ids.node,
      mastery: 0.72,
      source_version: 1,
      calculated_at: "2026-08-27T00:00:00Z",
    }]));
  }

  if (pathname === "/v1/attempts" && req.method === "POST") return sendJson(res, 200, envelope(attemptPayload()));
  if (pathname === `/v1/attempts/${ids.attempt}` && req.method === "GET") return sendJson(res, 200, envelope(attemptPayload()));
  if (pathname.startsWith(`/v1/attempts/${ids.attempt}/answers/`) && req.method === "PUT") {
    return sendJson(res, 200, envelope({ revision: 1, value: "", answered_at: "2026-08-27T00:00:00Z" }));
  }
  if (pathname === `/v1/attempts/${ids.attempt}/submit` && req.method === "POST") {
    return sendJson(res, 200, envelope({
      attempt: { ...attemptPayload(), status: "graded", completed_at: "2026-08-27T00:10:00Z" },
      score: 1,
      max_score: 1,
    }));
  }

  return sendJson(res, 404, problem(404, "RESOURCE_NOT_FOUND", "Acceptance route not found."), "application/problem+json");
}

function listen(server, port) {
  return new Promise((resolve, reject) => {
    const onError = (error) => reject(error);
    server.once("error", onError);
    server.listen(port, "127.0.0.1", () => {
      server.off("error", onError);
      resolve();
    });
  });
}

async function waitForHttp(url, timeoutMs = 30000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url, { redirect: "manual" });
      if (response.status > 0) return;
    } catch {
      // Bounded startup retry.
    }
    await sleep(200);
  }
  fail("E2E_APP_START_TIMEOUT");
}

function startNext() {
  const nextBin = path.join(appDir, "node_modules", "next", "dist", "bin", "next");
  return spawn(process.execPath, [nextBin, "start", "-H", "127.0.0.1", "-p", String(appPort)], {
    cwd: appDir,
    env: { ...process.env, MODRIK_API_BASE_URL: `http://127.0.0.1:${mockPort}` },
    stdio: "ignore",
  });
}

async function stopChild(child) {
  if (!child || child.exitCode !== null) return;
  child.kill("SIGTERM");
  const exited = await Promise.race([
    new Promise((resolve) => child.once("exit", () => resolve(true))),
    sleep(2500).then(() => false),
  ]);
  if (!exited && child.exitCode === null) {
    child.kill("SIGKILL");
    await Promise.race([new Promise((resolve) => child.once("exit", resolve)), sleep(1000)]);
  }
}

async function shutdownMock(server) {
  if (typeof server.closeAllConnections === "function") server.closeAllConnections();
  await Promise.race([new Promise((resolve) => server.close(resolve)), sleep(1500)]);
}

function ephemeralSession() {
  return crypto.randomBytes(32).toString("base64url");
}

async function authenticate(context, token = ephemeralSession()) {
  await context.addCookies([{
    name: "modrik_web_session",
    value: token,
    url: baseURL,
    httpOnly: true,
    sameSite: "Lax",
  }]);
  return token;
}

async function setTextScale(page, scale) {
  const fontSize = await page.evaluate((value) => {
    const rule = `html { font-size: ${value === 2 ? "200%" : "100%"} !important; }`;
    let inserted = false;
    for (const sheet of Array.from(document.styleSheets)) {
      try {
        sheet.insertRule(rule, sheet.cssRules.length);
        inserted = true;
        break;
      } catch {
        // Try the next same-origin stylesheet.
      }
    }
    if (!inserted) document.documentElement.style.fontSize = value === 2 ? "200%" : "100%";
    return Number.parseFloat(getComputedStyle(document.documentElement).fontSize);
  }, scale);
  check(scale === 2 ? fontSize >= 30 : fontSize >= 15, "E2E_TEXT_SCALE_NOT_APPLIED");
}

async function noHorizontalOverflow(page, code) {
  const okay = await page.evaluate(() =>
    document.documentElement.scrollWidth <= window.innerWidth + 1
    && document.body.scrollWidth <= window.innerWidth + 1,
  );
  check(okay, code);
}

async function reachable(locator, page, code) {
  check((await locator.count()) > 0, `${code}_MISSING`);
  await locator.scrollIntoViewIfNeeded();
  check(await locator.isVisible(), `${code}_NOT_VISIBLE`);
  const box = await locator.boundingBox();
  const viewport = page.viewportSize();
  check(Boolean(box && viewport), `${code}_NO_GEOMETRY`);
  check(box.x >= -1 && box.x + box.width <= viewport.width + 1, `${code}_HORIZONTAL_CLIP`);
}

async function focusVisible(page, code) {
  check(await page.evaluate(() => {
    const active = document.activeElement;
    if (!(active instanceof HTMLElement) || active === document.body) return false;
    const style = getComputedStyle(active);
    return style.outlineStyle !== "none" && Number.parseFloat(style.outlineWidth || "0") >= 1;
  }), code);
}

async function keyboardLoginOrder(page) {
  await page.locator("body").click({ position: { x: 2, y: 2 } });
  let foundEmail = false;
  for (let index = 0; index < 14; index += 1) {
    await page.keyboard.press("Tab");
    if (await page.evaluate(() => document.activeElement?.getAttribute("name") === "email")) {
      foundEmail = true;
      break;
    }
  }
  check(foundEmail, "E2E_LOGIN_EMAIL_FOCUS_ORDER");
  await focusVisible(page, "E2E_LOGIN_EMAIL_FOCUS_NOT_VISIBLE");
  await page.keyboard.press("Tab");
  check(await page.evaluate(() => document.activeElement?.getAttribute("name") === "password"), "E2E_LOGIN_PASSWORD_FOCUS_ORDER");
  await focusVisible(page, "E2E_LOGIN_PASSWORD_FOCUS_NOT_VISIBLE");
  await page.keyboard.press("Tab");
  check(await page.evaluate(() => document.activeElement?.getAttribute("type") === "submit"), "E2E_LOGIN_SUBMIT_FOCUS_ORDER");
  await focusVisible(page, "E2E_LOGIN_SUBMIT_FOCUS_NOT_VISIBLE");
}

async function noKeyboardTrap(page, code) {
  const seen = new Set();
  for (let index = 0; index < 28; index += 1) {
    await page.keyboard.press("Tab");
    seen.add(await page.evaluate(() => {
      const active = document.activeElement;
      if (!(active instanceof HTMLElement)) return "none";
      return `${active.tagName}|${active.getAttribute("name") || ""}|${active.getAttribute("aria-label") || ""}|${String(active.className || "").slice(0, 50)}`;
    }));
  }
  check(seen.size >= 5, code);
}

async function waitLogin(page) {
  await page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 15000 });
}

async function waitLearning(page) {
  await page.locator(".student-shell").waitFor({ state: "visible", timeout: 15000 });
  await page.locator(".dashboard-stack").first().waitFor({ state: "visible", timeout: 15000 });
}

async function authViewport(browser, spec) {
  resetMock(spec.locale);
  mockState.sessionMode = "unauthenticated";
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitLogin(page);
    await page.locator(".auth-locale button", { hasText: spec.locale.toUpperCase() }).first().click();
    await setTextScale(page, spec.textScale);
    const shell = page.locator(".auth-shell");
    check(await shell.getAttribute("lang") === spec.locale, "E2E_AUTH_LOCALE");
    check(await shell.getAttribute("dir") === (spec.locale === "ar" ? "rtl" : "ltr"), "E2E_AUTH_DIRECTION");
    await noHorizontalOverflow(page, "E2E_AUTH_HORIZONTAL_OVERFLOW");
    await reachable(page.locator("button[type=submit]").first(), page, "E2E_AUTH_SUBMIT");
    await keyboardLoginOrder(page);
    await noKeyboardTrap(page, "E2E_AUTH_KEYBOARD_TRAP");
    await context.setOffline(true);
    await page.waitForTimeout(100);
    check(await page.locator(".auth-notice-offline").isVisible(), "E2E_AUTH_OFFLINE_BANNER");
    check(await page.locator("button[type=submit]").first().isDisabled(), "E2E_AUTH_OFFLINE_DISABLED");
    await context.setOffline(false);
  } finally {
    await context.close();
  }
}

async function learningViewport(browser, spec) {
  resetMock(spec.locale);
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  await authenticate(context);
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitLearning(page);
    await setTextScale(page, spec.textScale);

    const shell = page.locator(".student-shell");
    check(await shell.getAttribute("lang") === spec.locale, "E2E_LEARNING_LOCALE");
    check(await shell.getAttribute("dir") === (spec.locale === "ar" ? "rtl" : "ltr"), "E2E_LEARNING_DIRECTION");
    await noHorizontalOverflow(page, "E2E_LEARNING_HORIZONTAL_OVERFLOW");
    await noKeyboardTrap(page, "E2E_LEARNING_KEYBOARD_TRAP");

    const nav = page.locator(".student-nav button");
    check(await nav.count() === 5, "E2E_LEARNING_NAV_COUNT");
    check(await nav.nth(0).getAttribute("aria-current") === "page", "E2E_CATALOGUE_INITIAL_DESTINATION");
    await reachable(page.locator(".dashboard-hero"), page, "E2E_CATALOGUE_HERO");
    await reachable(page.locator('[data-node-type="subject"]'), page, "E2E_CATALOGUE_SUBJECT");
    await reachable(page.locator('[data-node-type="unit"]'), page, "E2E_CATALOGUE_UNIT");
    await reachable(page.locator('[data-node-type="topic"]'), page, "E2E_CATALOGUE_TOPIC");

    const topic = page.locator('[data-node-type="topic"]');
    const actionGroups = topic.locator(".next-actions");
    check(await actionGroups.count() === 2, "E2E_CATALOGUE_ACTION_GROUPS");

    const lessonButton = actionGroups.nth(0).locator("button").first();
    await reachable(lessonButton, page, "E2E_CATALOGUE_LESSON_ACTION");
    await lessonButton.click();
    await page.locator(".study-layout .context-panel").waitFor({ state: "visible", timeout: 10000 });
    await reachable(page.locator(".lesson-block").first(), page, "E2E_STUDY_PUBLISHED_CONTENT");
    await noHorizontalOverflow(page, "E2E_STUDY_HORIZONTAL_OVERFLOW");

    await nav.nth(0).click();
    await page.locator('[data-node-type="topic"]').waitFor({ state: "visible", timeout: 10000 });
    const assessmentButton = page.locator('[data-node-type="topic"] .next-actions').nth(1).locator("button").first();
    await reachable(assessmentButton, page, "E2E_CATALOGUE_ASSESSMENT_ACTION");
    await assessmentButton.click();
    const start = page.locator(".practice-workbench > .primary-button");
    await reachable(start, page, "E2E_PRACTICE_START");
    check(!(await start.isDisabled()), "E2E_PRACTICE_START_DISABLED");
    await start.click();
    await page.locator(".question-card").waitFor({ state: "visible", timeout: 10000 });
    await reachable(page.locator(".text-answer"), page, "E2E_ATTEMPT_ANSWER_CONTROL");
    await reachable(page.locator(".practice-submit-row button[type=submit]"), page, "E2E_ATTEMPT_SUBMIT");
    await noHorizontalOverflow(page, "E2E_ATTEMPT_HORIZONTAL_OVERFLOW");

    await nav.nth(3).click();
    await reachable(page.locator(".progress-workspace"), page, "E2E_PROGRESS_WORKSPACE");
    await noHorizontalOverflow(page, "E2E_PROGRESS_HORIZONTAL_OVERFLOW");

    await nav.nth(4).click();
    const selectors = page.locator(".academic-track-selector select");
    await selectors.first().waitFor({ state: "visible", timeout: 10000 });
    check(await selectors.count() === 2, "E2E_ACADEMIC_SELECTOR_COUNT");
    const yearSelector = selectors.nth(0);
    const trackSelector = selectors.nth(1);
    await reachable(yearSelector, page, "E2E_ACADEMIC_YEAR_SELECT");
    await reachable(trackSelector, page, "E2E_ACADEMIC_TRACK_SELECT");
    check(await yearSelector.inputValue() === "fixture-year", "E2E_ACADEMIC_YEAR_SELECTION");
    const optionText = await trackSelector.locator("option").first().textContent();
    check(Boolean(optionText && optionText.length >= 60), "E2E_ACADEMIC_LONG_LABEL");
    const confirmAction = page.locator(".academic-track-selector .primary-button");
    check(await confirmAction.isDisabled(), "E2E_ACADEMIC_RESET_INITIAL_DISABLED");
    await trackSelector.selectOption(ids.trackB);
    const consequence = page.locator(".academic-track-selector .reset-consequence");
    await reachable(consequence, page, "E2E_ACADEMIC_RESET_CONSEQUENCE");
    await consequence.locator('input[type="checkbox"]').check();
    await reachable(confirmAction, page, "E2E_ACADEMIC_RESET_CONFIRM");
    check(!(await confirmAction.isDisabled()), "E2E_ACADEMIC_RESET_CONFIRM_DISABLED");
    await noHorizontalOverflow(page, "E2E_ACADEMIC_HORIZONTAL_OVERFLOW");
  } finally {
    await context.close();
  }
}

async function stateAcceptance(browser) {
  resetMock("ar");
  mockState.sessionMode = "unauthenticated";
  mockState.sessionDelayMs = 650;
  let context = await browser.newContext({ viewport: { width: 320, height: 720 } });
  let page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await page.locator(".auth-loading").waitFor({ state: "visible", timeout: 5000 });
    await setTextScale(page, 2);
    await noHorizontalOverflow(page, "E2E_AUTH_LOADING_HORIZONTAL_OVERFLOW");
    await waitLogin(page);
    mockState.sessionDelayMs = 0;
    mockState.sessionMode = "error";
    await page.reload({ waitUntil: "domcontentloaded" });
    await waitLogin(page);
    check(await page.locator(".auth-notice-error").isVisible(), "E2E_AUTH_ERROR_STATE");
    await setTextScale(page, 2);
    await noHorizontalOverflow(page, "E2E_AUTH_ERROR_HORIZONTAL_OVERFLOW");
  } finally {
    await context.close();
  }

  resetMock("fr");
  context = await browser.newContext({ viewport: { width: 320, height: 720 } });
  await authenticate(context);
  page = await context.newPage();
  try {
    mockState.academicContextDelayMs = 650;
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await page.locator(".state-panel").waitFor({ state: "visible", timeout: 8000 });
    await setTextScale(page, 2);
    await waitLearning(page);

    mockState.academicContextDelayMs = 0;
    mockState.contentCatalogueStatus = 503;
    await page.reload({ waitUntil: "domcontentloaded" });
    const contentRetry = page.locator(".state-panel button");
    await contentRetry.waitFor({ state: "visible", timeout: 10000 });
    await reachable(contentRetry, page, "E2E_CONTENT_CATALOGUE_ERROR_RETRY");
    mockState.contentCatalogueStatus = 200;
    await contentRetry.click();
    await waitLearning(page);

    mockState.academicTracksStatus = 503;
    await page.locator(".student-nav button").nth(4).click();
    const catalogueRetry = page.locator("#student-main .empty-panel button").first();
    await catalogueRetry.waitFor({ state: "visible", timeout: 10000 });
    await reachable(catalogueRetry, page, "E2E_ACADEMIC_CATALOGUE_RETRY");
    mockState.academicTracksStatus = 200;
    await catalogueRetry.click();
    await page.locator(".academic-track-selector select").last().waitFor({ state: "visible", timeout: 10000 });

    mockState.accountSessionsStatus = 503;
    await page.locator(".auth-topnav button").nth(1).click();
    const accountRetry = page.locator(".auth-inline-error button");
    await accountRetry.waitFor({ state: "visible", timeout: 10000 });
    await reachable(accountRetry, page, "E2E_ACCOUNT_ERROR_RETRY");
    mockState.accountSessionsStatus = 200;
    await accountRetry.click();
    await page.locator(".auth-session-list").waitFor({ state: "visible", timeout: 10000 });
    await setTextScale(page, 2);
    await noHorizontalOverflow(page, "E2E_ACCOUNT_HORIZONTAL_OVERFLOW");
    await reachable(page.locator(".auth-card-heading button").first(), page, "E2E_ACCOUNT_RETRY_CONTROL");
  } finally {
    await context.close();
  }
}

async function main() {
  check(fs.existsSync(path.join(appDir, "package.json")), "E2E_CATALOGUE_TARGET_MISSING");
  fs.mkdirSync(evidenceDir, { recursive: true });

  const mock = http.createServer((req, res) => {
    handleMock(req, res).catch(() => {
      if (!res.headersSent) sendJson(res, 500, problem(500, "E2E_MOCK_FAILURE", "Acceptance mock failure."), "application/problem+json");
      else res.end();
    });
  });
  await listen(mock, mockPort);

  let app = null;
  let browser = null;
  try {
    app = startNext();
    await waitForHttp(baseURL);
    browser = await chromium.launch({ headless: true });
    for (const spec of viewports) {
      await runCase(`auth:${spec.name}`, { surface: "auth", ...spec }, () => authViewport(browser, spec));
      await runCase(`catalogue-learning:${spec.name}`, { surface: "catalogue-learning", ...spec }, () => learningViewport(browser, spec));
    }
    await runCase(
      "states:auth-catalogue-account",
      { surface: "auth-catalogue", width: 320, height: 720, locale: "ar/fr", textScale: 2 },
      () => stateAcceptance(browser),
    );
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await shutdownMock(mock);
  }

  const output = path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`);
  fs.writeFileSync(output, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });
  const passed = evidence.cases.filter((item) => item.status === "PASS").length;
  const failed = evidence.cases.length - passed;
  console.log(`Catalogue browser runtime acceptance: ${passed} PASS, ${failed} FAIL (${candidate})`);
  if (failures.length) {
    console.error(`Catalogue browser acceptance failures: ${failures.join(", ")}`);
    process.exitCode = 1;
  }
}

main().catch(() => {
  console.error("Catalogue browser runtime acceptance aborted: E2E_CATALOGUE_HARNESS_FAILURE");
  process.exitCode = 1;
});
