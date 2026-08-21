"use strict";

const crypto = require("node:crypto");
const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const appDir = path.join(targetDir, "apps", "web");
const evidenceDir = path.resolve(process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".e2e-evidence"));
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3310);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4310);
const baseURL = `http://127.0.0.1:${appPort}`;

const ids = {
  user: "01J00000000000000000000001",
  context: "01J00000000000000000000002",
  lesson: "01J00000000000000000000003",
  quiz: "01J00000000000000000000004",
  node: "01J00000000000000000000005",
  attempt: "01J00000000000000000000006",
  question: "01J00000000000000000000007",
  track: "01J000000000000000000000A1",
};

const state = {
  locale: "en",
  sessionMode: "unauthenticated",
  sessionDelayMs: 0,
};

const evidence = {
  schema_version: "modrik.web.browser-overflow-geometry.v2",
  expected_sha: process.env.MODRIK_E2E_EXPECTED_SHA || null,
  harness_sha: process.env.MODRIK_E2E_HARNESS_SHA || null,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  text_scale_method: "cssom-root-font-size-200-percent",
  privacy: {
    screenshots: false,
    traces: false,
    videos: false,
    dom_text: false,
    dom_html: false,
    ids_or_aria_labels: false,
    form_values: false,
    urls_or_bodies: false,
  },
  cases: [],
};

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
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

function envelope(data) {
  return { data, meta: { request_id: "overflow-e2e" } };
}

function problem(status, code) {
  return {
    type: `https://modrik.org/problems/${code.toLowerCase()}`,
    title: status === 401 ? "Authentication required" : "Synthetic failure",
    status,
    code,
    detail: status === 401 ? "Authentication required." : "Synthetic failure.",
    request_id: "overflow-e2e",
    retryable: false,
  };
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
      attempt_question_id: ids.question,
      position: 1,
      type: "short_text",
      prompt: {
        en: "Synthetic responsive acceptance prompt",
        ar: "سؤال تجريبي للتحقق من الاستجابة",
        fr: "Question synthétique pour la validation responsive",
      },
      response_contract: { kind: "short_text", max_length: 64 },
      current_answer: null,
    }],
  };
}

function hasBearer(req) {
  const value = req.headers.authorization;
  return typeof value === "string" && value.startsWith("Bearer ") && value.length > 7;
}

