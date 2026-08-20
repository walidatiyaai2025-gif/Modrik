"use strict";

const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const profile = process.env.MODRIK_E2E_PROFILE || "core";
const candidate = process.env.MODRIK_E2E_CANDIDATE || "owned-head";
const expectedSha = process.env.MODRIK_E2E_EXPECTED_SHA || "";
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3200);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4200);
const baseURL = `http://127.0.0.1:${appPort}`;
const evidenceDir = path.resolve(process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".e2e-evidence"));
const appDir = path.join(targetDir, "apps", "web");

const ids = {
  user: "01J00000000000000000000001",
  context: "01J00000000000000000000002",
  lesson: "01J00000000000000000000003",
  quiz: "01J00000000000000000000004",
  trackA: "01J000000000000000000000A1",
  trackB: "01J000000000000000000000A2",
  progressNode: "01J00000000000000000000005",
  attempt: "01J00000000000000000000006",
  attemptQuestion: "01J00000000000000000000007",
};

const viewportCases = [
  { name: "desktop-en", width: 1440, height: 1000, locale: "en", textScale: 1 },
  { name: "desktop-ar", width: 1024, height: 900, locale: "ar", textScale: 1 },
  { name: "tablet-fr", width: 768, height: 900, locale: "fr", textScale: 1 },
  { name: "mobile-en-390", width: 390, height: 844, locale: "en", textScale: 1 },
  { name: "mobile-fr-360-200", width: 360, height: 800, locale: "fr", textScale: 2 },
  { name: "mobile-ar-320-200", width: 320, height: 720, locale: "ar", textScale: 2 },
];

const evidence = {
  schema_version: "modrik.web.browser-runtime-evidence.v1",
  candidate,
  profile,
  expected_sha: expectedSha || null,
  observed_sha: process.env.MODRIK_E2E_OBSERVED_SHA || null,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  text_scale_method: "root-font-size-200-percent",
  security: {
    traces_recorded: false,
    screenshots_recorded: false,
    request_response_bodies_recorded: false,
    credentials_recorded: false,
  },
  cases: [],
};

const failures = [];
let caseCounter = 0;

function safeFailureCode(error) {
  const message = error instanceof Error ? error.message : "";
  return /^E2E_[A-Z0-9_:-]+$/.test(message) ? message : "E2E_BROWSER_ASSERTION_FAILED";
}

async function runCase(name, metadata, fn) {
  const started = Date.now();
  try {
    await fn();
    evidence.cases.push({
      id: ++caseCounter,
      name,
      status: "PASS",
      duration_ms: Date.now() - started,
      ...metadata,
    });
  } catch (error) {
    const failureCode = safeFailureCode(error);
    failures.push(`${name}:${failureCode}`);
    evidence.cases.push({
      id: ++caseCounter,
      name,
      status: "FAIL",
      failure_code: failureCode,
      duration_ms: Date.now() - started,
      ...metadata,
    });
  }
}

function check(condition, code) {
  if (!condition) throw new Error(code);
}

function json(res, status, data, contentType = "application/json") {
  const body = JSON.stringify(data);
  res.writeHead(status, {
    "Content-Type": contentType,
    "Cache-Control": "no-store",
    "Content-Length": Buffer.byteLength(body),
  });
  res.end(body);
}

