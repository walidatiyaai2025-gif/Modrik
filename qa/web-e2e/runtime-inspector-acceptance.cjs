"use strict";

const crypto = require("node:crypto");
const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const appDir = path.join(targetDir, "apps", "web");
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3300);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4300);
const baseURL = `http://127.0.0.1:${appPort}`;
const mode = process.env.MODRIK_E2E_INSPECTOR_MODE || "pilot";
const candidate = process.env.MODRIK_E2E_CANDIDATE || `runtime-inspector-${mode}`;
const observedSha = process.env.MODRIK_E2E_OBSERVED_SHA || null;
const sourceShas = process.env.MODRIK_E2E_SOURCE_SHAS || "";
const evidenceDir = path.resolve(process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".runtime", "web-browser-evidence"));
const diagnosticStorageKey = "modrik_runtime_diagnostics_v1";
const correlationId = "6e992d02-2e90-4bb8-aebc-5a4239228a19";
const privacySentinel = `sensitive-${crypto.randomUUID()}`;

const evidence = {
  schema_version: "modrik.web.runtime-inspector-browser-evidence.v1",
  candidate,
  mode,
  observed_sha: observedSha,
  source_shas: sourceShas || null,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  keyboard_open_method: "Tab-to-launcher-then-Enter",
  security: {
    traces_recorded: false,
    screenshots_recorded: false,
    videos_recorded: false,
    request_response_bodies_recorded: false,
    sensitive_values_recorded: false,
  },
  cases: [],
};

const failures = [];

function check(condition, code) {
  if (!condition) throw new Error(code);
}

function safeFailureCode(error) {
  const message = error instanceof Error ? error.message : "";
  return /^E2E_[A-Z0-9_:-]+$/.test(message) ? message : "E2E_INSPECTOR_ASSERTION_FAILED";
}

async function runCase(name, metadata, fn) {
  const started = Date.now();
  try {
    await fn();
    evidence.cases.push({ name, status: "PASS", duration_ms: Date.now() - started, ...metadata });
  } catch (error) {
    const failureCode = safeFailureCode(error);
    failures.push(`${name}:${failureCode}`);
    evidence.cases.push({ name, status: "FAIL", failure_code: failureCode, duration_ms: Date.now() - started, ...metadata });
  }
}

function json(res, status, payload, headers = {}) {
  const body = JSON.stringify(payload);
  res.writeHead(status, {
    "Content-Type": status >= 400 ? "application/problem+json" : "application/json",
    "Cache-Control": "no-store",
    "Content-Length": Buffer.byteLength(body),
    ...headers,
  });
  res.end(body);
}

function authenticationProblem(requestId) {
  return {
    type: "https://modrik.org/problems/authentication_required",
    title: "Authentication required",
    status: 401,
    code: "AUTHENTICATION_REQUIRED",
    detail: "Authentication required.",
    request_id: requestId,
    retryable: false,
  };
}

async function handleMockRequest(req, res) {
  const url = new URL(req.url, `http://127.0.0.1:${mockPort}`);
  if (url.pathname === "/v1/session") {
    const requested = req.headers["x-correlation-id"];
    const safeCorrelation = typeof requested === "string" && /^[0-9a-f-]{36}$/i.test(requested)
      ? requested
      : correlationId;
    return json(res, 401, authenticationProblem(safeCorrelation), { "X-Correlation-ID": safeCorrelation });
  }
  return json(res, 404, {
    type: "https://modrik.org/problems/resource_not_found",
    title: "Request rejected",
    status: 404,
    code: "RESOURCE_NOT_FOUND",
    detail: "Synthetic browser-evidence route not found.",
    request_id: correlationId,
    retryable: false,
  }, { "X-Correlation-ID": correlationId });
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

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForHttp(url, timeoutMs = 60000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url, { redirect: "manual" });
      if (response.status > 0) return;
    } catch {
      // Retry only within the bounded deadline.
    }
    await delay(250);
  }
  throw new Error("E2E_INSPECTOR_APP_START_TIMEOUT");
}