async function handleMock(req, res) {
  const pathname = new URL(req.url, `http://127.0.0.1:${mockPort}`).pathname;

  if (pathname === "/v1/session") {
    if (state.sessionDelayMs > 0) await sleep(state.sessionDelayMs);
    if (state.sessionMode !== "authenticated" || !hasBearer(req)) {
      return sendJson(res, 401, problem(401, "AUTHENTICATION_REQUIRED"), "application/problem+json");
    }
    return sendJson(res, 200, envelope({ user_id: ids.user, locale: state.locale, roles: ["student"] }));
  }

  if (pathname === "/v1/academic-tracks") {
    return sendJson(res, 200, envelope({ tracks: [{
      id: ids.track,
      labels: {
        en: "Synthetic academic track with an intentionally extended learner-facing label for responsive browser acceptance",
        ar: "مسار أكاديمي تجريبي ذو تسمية طويلة مقصودة للتحقق من الاستجابة وإمكانية الوصول في المتصفح",
        fr: "Parcours académique synthétique avec un libellé volontairement long pour la validation responsive du navigateur",
      },
    }] }));
  }

  if (pathname === "/v1/academic-context") {
    return sendJson(res, 200, envelope({
      state: "active",
      context_id: ids.context,
      academic_track_id: ids.track,
      year_level: "fixture-year",
      activated_at: "2026-08-21T00:00:00Z",
    }));
  }

  if (pathname === `/v1/lessons/${ids.lesson}`) {
    return sendJson(res, 200, envelope({
      id: ids.lesson,
      curriculum_node_id: ids.node,
      content_version: 1,
      title: { en: "Synthetic lesson", ar: "درس تجريبي", fr: "Leçon synthétique" },
      practice_quiz_id: ids.quiz,
      blocks: [],
    }));
  }

  if (pathname === "/v1/progress") {
    return sendJson(res, 200, envelope([{ academic_context_id: ids.context, curriculum_node_id: ids.node, mastery: 0.72, source_version: 1, calculated_at: "2026-08-21T00:00:00Z" }]));
  }

  if (pathname === "/v1/attempts" && req.method === "POST") {
    return sendJson(res, 200, envelope(attemptPayload()));
  }
  if (pathname === `/v1/attempts/${ids.attempt}` && req.method === "GET") {
    return sendJson(res, 200, envelope(attemptPayload()));
  }
  if (pathname.startsWith(`/v1/attempts/${ids.attempt}/answers/`) && req.method === "PUT") {
    return sendJson(res, 200, envelope({ revision: 1, value: "", answered_at: "2026-08-21T00:00:00Z" }));
  }
  if (pathname === `/v1/attempts/${ids.attempt}/submit` && req.method === "POST") {
    return sendJson(res, 200, envelope({
      attempt: { ...attemptPayload(), status: "graded", completed_at: "2026-08-21T00:10:00Z" },
      score: 1,
      max_score: 1,
    }));
  }

  return sendJson(res, 404, problem(404, "RESOURCE_NOT_FOUND"), "application/problem+json");
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
      // bounded retry
    }
    await sleep(200);
  }
  throw new Error("E2E_APP_START_TIMEOUT");
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
    sleep(2000).then(() => false),
  ]);
  if (!exited && child.exitCode === null) child.kill("SIGKILL");
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
        // continue to the next same-origin stylesheet
      }
    }
    if (!inserted) document.documentElement.style.fontSize = "200%";
  });
}

async function authenticate(context) {
  await context.addCookies([{
    name: "modrik_web_session",
    value: crypto.randomBytes(32).toString("base64url"),
    url: baseURL,
    httpOnly: true,
    sameSite: "Lax",
  }]);
}

async function collectGeometry(page, selectors) {
  return page.evaluate((selectorList) => {
    const viewportWidth = window.innerWidth;
    const rows = [];
    for (const selector of selectorList) {
      const elements = Array.from(document.querySelectorAll(selector)).slice(0, 12);
      elements.forEach((element, index) => {
        if (!(element instanceof HTMLElement)) return;
        const rect = element.getBoundingClientRect();
        const leftOverflow = Math.max(0, -rect.left);
        const rightOverflow = Math.max(0, rect.right - viewportWidth);
        const internalOverflow = Math.max(0, element.scrollWidth - element.clientWidth);
        const severity = Math.max(leftOverflow, rightOverflow, internalOverflow);
        if (severity <= 1) return;
        rows.push({
          selector,
          index,
          left: Math.round(rect.left * 10) / 10,
          right: Math.round(rect.right * 10) / 10,
          width: Math.round(rect.width * 10) / 10,
          client_width: element.clientWidth,
          scroll_width: element.scrollWidth,
          viewport_width: viewportWidth,
          left_overflow: Math.round(leftOverflow * 10) / 10,
          right_overflow: Math.round(rightOverflow * 10) / 10,
          internal_overflow: internalOverflow,
          severity: Math.round(severity * 10) / 10,
        });
      });
    }
    return rows.sort((a, b) => b.severity - a.severity).slice(0, 12);
  }, selectors);
}

const AUTH_SELECTORS = [
  "html", "body", ".auth-shell", ".auth-main", ".auth-card", ".auth-form",
  ".auth-form input", ".auth-primary", ".auth-locale", ".auth-locale button",
  ".auth-brand-lockup", ".auth-mark", ".auth-loading", ".auth-loading h1",
];