function problem(status, code, detail, requestId = "e2e-request") {
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

function envelope(data) {
  return { data, meta: { request_id: "e2e-request" } };
}

function longTrackLabels() {
  return [
    {
      id: ids.trackA,
      labels: {
        en: "Synthetic academic track with an intentionally extended learner-facing label for responsive browser acceptance",
        ar: "مسار أكاديمي تجريبي ذو تسمية طويلة مقصودة للتحقق من الاستجابة وإمكانية الوصول في المتصفح",
        fr: "Parcours académique synthétique avec un libellé volontairement long pour la validation responsive du navigateur",
      },
    },
    {
      id: ids.trackB,
      labels: {
        en: "Alternative synthetic academic track used only for reset confirmation browser acceptance",
        ar: "مسار أكاديمي تجريبي بديل يستخدم فقط للتحقق من تأكيد تغيير المسار في المتصفح",
        fr: "Parcours académique synthétique alternatif utilisé uniquement pour vérifier la confirmation du changement",
      },
    },
  ];
}

function createMockState() {
  return {
    locale: "en",
    sessionMode: "authenticated",
    sessionDelayMs: 0,
    academicContextStatus: 200,
    academicContextDelayMs: 0,
    academicTracksStatus: 200,
    academicTracksDelayMs: 0,
    accountSessionsStatus: 200,
    accountSessionsDelayMs: 0,
    activeTrackId: ids.trackA,
    attemptStarted: false,
    runtimeQuestionSentinel: crypto.randomUUID(),
  };
}

const mockState = createMockState();

function resetMockState(locale = "en") {
  Object.assign(mockState, createMockState(), { locale });
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function hasBearer(req) {
  const value = req.headers.authorization;
  return typeof value === "string" && value.startsWith("Bearer ") && value.length > "Bearer ".length;
}

async function handleMockRequest(req, res) {
  const url = new URL(req.url, `http://127.0.0.1:${mockPort}`);
  const pathname = url.pathname;

  if (mockState.sessionDelayMs > 0 && pathname === "/v1/session") await delay(mockState.sessionDelayMs);

  if (pathname === "/v1/session") {
    if (mockState.sessionMode === "error") {
      return json(res, 503, problem(503, "AUTH_SERVICE_UNAVAILABLE", "Synthetic upstream unavailable."), "application/problem+json");
    }
    if (mockState.sessionMode !== "authenticated" || !hasBearer(req)) {
      return json(res, 401, problem(401, "AUTHENTICATION_REQUIRED", "Authentication required."), "application/problem+json");
    }
    return json(res, 200, envelope({ user_id: ids.user, locale: mockState.locale, roles: ["student"] }));
  }

  if (pathname === "/v1/auth/sessions") {
    if (mockState.accountSessionsDelayMs > 0) await delay(mockState.accountSessionsDelayMs);
    if (mockState.accountSessionsStatus !== 200) {
      return json(res, mockState.accountSessionsStatus, problem(mockState.accountSessionsStatus, "AUTH_SERVICE_UNAVAILABLE", "Synthetic sessions unavailable."), "application/problem+json");
    }
    return json(res, 200, envelope({
      sessions: [{
        id: "01J00000000000000000000008",
        name: "Browser acceptance session",
        authenticated_at: "2026-08-21T00:00:00Z",
        last_used_at: "2026-08-21T00:00:00Z",
        expires_at: "2026-08-22T00:00:00Z",
        created_at: "2026-08-21T00:00:00Z",
        is_current: true,
      }],
    }));
  }

  if (pathname.startsWith("/v1/auth/")) {
    return json(res, 503, problem(503, "PROVIDER_CONFIGURATION_PENDING", "Synthetic provider path disabled."), "application/problem+json");
  }

  if (pathname === "/v1/academic-tracks") {
    if (mockState.academicTracksDelayMs > 0) await delay(mockState.academicTracksDelayMs);
    if (mockState.academicTracksStatus !== 200) {
      return json(
        res,
        mockState.academicTracksStatus,
        problem(mockState.academicTracksStatus, mockState.academicTracksStatus === 401 ? "AUTHENTICATION_REQUIRED" : "LEARNING_SERVICE_UNAVAILABLE", mockState.academicTracksStatus === 401 ? "Authentication required." : "Synthetic catalogue unavailable.", "e2e-learning-request"),
        "application/problem+json",
      );
    }
    return json(res, 200, envelope({ tracks: longTrackLabels() }));
  }

  if (pathname === "/v1/academic-context") {
    if (mockState.academicContextDelayMs > 0) await delay(mockState.academicContextDelayMs);
    if (mockState.academicContextStatus !== 200) {
      return json(res, mockState.academicContextStatus, problem(mockState.academicContextStatus, "LEARNING_SERVICE_UNAVAILABLE", "Synthetic academic context unavailable."), "application/problem+json");
    }
    return json(res, 200, envelope({
      state: "active",
      context_id: ids.context,
      academic_track_id: mockState.activeTrackId,
      year_level: "fixture-year",
      activated_at: "2026-08-21T00:00:00Z",
    }));
  }

  if (pathname === "/v1/academic-context/reset" || pathname === "/v1/academic-context/activate") {
    let payload = {};
    try {
      payload = JSON.parse(await readRequestBody(req));
    } catch {
      payload = {};
    }
    if (payload && typeof payload.academic_track_id === "string") mockState.activeTrackId = payload.academic_track_id;
    return json(res, 200, envelope({
      state: "active",
      context_id: ids.context,
      academic_track_id: mockState.activeTrackId,
      year_level: "fixture-year",
      activated_at: "2026-08-21T00:00:00Z",
    }));
  }

  if (pathname === `/v1/lessons/${ids.lesson}`) {
    return json(res, 200, envelope({
      id: ids.lesson,
      curriculum_node_id: ids.progressNode,
      content_version: 1,
      title: { en: "Synthetic lesson", ar: "درس تجريبي", fr: "Leçon synthétique" },
      practice_quiz_id: ids.quiz,
      blocks: [],
    }));
  }

  if (pathname === "/v1/progress") {
    return json(res, 200, envelope([{ academic_context_id: ids.context, curriculum_node_id: ids.progressNode, mastery: 0.72, source_version: 1, calculated_at: "2026-08-21T00:00:00Z" }]));
  }

  if (pathname === "/v1/attempts" && req.method === "POST") {
    mockState.attemptStarted = true;
    return json(res, 200, envelope(attemptPayload()));
  }

  if (pathname === `/v1/attempts/${ids.attempt}` && req.method === "GET") {
    return json(res, 200, envelope(attemptPayload()));
  }

  if (pathname.startsWith(`/v1/attempts/${ids.attempt}/answers/`) && req.method === "PUT") {
    return json(res, 200, envelope({ revision: 1, value: "", answered_at: "2026-08-21T00:00:00Z" }));
  }

  if (pathname === `/v1/attempts/${ids.attempt}/submit` && req.method === "POST") {
    const attempt = { ...attemptPayload(), status: "graded", completed_at: "2026-08-21T00:10:00Z" };
    return json(res, 200, envelope({ attempt, score: 1, max_score: 1 }));
  }

  return json(res, 404, problem(404, "RESOURCE_NOT_FOUND", "Synthetic route not found."), "application/problem+json");
}

function attemptPayload() {
  return {
    id: ids.attempt,
    academic_context_id: ids.context,
    quiz_id: ids.quiz,
    status: "in_progress",
    blueprint_version: 1,
    ordering_algorithm: "modrik-fy-v1",
    started_at: "2026-08-21T00:05:00Z",
    completed_at: null,
    archived_at: null,
    questions: [{
      attempt_question_id: ids.attemptQuestion,
      position: 1,
      type: "short_text",
      prompt: { en: mockState.runtimeQuestionSentinel, ar: mockState.runtimeQuestionSentinel, fr: mockState.runtimeQuestionSentinel },
      response_contract: { kind: "short_text", max_length: 64 },
      current_answer: null,
    }],
  };
}

function readRequestBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on("data", (chunk) => chunks.push(chunk));
    req.on("end", () => resolve(Buffer.concat(chunks).toString("utf8")));
    req.on("error", reject);
  });
}