function startNext() {
  const nextBinary = path.join(appDir, "node_modules", "next", "dist", "bin", "next");
  const child = spawn(process.execPath, [nextBinary, "start", "-H", "127.0.0.1", "-p", String(appPort)], {
    cwd: appDir,
    env: {
      ...process.env,
      MODRIK_API_BASE_URL: `http://127.0.0.1:${mockPort}`,
      MODRIK_RUNTIME_INSPECTOR_ENABLED: "true",
      MODRIK_RUNTIME_ENVIRONMENT: mode === "pilot" ? "pilot" : "production",
      MODRIK_BUILD_VERSION: "e2e",
      MODRIK_GIT_SHA: observedSha || "unknown",
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
  await Promise.race([new Promise((resolve) => child.once("exit", resolve)), delay(4000)]);
  if (child.exitCode === null) child.kill("SIGKILL");
}

async function waitForLogin(page) {
  try {
    await page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 15000 });
  } catch {
    throw new Error("E2E_INSPECTOR_HYDRATION_PRECONDITION");
  }
}

async function setLocale(page, locale) {
  const button = page.locator(".auth-locale button").filter({ hasText: locale.toUpperCase() }).first();
  await button.click();
  const shell = page.locator(".auth-shell");
  check(await shell.getAttribute("lang") === locale, "E2E_INSPECTOR_AUTH_LOCALE");
  check(await shell.getAttribute("dir") === (locale === "ar" ? "rtl" : "ltr"), "E2E_INSPECTOR_AUTH_DIRECTION");
}

async function setTextScale(page, scale) {
  const computed = await page.evaluate((value) => {
    document.documentElement.style.fontSize = value === 2 ? "200%" : "100%";
    return Number.parseFloat(getComputedStyle(document.documentElement).fontSize);
  }, scale);
  check(scale === 2 ? computed >= 30 : computed >= 15, "E2E_INSPECTOR_TEXT_SCALE_NOT_APPLIED");
}

function diagnosticSeed(locale) {
  const direction = locale === "ar" ? "rtl" : "ltr";
  return Array.from({ length: 70 }, (_, index) => ({
    timestamp: new Date(Date.UTC(2026, 7, 21, 0, 0, index % 60)).toISOString(),
    severity: index % 7 === 0 ? "warn" : "info",
    surface: "web",
    category: "request",
    operation: index === 69 ? "e2e:correlation" : "e2e:bounded",
    correlationId,
    supportReference: correlationId,
    resultClass: index % 7 === 0 ? "4xx" : "2xx",
    status: index % 7 === 0 ? 401 : 200,
    errorCode: index % 7 === 0 ? "AUTHENTICATION_REQUIRED" : null,
    durationMs: 12,
    route: "/",
    locale,
    direction,
    online: true,
    retryState: "none",
    authorization: privacySentinel,
    cookie: privacySentinel,
    password: privacySentinel,
    provider_secret: privacySentinel,
    learner_answer: privacySentinel,
    question_text: privacySentinel,
    email: privacySentinel,
    request_body: privacySentinel,
    response_body: privacySentinel,
  }));
}

async function seedDiagnostics(page, locale) {
  await page.evaluate(({ key, rows }) => window.sessionStorage.setItem(key, JSON.stringify(rows)), {
    key: diagnosticStorageKey,
    rows: diagnosticSeed(locale),
  });
  await page.reload({ waitUntil: "domcontentloaded" });
  await waitForLogin(page);
  await setLocale(page, locale);
}

async function reachable(locator, page, code) {
  check((await locator.count()) > 0, `${code}_MISSING`);
  await locator.scrollIntoViewIfNeeded();
  check(await locator.isVisible(), `${code}_NOT_VISIBLE`);
  const box = await locator.boundingBox();
  const viewport = page.viewportSize();
  check(Boolean(box && viewport), `${code}_NO_BOX`);
  check(box.x >= -1 && box.x + box.width <= viewport.width + 1, `${code}_HORIZONTAL_CLIP`);
}

async function noHorizontalOverflow(locator, code) {
  check(await locator.evaluate((element) => element.scrollWidth <= element.clientWidth + 1), code);
}

async function focusIsVisible(page, code) {
  const visible = await page.evaluate(() => {
    const element = document.activeElement;
    if (!(element instanceof HTMLElement) || element === document.body) return false;
    const style = getComputedStyle(element);
    return style.outlineStyle !== "none" && Number.parseFloat(style.outlineWidth || "0") >= 1;
  });
  check(visible, code);
}

async function tabToInspectorLauncher(page) {
  for (let index = 0; index < 32; index += 1) {
    await page.keyboard.press("Tab");
    const reached = await page.evaluate(() => document.activeElement?.getAttribute("aria-haspopup") === "dialog");
    if (reached) return;
  }
  throw new Error("E2E_INSPECTOR_KEYBOARD_LAUNCHER_UNREACHABLE");
}

const copyBundlePattern = /copy diagnostic json|نسخ json التشخيصي|copier le json de diagnostic/i;
const downloadPattern = /download diagnostic json|تنزيل json التشخيصي|télécharger le json de diagnostic/i;
const clearPattern = /clear diagnostics|مسح التشخيصات|effacer les diagnostics/i;
const correlationPattern = /copy correlation id|نسخ معرّف الارتباط|copier l.identifiant de corrélation/i;

async function testPilotCase(browser, spec) {
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  await context.grantPermissions(["clipboard-read", "clipboard-write"], { origin: baseURL });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitForLogin(page);
    await setLocale(page, spec.locale);
    await seedDiagnostics(page, spec.locale);
    await setTextScale(page, spec.textScale);

    const host = page.locator('[data-runtime-inspector="enabled"]');
    check((await host.count()) === 1, "E2E_INSPECTOR_GATED_HOST_MISSING");
    check(await host.getAttribute("lang") === spec.locale, "E2E_INSPECTOR_HOST_LOCALE");
    check(await host.getAttribute("dir") === (spec.locale === "ar" ? "rtl" : "ltr"), "E2E_INSPECTOR_HOST_DIRECTION");

    const launcher = host.locator('button[aria-haspopup="dialog"]');
    await reachable(launcher, page, "E2E_INSPECTOR_LAUNCHER");
    await tabToInspectorLauncher(page);
    await focusIsVisible(page, "E2E_INSPECTOR_LAUNCHER_FOCUS_NOT_VISIBLE");
    await page.keyboard.press("Enter");

    const dialog = page.locator('[role="dialog"][aria-modal="true"]');
    await dialog.waitFor({ state: "visible", timeout: 5000 });
    await noHorizontalOverflow(dialog, spec.textScale === 2 ? "E2E_INSPECTOR_200_HORIZONTAL_OVERFLOW" : "E2E_INSPECTOR_HORIZONTAL_OVERFLOW");
    check(await page.evaluate(() => document.activeElement?.closest('[role="dialog"]') !== null), "E2E_INSPECTOR_INITIAL_FOCUS");
    await focusIsVisible(page, "E2E_INSPECTOR_INITIAL_FOCUS_NOT_VISIBLE");

    const focusables = dialog.locator('button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])');
    const focusableCount = await focusables.count();
    check(focusableCount >= 4, "E2E_INSPECTOR_FOCUSABLE_COUNT");
    await focusables.nth(focusableCount - 1).focus();
    await page.keyboard.press("Tab");
    check(await page.evaluate(() => document.activeElement?.closest('[role="dialog"]') !== null), "E2E_INSPECTOR_TAB_TRAP");
    await focusables.nth(0).focus();
    await page.keyboard.press("Shift+Tab");
    check(await page.evaluate(() => document.activeElement?.closest('[role="dialog"]') !== null), "E2E_INSPECTOR_SHIFT_TAB_TRAP");

    const visibleText = (await dialog.innerText()) || "";
    check(visibleText.includes(correlationId), "E2E_INSPECTOR_CORRELATION_NOT_VISIBLE");
    check(!visibleText.includes(privacySentinel), "E2E_INSPECTOR_PRIVACY_DOM_LEAK");
    const timelineCount = await dialog.locator("ol > li").count();
    check(timelineCount > 0 && timelineCount <= 50, "E2E_INSPECTOR_TIMELINE_BOUND");

    const correlationCopy = dialog.locator("button").filter({ hasText: correlationPattern }).first();
    await reachable(correlationCopy, page, "E2E_INSPECTOR_CORRELATION_COPY");
    await correlationCopy.click();
    check(await page.evaluate(() => navigator.clipboard.readText()) === correlationId, "E2E_INSPECTOR_CORRELATION_COPY_VALUE");

    const copyBundle = dialog.locator("button").filter({ hasText: copyBundlePattern }).first();
    await reachable(copyBundle, page, "E2E_INSPECTOR_COPY_BUNDLE");
    await copyBundle.click();
    const copiedBundle = await page.evaluate(() => navigator.clipboard.readText());
    check(copiedBundle.includes('"schema_version": "modrik.web.runtime-diagnostics.v1"'), "E2E_INSPECTOR_COPY_SCHEMA");
    check(Buffer.byteLength(copiedBundle, "utf8") <= 32 * 1024, "E2E_INSPECTOR_EXPORT_BYTE_BOUND");
    check(!copiedBundle.includes(privacySentinel), "E2E_INSPECTOR_PRIVACY_EXPORT_LEAK");
    for (const forbidden of ["authorization", "cookie", "password", "provider_secret", "learner_answer", "question_text", "email", "request_body", "response_body"]) {
      check(!copiedBundle.toLowerCase().includes(forbidden), "E2E_INSPECTOR_FORBIDDEN_FIELD");
    }

    const downloadButton = dialog.locator("button").filter({ hasText: downloadPattern }).first();
    await reachable(downloadButton, page, "E2E_INSPECTOR_DOWNLOAD");
    const downloadPromise = page.waitForEvent("download", { timeout: 5000 });
    await downloadButton.click();
    const download = await downloadPromise;
    check(download.suggestedFilename() === "modrik-runtime-diagnostics.json", "E2E_INSPECTOR_DOWNLOAD_FILENAME");
    await download.cancel().catch(() => {});

    const clearButton = dialog.locator("button").filter({ hasText: clearPattern }).first();
    await reachable(clearButton, page, "E2E_INSPECTOR_CLEAR");
    await clearButton.click();
    check((await dialog.locator("ol > li").count()) === 0, "E2E_INSPECTOR_CLEAR_FAILED");

    await page.keyboard.press("Escape");
    check(!(await dialog.isVisible()), "E2E_INSPECTOR_ESCAPE_CLOSE");
    check(await page.evaluate(() => document.activeElement?.getAttribute("aria-haspopup") === "dialog"), "E2E_INSPECTOR_FOCUS_RETURN");
  } finally {
    await context.close();
  }
}

