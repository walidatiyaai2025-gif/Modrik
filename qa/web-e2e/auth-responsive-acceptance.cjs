"use strict";

const crypto = require("node:crypto");
const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const appDir = path.join(targetDir, "apps", "web");
const evidenceDir = path.resolve(
  process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".runtime", "web-browser-evidence"),
);
const candidate = process.env.MODRIK_E2E_CANDIDATE || "auth-responsive-candidate";
const observedSha = process.env.MODRIK_E2E_OBSERVED_SHA || null;
const sourceShas = process.env.MODRIK_E2E_SOURCE_SHAS || null;
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3290);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4290);
const baseURL = `http://127.0.0.1:${appPort}`;

const specs = [
  { name: "desktop-en", width: 1440, height: 1000, locale: "en", textScale: 1 },
  { name: "mobile-en-390", width: 390, height: 844, locale: "en", textScale: 1 },
  { name: "mobile-fr-360-200", width: 360, height: 800, locale: "fr", textScale: 2 },
  { name: "mobile-ar-320-200", width: 320, height: 720, locale: "ar", textScale: 2 },
];

const state = {
  sessionMode: "unauthenticated",
  sessionDelayMs: 0,
};

const evidence = {
  schema_version: "modrik.web.auth-responsive-browser-evidence.v1",
  candidate,
  observed_sha: observedSha,
  source_shas: sourceShas,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  text_scale_method: "cssom-root-font-size-200-percent",
  security: {
    traces_recorded: false,
    screenshots_recorded: false,
    videos_recorded: false,
    dom_dumps_recorded: false,
    console_messages_recorded: false,
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
  return /^E2E_[A-Z0-9_:-]+$/.test(message) ? message : "E2E_AUTH_RESPONSIVE_ASSERTION_FAILED";
}

async function runCase(name, metadata, fn) {
  const started = Date.now();
  try {
    await fn();
    evidence.cases.push({ id: ++caseId, name, status: "PASS", duration_ms: Date.now() - started, ...metadata });
  } catch (error) {
    const failureCode = safeFailureCode(error);
    failures.push(`${name}:${failureCode}`);
    evidence.cases.push({
      id: ++caseId,
      name,
      status: "FAIL",
      failure_code: failureCode,
      duration_ms: Date.now() - started,
      ...metadata,
    });
  }
}

function problem(status, code, detail) {
  return {
    type: `https://modrik.org/problems/${code.toLowerCase()}`,
    title: status === 401 ? "Authentication required" : "Request rejected",
    status,
    code,
    detail,
    request_id: "auth-responsive-e2e",
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

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function handleMock(req, res) {
  const pathname = new URL(req.url, `http://127.0.0.1:${mockPort}`).pathname;
  if (pathname === "/v1/session") {
    if (state.sessionDelayMs) await sleep(state.sessionDelayMs);
    if (state.sessionMode === "error") {
      return sendJson(
        res,
        503,
        problem(503, "AUTH_SERVICE_UNAVAILABLE", "Synthetic Auth service unavailable."),
        "application/problem+json",
      );
    }
    return sendJson(
      res,
      401,
      problem(401, "AUTHENTICATION_REQUIRED", "Authentication required."),
      "application/problem+json",
    );
  }

  return sendJson(
    res,
    404,
    problem(404, "RESOURCE_NOT_FOUND", "Synthetic Auth browser route not found."),
    "application/problem+json",
  );
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
  fail("E2E_AUTH_RESPONSIVE_APP_START_TIMEOUT");
}

function startNext() {
  const nextBin = path.join(appDir, "node_modules", "next", "dist", "bin", "next");
  return spawn(process.execPath, [nextBin, "start", "-H", "127.0.0.1", "-p", String(appPort)], {
    cwd: appDir,
    env: {
      ...process.env,
      MODRIK_API_BASE_URL: `http://127.0.0.1:${mockPort}`,
      MODRIK_RUNTIME_INSPECTOR_ENABLED: "true",
      MODRIK_RUNTIME_ENVIRONMENT: "pilot",
      MODRIK_BUILD_VERSION: "auth-responsive-e2e",
      MODRIK_GIT_SHA: observedSha || "unknown",
    },
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
  if (!exited && child.exitCode === null) child.kill("SIGKILL");
}

async function shutdownServer(server) {
  if (typeof server.closeAllConnections === "function") server.closeAllConnections();
  await Promise.race([new Promise((resolve) => server.close(resolve)), sleep(1500)]);
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
  check(scale === 2 ? fontSize >= 30 : fontSize >= 15, "E2E_AUTH_TEXT_SCALE_NOT_APPLIED");
}

async function noHorizontalOverflow(page, code) {
  const okay = await page.evaluate(
    () => document.documentElement.scrollWidth <= window.innerWidth + 1 && document.body.scrollWidth <= window.innerWidth + 1,
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
  try {
    await locator.click({ trial: true });
  } catch {
    fail(`${code}_NOT_ACTIONABLE`);
  }
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

async function noKeyboardTrap(page) {
  const seen = new Set();
  for (let index = 0; index < 28; index += 1) {
    await page.keyboard.press("Tab");
    seen.add(await page.evaluate(() => {
      const active = document.activeElement;
      if (!(active instanceof HTMLElement)) return "none";
      return `${active.tagName}|${active.getAttribute("name") || ""}|${active.getAttribute("aria-label") || ""}`;
    }));
  }
  check(seen.size >= 5, "E2E_AUTH_KEYBOARD_TRAP");
}

async function waitLogin(page) {
  await page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 15000 });
}

async function authViewport(browser, spec) {
  state.sessionMode = "unauthenticated";
  state.sessionDelayMs = 0;
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

    const submit = page.locator("button[type=submit]").first();
    await reachable(submit, page, "E2E_AUTH_SUBMIT");
    await keyboardLoginOrder(page);
    await noKeyboardTrap(page);

    await context.setOffline(true);
    await page.locator(".auth-notice-offline").waitFor({ state: "visible", timeout: 5000 });
    check(await submit.isDisabled(), "E2E_AUTH_OFFLINE_DISABLED");
    await noHorizontalOverflow(page, "E2E_AUTH_OFFLINE_HORIZONTAL_OVERFLOW");
    await context.setOffline(false);
  } finally {
    await context.close();
  }
}

async function loadingAcceptance(browser) {
  state.sessionMode = "unauthenticated";
  state.sessionDelayMs = 700;
  const context = await browser.newContext({ viewport: { width: 320, height: 720 } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await page.locator(".auth-loading").waitFor({ state: "visible", timeout: 5000 });
    await setTextScale(page, 2);
    await noHorizontalOverflow(page, "E2E_AUTH_LOADING_HORIZONTAL_OVERFLOW");
  } finally {
    state.sessionDelayMs = 0;
    await context.close();
  }
}

async function errorAcceptance(browser) {
  state.sessionMode = "error";
  state.sessionDelayMs = 0;
  const context = await browser.newContext({ viewport: { width: 360, height: 800 } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitLogin(page);
    await page.locator(".auth-locale button", { hasText: "FR" }).first().click();
    await setTextScale(page, 2);
    check(await page.locator(".auth-notice-error").isVisible(), "E2E_AUTH_ERROR_STATE");
    await noHorizontalOverflow(page, "E2E_AUTH_ERROR_HORIZONTAL_OVERFLOW");
  } finally {
    state.sessionMode = "unauthenticated";
    await context.close();
  }
}

async function main() {
  check(fs.existsSync(path.join(appDir, "package.json")), "E2E_AUTH_RESPONSIVE_TARGET_MISSING");
  fs.mkdirSync(evidenceDir, { recursive: true });

  const mock = http.createServer((req, res) => {
    handleMock(req, res).catch(() => {
      if (!res.headersSent) sendJson(res, 500, { code: "E2E_AUTH_RESPONSIVE_MOCK_FAILURE" });
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

    for (const spec of specs) {
      await runCase(`auth:${spec.name}`, { surface: "auth", ...spec }, () => authViewport(browser, spec));
    }
    await runCase(
      "auth:loading-320-200",
      { surface: "auth-loading", width: 320, height: 720, locale: "control", textScale: 2 },
      () => loadingAcceptance(browser),
    );
    await runCase(
      "auth:error-fr-360-200",
      { surface: "auth-error", width: 360, height: 800, locale: "fr", textScale: 2 },
      () => errorAcceptance(browser),
    );
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await shutdownServer(mock);
  }

  const output = path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`);
  fs.writeFileSync(output, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });

  const passed = evidence.cases.filter((item) => item.status === "PASS").length;
  const failed = evidence.cases.length - passed;
  console.log(`Auth responsive browser acceptance: ${passed} PASS, ${failed} FAIL (${candidate})`);
  if (failures.length) {
    console.error(`Auth responsive failures: ${failures.join(", ")}`);
    process.exitCode = 1;
  }
}

main().catch((error) => {
  const code = safeFailureCode(error);
  fs.mkdirSync(evidenceDir, { recursive: true });
  evidence.cases.push({
    id: ++caseId,
    name: "auth-responsive:harness",
    status: "FAIL",
    failure_code: code === "E2E_AUTH_RESPONSIVE_ASSERTION_FAILED" ? "E2E_AUTH_RESPONSIVE_HARNESS_FAILURE" : code,
  });
  fs.writeFileSync(
    path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`),
    `${JSON.stringify(evidence, null, 2)}\n`,
    { mode: 0o600 },
  );
  console.error("Auth responsive browser acceptance aborted");
  process.exitCode = 1;
});