function listen(server, port) {
  return new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(port, "127.0.0.1", () => {
      server.off("error", reject);
      resolve();
    });
  });
}

async function waitForHttp(url, timeoutMs = 60000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url, { redirect: "manual" });
      if (response.status > 0) return;
    } catch {
      // Retry until the bounded deadline.
    }
    await delay(250);
  }
  throw new Error("E2E_APP_START_TIMEOUT");
}

function startNext(extraEnv = {}) {
  const child = spawn("npm", ["run", "start", "--", "-H", "127.0.0.1", "-p", String(appPort)], {
    cwd: appDir,
    env: {
      ...process.env,
      MODRIK_API_BASE_URL: `http://127.0.0.1:${mockPort}`,
      ...extraEnv,
    },
    stdio: ["ignore", "pipe", "pipe"],
  });
  child.stdout.on("data", () => {});
  child.stderr.on("data", () => {});
  return child;
}

async function stopChild(child) {
  if (!child || child.exitCode !== null) return;
  child.kill("SIGTERM");
  await Promise.race([
    new Promise((resolve) => child.once("exit", resolve)),
    delay(5000),
  ]);
  if (child.exitCode === null) child.kill("SIGKILL");
}

function ephemeralSessionValue() {
  return crypto.randomBytes(32).toString("base64url");
}

async function setAuthenticatedCookie(context, token = ephemeralSessionValue()) {
  await context.addCookies([{ name: "modrik_web_session", value: token, url: baseURL, httpOnly: true, sameSite: "Lax" }]);
  return token;
}

async function clearSessionCookie(context) {
  await context.clearCookies();
}

async function setTextScale(page, scale) {
  const fontSize = await page.evaluate((value) => {
    document.documentElement.style.fontSize = value === 2 ? "200%" : "100%";
    return Number.parseFloat(getComputedStyle(document.documentElement).fontSize);
  }, scale);
  check(scale === 2 ? fontSize >= 30 : fontSize >= 15, "E2E_TEXT_SCALE_NOT_APPLIED");
}

async function noHorizontalOverflow(page, code) {
  const okay = await page.evaluate(() => {
    const root = document.documentElement;
    const body = document.body;
    return root.scrollWidth <= window.innerWidth + 1 && body.scrollWidth <= window.innerWidth + 1;
  });
  check(okay, code);
}

async function reachable(locator, page, code) {
  check((await locator.count()) > 0, `${code}_MISSING`);
  await locator.scrollIntoViewIfNeeded();
  check(await locator.isVisible(), `${code}_NOT_VISIBLE`);
  const box = await locator.boundingBox();
  check(Boolean(box), `${code}_NO_BOX`);
  const viewport = page.viewportSize();
  check(Boolean(viewport), `${code}_NO_VIEWPORT`);
  check(box.x >= -1 && box.x + box.width <= viewport.width + 1, `${code}_HORIZONTAL_CLIP`);
}