const LEARNING_SELECTORS = [
  "html", "body", ".student-shell", ".student-frame", ".student-sidebar", ".student-stage",
  ".student-topbar", ".locale-switcher", ".student-nav", ".nav-item", ".dashboard-stack",
  ".study-layout", ".workspace-rail", ".lesson-reader",
];

const PRACTICE_SELECTORS = [
  ...LEARNING_SELECTORS,
  ".practice-empty", ".practice-empty .primary-button", ".question-card", ".question-card form",
  ".text-answer", ".practice-submit-row", ".practice-submit-row button", ".primary-button",
];

async function recordAuth(browser, name, width, height, locale, loading = false) {
  state.locale = locale;
  state.sessionMode = "unauthenticated";
  state.sessionDelayMs = loading ? 1200 : 0;
  const context = await browser.newContext({ viewport: { width, height } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    if (loading) await page.locator(".auth-loading").waitFor({ state: "visible", timeout: 5000 });
    else await page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 15000 });
    if (!loading) await page.locator(".auth-locale button", { hasText: locale.toUpperCase() }).first().click();
    await setTextScale(page);
    evidence.cases.push({
      name,
      surface: loading ? "auth-loading" : "auth",
      width,
      height,
      locale,
      text_scale: 2,
      geometry: await collectGeometry(page, AUTH_SELECTORS),
    });
  } finally {
    state.sessionDelayMs = 0;
    await context.close();
  }
}

async function recordLearning(browser, name, width, height, locale, mode = "learning") {
  state.locale = locale;
  state.sessionMode = "authenticated";
  state.sessionDelayMs = 0;
  const context = await browser.newContext({ viewport: { width, height } });
  await authenticate(context);
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await page.locator(".student-shell").waitFor({ state: "visible", timeout: 15000 });
    await page.locator(".dashboard-stack").waitFor({ state: "visible", timeout: 15000 });
    await setTextScale(page);

    if (mode === "study") {
      await page.locator(".student-nav button").nth(1).click();
      await page.locator(".lesson-reader").waitFor({ state: "visible", timeout: 10000 });
    } else if (mode === "practice") {
      await page.locator(".student-nav button").nth(2).click();
      const start = page.locator(".practice-empty .primary-button");
      await start.waitFor({ state: "visible", timeout: 10000 });
      await start.click();
      await page.locator(".question-card").waitFor({ state: "visible", timeout: 10000 });
    }

    evidence.cases.push({
      name,
      surface: mode,
      width,
      height,
      locale,
      text_scale: 2,
      geometry: await collectGeometry(page, mode === "practice" ? PRACTICE_SELECTORS : LEARNING_SELECTORS),
    });
  } finally {
    await context.close();
  }
}

async function closeServer(server) {
  if (typeof server.closeAllConnections === "function") server.closeAllConnections();
  await Promise.race([new Promise((resolve) => server.close(resolve)), sleep(1200)]);
}

async function main() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const mock = http.createServer((req, res) => {
    handleMock(req, res).catch(() => {
      if (!res.headersSent) sendJson(res, 500, problem(500, "E2E_MOCK_FAILURE"), "application/problem+json");
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
    await recordAuth(browser, "fr-360-auth", 360, 800, "fr");
    await recordLearning(browser, "fr-360-learning", 360, 800, "fr");
    await recordLearning(browser, "fr-360-practice", 360, 800, "fr", "practice");
    await recordAuth(browser, "ar-320-auth", 320, 720, "ar");
    await recordLearning(browser, "ar-320-study", 320, 720, "ar", "study");
    await recordAuth(browser, "ar-320-loading", 320, 720, "ar", true);
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await closeServer(mock);
  }

  const output = path.join(evidenceDir, "overflow-geometry.json");
  fs.writeFileSync(output, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });
  console.log(`Sanitized overflow geometry: ${evidence.cases.length} cases recorded`);
}

main().catch(() => {
  console.error("Overflow geometry probe aborted: E2E_OVERFLOW_GEOMETRY_FAILURE");
  process.exitCode = 1;
});