async function testProductionGateOff(browser) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    check((await page.locator('[data-runtime-inspector="enabled"]').count()) === 0, "E2E_INSPECTOR_PRODUCTION_HOST_VISIBLE");
    check((await page.locator('button[aria-haspopup="dialog"]').count()) === 0, "E2E_INSPECTOR_PRODUCTION_LAUNCHER_VISIBLE");
  } finally {
    await context.close();
  }
}

async function testProductionStorageFailClosed(browser) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await waitForLogin(page).catch(() => {
      throw new Error("E2E_INSPECTOR_PRODUCTION_HYDRATION_PRECONDITION");
    });
    await page.evaluate(({ key, sentinel }) => window.sessionStorage.setItem(key, sentinel), { key: diagnosticStorageKey, sentinel: privacySentinel });
    await page.reload({ waitUntil: "domcontentloaded" });
    await waitForLogin(page).catch(() => {
      throw new Error("E2E_INSPECTOR_PRODUCTION_HYDRATION_PRECONDITION");
    });
    check((await page.locator('[data-runtime-inspector="enabled"]').count()) === 0, "E2E_INSPECTOR_PRODUCTION_RELOAD_HOST_VISIBLE");
    check(await page.evaluate((key) => window.sessionStorage.getItem(key), diagnosticStorageKey) === null, "E2E_INSPECTOR_PRODUCTION_STORAGE_NOT_CLEARED");
  } finally {
    await context.close();
  }
}