async function expectFocusVisible(page, code) {
  const visible = await page.evaluate(() => {
    const element = document.activeElement;
    if (!(element instanceof HTMLElement) || element === document.body) return false;
    const style = getComputedStyle(element);
    const outlineWidth = Number.parseFloat(style.outlineWidth || "0");
    return style.outlineStyle !== "none" && outlineWidth >= 1;
  });
  check(visible, code);
}

async function keyboardLoginOrder(page) {
  await page.locator("body").click({ position: { x: 2, y: 2 } });
  let foundEmail = false;
  for (let index = 0; index < 14; index += 1) {
    await page.keyboard.press("Tab");
    const name = await page.evaluate(() => document.activeElement?.getAttribute("name"));
    if (name === "email") {
      foundEmail = true;
      break;
    }
  }
  check(foundEmail, "E2E_LOGIN_EMAIL_FOCUS_ORDER");
  await expectFocusVisible(page, "E2E_LOGIN_EMAIL_FOCUS_NOT_VISIBLE");
  await page.keyboard.press("Tab");
  check(await page.evaluate(() => document.activeElement?.getAttribute("name") === "password"), "E2E_LOGIN_PASSWORD_FOCUS_ORDER");
  await expectFocusVisible(page, "E2E_LOGIN_PASSWORD_FOCUS_NOT_VISIBLE");
  await page.keyboard.press("Tab");
  check(await page.evaluate(() => document.activeElement?.getAttribute("type") === "submit"), "E2E_LOGIN_SUBMIT_FOCUS_ORDER");
  await expectFocusVisible(page, "E2E_LOGIN_SUBMIT_FOCUS_NOT_VISIBLE");
}

async function keyboardNoTrap(page, code) {
  const seen = new Set();
  for (let index = 0; index < 28; index += 1) {
    await page.keyboard.press("Tab");
    const signature = await page.evaluate(() => {
      const element = document.activeElement;
      if (!(element instanceof HTMLElement)) return "none";
      return [element.tagName, element.getAttribute("name") || "", element.getAttribute("aria-label") || "", element.className || ""].join("|").slice(0, 120);
    });
    seen.add(signature);
  }
  check(seen.size >= 5, code);
}

async function waitForLogin(page) {
  await page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 15000 });
}

async function waitForLearning(page) {
  await page.locator(".student-shell").waitFor({ state: "visible", timeout: 20000 });
  await page.locator(".dashboard-stack").waitFor({ state: "visible", timeout: 20000 });
}

async function testLoginViewport(browser, spec) {
  resetMockState(spec.locale);
  mockState.sessionMode = "unauthenticated";
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitForLogin(page);
    const localeButton = page.locator(".auth-locale button", { hasText: spec.locale.toUpperCase() }).first();
    await localeButton.click();
    await setTextScale(page, spec.textScale);
    const shell = page.locator(".auth-shell");
    check(await shell.getAttribute("lang") === spec.locale, "E2E_AUTH_LOCALE");
    check(await shell.getAttribute("dir") === (spec.locale === "ar" ? "rtl" : "ltr"), "E2E_AUTH_DIRECTION");
    await noHorizontalOverflow(page, "E2E_AUTH_HORIZONTAL_OVERFLOW");
    await reachable(page.locator("button[type=submit]").first(), page, "E2E_AUTH_SUBMIT");
    await keyboardLoginOrder(page);
    await keyboardNoTrap(page, "E2E_AUTH_KEYBOARD_TRAP");

    await context.setOffline(true);
    await page.waitForTimeout(50);
    check(await page.locator(".auth-notice-offline").isVisible(), "E2E_AUTH_OFFLINE_BANNER");
    check(await page.locator("button[type=submit]").first().isDisabled(), "E2E_AUTH_OFFLINE_DISABLED");
    await context.setOffline(false);
  } finally {
    await context.close();
  }
}

async function testLearningViewport(browser, spec, inspectorExpected = false) {
  resetMockState(spec.locale);
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  await setAuthenticatedCookie(context);
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitForLearning(page);
    await setTextScale(page, spec.textScale);

    const studentShell = page.locator(".student-shell");
    check(await studentShell.getAttribute("lang") === spec.locale, "E2E_LEARNING_LOCALE");
    check(await studentShell.getAttribute("dir") === (spec.locale === "ar" ? "rtl" : "ltr"), "E2E_LEARNING_DIRECTION");
    await noHorizontalOverflow(page, "E2E_LEARNING_HORIZONTAL_OVERFLOW");
    await keyboardNoTrap(page, "E2E_LEARNING_KEYBOARD_TRAP");

    const nav = page.locator(".student-nav button");
    check(await nav.count() === 4, "E2E_LEARNING_NAV_COUNT");

    await nav.nth(1).click();
    await reachable(page.locator(".lesson-reader"), page, "E2E_STUDY_WORKSPACE");
    await noHorizontalOverflow(page, "E2E_STUDY_HORIZONTAL_OVERFLOW");

    await nav.nth(2).click();
    const start = page.locator(".practice-empty .primary-button");
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

    await nav.nth(0).click();
    const selector = page.locator(".academic-track-selector select");
    await selector.waitFor({ state: "visible", timeout: 10000 });
    await reachable(selector, page, "E2E_ACADEMIC_TRACK_SELECT");
    const optionText = await selector.locator("option").nth(0).textContent();
    check(Boolean(optionText && optionText.length >= 60), "E2E_ACADEMIC_LONG_LABEL_FIXTURE");
    check(await page.locator(".academic-track-selector .primary-button").isDisabled(), "E2E_ACADEMIC_RESET_INITIAL_DISABLED");
    await selector.selectOption(ids.trackB);
    const consequence = page.locator(".reset-consequence");
    await reachable(consequence, page, "E2E_ACADEMIC_RESET_CONSEQUENCE");
    const confirm = consequence.locator("input[type=checkbox]");
    await confirm.check();
    const confirmButton = page.locator(".academic-track-selector .primary-button");
    await reachable(confirmButton, page, "E2E_ACADEMIC_RESET_CONFIRM");
    check(!(await confirmButton.isDisabled()), "E2E_ACADEMIC_RESET_CONFIRM_DISABLED");
    await noHorizontalOverflow(page, "E2E_ACADEMIC_HORIZONTAL_OVERFLOW");

    if (inspectorExpected) {
      check((await page.locator('[data-runtime-inspector="enabled"]').count()) === 1, "E2E_INSPECTOR_EXPECTED_HOST");
    }
  } finally {
    await context.close();
  }
}

async function testAuthRuntimeStates(browser) {
  resetMockState("ar");
  mockState.sessionMode = "unauthenticated";
  mockState.sessionDelayMs = 700;
  const context = await browser.newContext({ viewport: { width: 320, height: 720 } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await page.locator(".auth-loading").waitFor({ state: "visible", timeout: 5000 });
    await setTextScale(page, 2);
    await noHorizontalOverflow(page, "E2E_AUTH_LOADING_HORIZONTAL_OVERFLOW");
    await waitForLogin(page);

    mockState.sessionDelayMs = 0;
    mockState.sessionMode = "error";
    await page.reload({ waitUntil: "domcontentloaded" });
    await waitForLogin(page);
    check(await page.locator(".auth-notice-error").isVisible(), "E2E_AUTH_ERROR_STATE");
    await setTextScale(page, 2);
    await noHorizontalOverflow(page, "E2E_AUTH_ERROR_HORIZONTAL_OVERFLOW");
  } finally {
    await context.close();
  }
}

async function testLoadingErrorAccountStates(browser) {
  resetMockState("fr");
  const context = await browser.newContext({ viewport: { width: 320, height: 720 } });
  await setAuthenticatedCookie(context);
  const page = await context.newPage();
  try {
    mockState.academicContextDelayMs = 800;
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await page.locator(".state-panel").waitFor({ state: "visible", timeout: 10000 });
    check(await page.locator(".state-panel").isVisible(), "E2E_LEARNING_LOADING_STATE");
    await setTextScale(page, 2);
    await waitForLearning(page);

    mockState.academicContextDelayMs = 0;
    mockState.academicContextStatus = 503;
    await page.reload({ waitUntil: "domcontentloaded" });
    await page.locator(".state-panel button").waitFor({ state: "visible", timeout: 10000 });
    await reachable(page.locator(".state-panel button"), page, "E2E_LEARNING_ERROR_RETRY");
    mockState.academicContextStatus = 200;
    await page.locator(".state-panel button").click();
    await waitForLearning(page);

    mockState.academicTracksStatus = 503;
    await page.reload({ waitUntil: "domcontentloaded" });
    await waitForLearning(page);
    const catalogueRetry = page.locator(".context-panel .empty-panel button").first();
    await catalogueRetry.waitFor({ state: "visible", timeout: 10000 });
    await reachable(catalogueRetry, page, "E2E_ACADEMIC_CATALOGUE_RETRY");
    mockState.academicTracksStatus = 200;
    await catalogueRetry.click();
    await page.locator(".academic-track-selector select").waitFor({ state: "visible", timeout: 10000 });

    mockState.accountSessionsStatus = 503;
    const accountButton = page.locator(".auth-topnav button").nth(1);
    await accountButton.click();
    await page.locator(".auth-inline-error").waitFor({ state: "visible", timeout: 10000 });
    await reachable(page.locator(".auth-inline-error button"), page, "E2E_ACCOUNT_ERROR_RETRY");
    mockState.accountSessionsStatus = 200;
    await page.locator(".auth-inline-error button").click();
    await page.locator(".auth-session-list").waitFor({ state: "visible", timeout: 10000 });
    await setTextScale(page, 2);
    await noHorizontalOverflow(page, "E2E_ACCOUNT_HORIZONTAL_OVERFLOW");
    await reachable(page.locator(".auth-card-heading button").first(), page, "E2E_ACCOUNT_RETRY_CONTROL");
  } finally {
    await context.close();
  }
}

async function testSessionSecurity(browser) {
  resetMockState("en");
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    await page.goto(`${baseURL}/landing`, { waitUntil: "domcontentloaded" });

    const staleToken = await setAuthenticatedCookie(context);
    mockState.academicTracksStatus = 401;
    const result401 = await page.evaluate(async () => {
      const response = await fetch("/api/learning/academic-tracks", { cache: "no-store" });
      return {
        status: response.status,
        contentType: response.headers.get("content-type"),
        cacheControl: response.headers.get("cache-control"),
        body: await response.text(),
      };
    });
    const expectedBody = JSON.stringify(problem(401, "AUTHENTICATION_REQUIRED", "Authentication required.", "e2e-learning-request"));
    check(result401.status === 401, "E2E_SESSION_401_STATUS");
    check(result401.contentType?.includes("application/problem+json"), "E2E_SESSION_401_CONTENT_TYPE");
    check(result401.cacheControl === "no-store, private", "E2E_SESSION_401_CACHE_CONTROL");
    check(result401.body === expectedBody, "E2E_SESSION_401_RFC9457_BODY");
    check(!result401.body.includes(staleToken), "E2E_SESSION_401_TOKEN_LEAK");
    const after401 = await context.cookies(baseURL);
    check(!after401.some((cookie) => cookie.name === "modrik_web_session"), "E2E_SESSION_401_COOKIE_NOT_CLEARED");

    mockState.academicTracksStatus = 200;
    const liveToken = await setAuthenticatedCookie(context);
    const result200 = await page.evaluate(async () => {
      const response = await fetch("/api/learning/academic-tracks", { cache: "no-store" });
      return { status: response.status, body: await response.text() };
    });
    check(result200.status === 200, "E2E_SESSION_NON401_STATUS");
    check(!result200.body.includes(liveToken), "E2E_SESSION_NON401_TOKEN_LEAK");
    const after200 = await context.cookies(baseURL);
    check(after200.some((cookie) => cookie.name === "modrik_web_session"), "E2E_SESSION_NON401_COOKIE_CLEARED");
  } finally {
    await clearSessionCookie(context);
    await context.close();
  }
}