async function main() {
  check(mode === "pilot" || mode === "production", "E2E_INSPECTOR_MODE_INVALID");
  check(fs.existsSync(path.join(appDir, "package.json")), "E2E_INSPECTOR_TARGET_MISSING");
  fs.mkdirSync(evidenceDir, { recursive: true });

  const mockServer = http.createServer((req, res) => {
    handleMockRequest(req, res).catch(() => {
      if (!res.headersSent) json(res, 500, { code: "E2E_MOCK_FAILURE" });
      else res.end();
    });
  });
  await listen(mockServer, mockPort);

  let app = null;
  let browser = null;
  try {
    app = startNext();
    await waitForHttp(baseURL);
    browser = await chromium.launch({ headless: true });
    if (mode === "production") {
      await runCase("runtime-inspector:production-default-off", { width: 390, height: 844, locale: "en", text_scale: 1, requires_hydration: false }, () => testProductionGateOff(browser));
      await runCase("runtime-inspector:production-storage-fail-closed", { width: 390, height: 844, locale: "en", text_scale: 1, requires_hydration: true }, () => testProductionStorageFailClosed(browser));
    } else {
      for (const spec of [
        { name: "desktop-en", width: 1440, height: 1000, locale: "en", textScale: 1 },
        { name: "mobile-fr-360-200", width: 360, height: 800, locale: "fr", textScale: 2 },
        { name: "mobile-ar-320-200", width: 320, height: 720, locale: "ar", textScale: 2 },
      ]) {
        await runCase(`runtime-inspector:${spec.name}`, {
          width: spec.width,
          height: spec.height,
          locale: spec.locale,
          direction: spec.locale === "ar" ? "rtl" : "ltr",
          text_scale: spec.textScale,
        }, () => testPilotCase(browser, spec));
      }
    }
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await new Promise((resolve) => mockServer.close(resolve));
  }

  const evidencePath = path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`);
  fs.writeFileSync(evidencePath, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });
  const passed = evidence.cases.filter((entry) => entry.status === "PASS").length;
  console.log(`Runtime Inspector browser acceptance: ${passed}/${evidence.cases.length} PASS (${candidate}, ${mode})`);
  if (failures.length > 0) {
    console.error(`Runtime Inspector browser failures: ${failures.join(", ")}`);
    process.exitCode = 1;
  }
}

main().catch(() => {
  console.error("Runtime Inspector browser acceptance aborted: E2E_INSPECTOR_HARNESS_FAILURE");
  process.exitCode = 1;
});