async function noHorizontalOverflowElement(locator, code) {
  const okay = await locator.evaluate((element) => element.scrollWidth <= element.clientWidth + 1);
  check(okay, code);
}

async function verifyInspector(page, questionSentinel, answerSentinel, locale, textScale, stressBuffer = false) {
  if (stressBuffer) {
    for (let index = 0; index < 6; index += 1) {
      await page.reload({ waitUntil: "domcontentloaded" });
      await waitForLearning(page);
    }
    await setTextScale(page, textScale);
    const nav = page.locator(".student-nav button");
    await nav.nth(2).click();
    const start = page.locator(".practice-empty .primary-button");
    if (await start.isVisible()) {
      await start.click();
      await page.locator(".question-card").waitFor({ state: "visible", timeout: 10000 });
    }
    await page.locator(".text-answer").fill(answerSentinel);
  }

  const launcher = page.locator('[data-runtime-inspector="enabled"] button[aria-haspopup="dialog"]');
  await reachable(launcher, page, "E2E_INSPECTOR_LAUNCHER");
  await launcher.click();
  const dialog = page.locator('[role="dialog"][aria-modal="true"]');
  await dialog.waitFor({ state: "visible", timeout: 10000 });
  check(await dialog.getAttribute("dir") === (locale === "ar" ? "rtl" : "ltr"), "E2E_INSPECTOR_DIRECTION");
  check(await dialog.getAttribute("lang") === locale, "E2E_INSPECTOR_LOCALE");
  await noHorizontalOverflowElement(dialog, "E2E_INSPECTOR_HORIZONTAL_OVERFLOW");

  const activeIsClose = await page.evaluate(() => {
    const active = document.activeElement;
    return active instanceof HTMLButtonElement && active.getAttribute("aria-label") !== null && active.closest('[role="dialog"]') !== null;
  });
  check(activeIsClose, "E2E_INSPECTOR_INITIAL_FOCUS");
  await expectFocusVisible(page, "E2E_INSPECTOR_CLOSE_FOCUS_NOT_VISIBLE");

  await page.keyboard.press("Shift+Tab");
  check(await page.evaluate(() => document.activeElement?.closest('[role="dialog"]') !== null), "E2E_INSPECTOR_SHIFT_TAB_TRAP");
  await page.keyboard.press("Tab");
  check(await page.evaluate(() => document.activeElement?.closest('[role="dialog"]') !== null), "E2E_INSPECTOR_TAB_TRAP");

  const dialogText = (await dialog.innerText()) || "";
  check(!dialogText.includes(questionSentinel), "E2E_INSPECTOR_QUESTION_DOM_LEAK");
  check(!dialogText.includes(answerSentinel), "E2E_INSPECTOR_ANSWER_DOM_LEAK");

  const sessionBundle = await page.evaluate(() => window.sessionStorage.getItem("modrik_runtime_diagnostics_v1") || "");
  check(!sessionBundle.includes(questionSentinel), "E2E_INSPECTOR_QUESTION_EXPORT_LEAK");
  check(!sessionBundle.includes(answerSentinel), "E2E_INSPECTOR_ANSWER_EXPORT_LEAK");

  await page.context().grantPermissions(["clipboard-read", "clipboard-write"], { origin: baseURL });
  const copyBundleButton = dialog.locator("button").filter({ hasText: /diagnostic json|json التشخيصي|json de diagnostic/i }).first();
  await reachable(copyBundleButton, page, "E2E_INSPECTOR_COPY_BUNDLE");
  await copyBundleButton.click();
  const copiedBundle = await page.evaluate(() => navigator.clipboard.readText());
  check(copiedBundle.includes('"schema_version": "modrik.web.runtime-diagnostics.v1"'), "E2E_INSPECTOR_COPY_SCHEMA");
  check(!copiedBundle.includes(questionSentinel), "E2E_INSPECTOR_QUESTION_COPY_LEAK");
  check(!copiedBundle.includes(answerSentinel), "E2E_INSPECTOR_ANSWER_COPY_LEAK");
  const forbiddenKeys = ["authorization", "cookie", "password", "answer", "question", "option", "prompt", "email", "requestBody", "responseBody"];
  const lower = copiedBundle.toLowerCase();
  for (const key of forbiddenKeys) check(!lower.includes(key.toLowerCase()), "E2E_INSPECTOR_FORBIDDEN_FIELD");

  const downloadButton = dialog.locator("button").filter({ hasText: /download diagnostic json|تنزيل json التشخيصي|télécharger le json de diagnostic/i }).first();
  const downloadPromise = page.waitForEvent("download", { timeout: 5000 });
  await downloadButton.click();
  const download = await downloadPromise;
  check(download.suggestedFilename() === "modrik-runtime-diagnostics.json", "E2E_INSPECTOR_DOWNLOAD_FILENAME");
  await download.cancel().catch(() => {});

  const timelineItems = await dialog.locator("ol > li").count();
  check(timelineItems <= 50, "E2E_INSPECTOR_TIMELINE_BOUND");
  const correlationCopy = dialog.locator("button").filter({ hasText: /correlation|ارتباط|corrélation/i });
  check((await correlationCopy.count()) >= 1, "E2E_INSPECTOR_CORRELATION_COPY");

  const clearButton = dialog.locator("button").filter({ hasText: /clear diagnostics|مسح التشخيصات|effacer les diagnostics/i }).first();
  await reachable(clearButton, page, "E2E_INSPECTOR_CLEAR");
  await clearButton.click();
  check((await dialog.locator("ol > li").count()) === 0, "E2E_INSPECTOR_CLEAR_FAILED");

  if (textScale === 2) await noHorizontalOverflowElement(dialog, "E2E_INSPECTOR_200_HORIZONTAL_OVERFLOW");
  await page.keyboard.press("Escape");
  check(!(await dialog.isVisible()), "E2E_INSPECTOR_ESCAPE_CLOSE");
  check(await page.evaluate(() => document.activeElement?.getAttribute("aria-haspopup") === "dialog"), "E2E_INSPECTOR_FOCUS_RETURN");
}

async function testInspectorViewport(browser, spec, stressBuffer = false) {
  resetMockState(spec.locale);
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  await setAuthenticatedCookie(context);
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitForLearning(page);
    await setTextScale(page, spec.textScale);
    const shell = page.locator(".student-shell");
    check(await shell.getAttribute("lang") === spec.locale, "E2E_INSPECTOR_PAGE_LOCALE");
    check(await shell.getAttribute("dir") === (spec.locale === "ar" ? "rtl" : "ltr"), "E2E_INSPECTOR_PAGE_DIRECTION");

    const nav = page.locator(".student-nav button");
    await nav.nth(2).click();
    const start = page.locator(".practice-empty .primary-button");
    await start.click();
    await page.locator(".question-card").waitFor({ state: "visible", timeout: 10000 });
    const answerSentinel = crypto.randomUUID();
    await page.locator(".text-answer").fill(answerSentinel);
    await verifyInspector(page, mockState.runtimeQuestionSentinel, answerSentinel, spec.locale, spec.textScale, stressBuffer);
  } finally {
    await context.close();
  }
}

async function runInspectorMatrix(browser) {
  const specs = [
    { name: "inspector-desktop-en", width: 1440, height: 1000, locale: "en", textScale: 1 },
    { name: "inspector-mobile-fr-360-200", width: 360, height: 800, locale: "fr", textScale: 2 },
    { name: "inspector-mobile-ar-320-200", width: 320, height: 720, locale: "ar", textScale: 2 },
  ];
  for (const [index, spec] of specs.entries()) {
    await runCase(`runtime-inspector:${spec.name}`, { surface: "runtime-inspector", ...spec }, () => testInspectorViewport(browser, spec, index === 0));
  }
}

async function testInspectorProductionOff(browser) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    resetMockState("en");
    mockState.sessionMode = "unauthenticated";
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitForLogin(page);
    check((await page.locator('[data-runtime-inspector="enabled"]').count()) === 0, "E2E_INSPECTOR_PRODUCTION_VISIBLE");
    check((await page.locator('button[aria-haspopup="dialog"]').count()) === 0, "E2E_INSPECTOR_PRODUCTION_LAUNCHER_VISIBLE");
  } finally {
    await context.close();
  }
}

async function runCore(browser, inspectorExpected = false) {
  for (const spec of viewportCases) {
    await runCase(`auth:${spec.name}`, { surface: "auth", ...spec }, () => testLoginViewport(browser, spec));
    await runCase(`learning:${spec.name}`, { surface: "learning", ...spec }, () => testLearningViewport(browser, spec, inspectorExpected));
  }
  await runCase("states:auth-loading-error", { surface: "auth", width: 320, height: 720, locale: "ar", textScale: 2 }, () => testAuthRuntimeStates(browser));
  await runCase("states:learning-academic-account", { surface: "auth-learning", width: 320, height: 720, locale: "fr", textScale: 2 }, () => testLoadingErrorAccountStates(browser));
}

async function main() {
  assert.ok(["core", "session-security", "inspector", "composite"].includes(profile), "profile must be supported");
  assert.ok(fs.existsSync(path.join(appDir, "package.json")), "target web package must exist");
  fs.mkdirSync(evidenceDir, { recursive: true });

  const mockServer = http.createServer((req, res) => {
    handleMockRequest(req, res).catch(() => {
      if (!res.headersSent) json(res, 500, problem(500, "E2E_MOCK_FAILURE", "Synthetic mock failure."), "application/problem+json");
      else res.end();
    });
  });
  await listen(mockServer, mockPort);

  let next = null;
  let browser = null;
  try {
    const inspectorEnabled = profile === "inspector" || profile === "composite";
    const inspectorEnv = inspectorEnabled
      ? { MODRIK_RUNTIME_INSPECTOR_ENABLED: "true", MODRIK_RUNTIME_ENVIRONMENT: "pilot", MODRIK_BUILD_VERSION: "e2e", MODRIK_GIT_SHA: process.env.MODRIK_E2E_OBSERVED_SHA || "unknown" }
      : {};
    next = startNext(inspectorEnv);
    await waitForHttp(baseURL);
    browser = await chromium.launch({ headless: true });

    if (profile === "core" || profile === "composite") {
      await runCore(browser, profile === "composite");
    }

    if (profile === "session-security" || profile === "inspector" || profile === "composite") {
      await runCase("session-security:learning-401", { surface: "learning-bff", width: 390, height: 844, locale: "en", textScale: 1 }, () => testSessionSecurity(browser));
    }

    if (profile === "inspector" || profile === "composite") {
      await runInspectorMatrix(browser);
    }

    if (profile === "inspector" || profile === "composite") {
      await browser.close();
      browser = null;
      await stopChild(next);
      next = startNext({
        MODRIK_RUNTIME_INSPECTOR_ENABLED: "true",
        MODRIK_RUNTIME_ENVIRONMENT: "production",
        MODRIK_BUILD_VERSION: "e2e",
        MODRIK_GIT_SHA: process.env.MODRIK_E2E_OBSERVED_SHA || "unknown",
      });
      await waitForHttp(baseURL);
      browser = await chromium.launch({ headless: true });
      await runCase("runtime-inspector:production-default-off", { surface: "runtime-inspector", width: 390, height: 844, locale: "en", textScale: 1 }, () => testInspectorProductionOff(browser));
    }
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(next);
    await new Promise((resolve) => mockServer.close(resolve));
  }

  const evidencePath = path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`);
  fs.writeFileSync(evidencePath, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });

  const passCount = evidence.cases.filter((entry) => entry.status === "PASS").length;
  const failCount = evidence.cases.length - passCount;
  console.log(`Browser runtime acceptance: ${passCount} PASS, ${failCount} FAIL (${candidate}, ${profile})`);
  if (failures.length > 0) {
    console.error(`Browser acceptance failures: ${failures.join(", ")}`);
    process.exitCode = 1;
  }
}

main().catch(() => {
  console.error("Browser runtime acceptance aborted: E2E_HARNESS_FAILURE");
  process.exitCode = 1;
});
