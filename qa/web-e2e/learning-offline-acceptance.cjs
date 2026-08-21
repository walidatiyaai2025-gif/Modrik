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
const candidate = process.env.MODRIK_E2E_CANDIDATE || "current-tree-learning-offline";
const observedSha = process.env.MODRIK_E2E_OBSERVED_SHA || null;
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3280);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4280);
const baseURL = `http://127.0.0.1:${appPort}`;

const ids = {
  user: "01J00000000000000000000001",
  context: "01J00000000000000000000002",
  lesson: "01J00000000000000000000003",
  quiz: "01J00000000000000000000004",
  node: "01J00000000000000000000005",
  track: "01J000000000000000000000A1",
};

const evidence = {
  schema_version: "modrik.web.learning-offline-browser-evidence.v1",
  candidate,
  observed_sha: observedSha,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  viewport: { width: 360, height: 800 },
  locale: "fr",
  direction: "ltr",
  text_scale: 2,
  text_scale_method: "cssom-root-font-size-200-percent",
  security: {
    traces_recorded: false,
    screenshots_recorded: false,
    videos_recorded: false,
    dom_dumps_recorded: false,
    request_response_bodies_recorded: false,
    credentials_recorded: false,
  },
  status: null,
  failure_code: null,
};

function fail(code) {
  throw new Error(code);
}

function check(value, code) {
  if (!value) fail(code);
}

function envelope(data) {
  return { data, meta: { request_id: "offline-browser-e2e" } };
}

function problem(status, code, detail) {
  return {
    type: `https://modrik.org/problems/${code.toLowerCase()}`,
    title: status === 401 ? "Authentication required" : "Request rejected",
    status,
    code,
    detail,
    request_id: "offline-browser-e2e",
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

function hasBearer(req) {
  const value = req.headers.authorization;
  return typeof value === "string" && value.startsWith("Bearer ") && value.length > 7;
}

async function handleMock(req, res) {
  const pathname = new URL(req.url, `http://127.0.0.1:${mockPort}`).pathname;

  if (pathname === "/v1/session") {
    if (!hasBearer(req)) {
      return sendJson(
        res,
        401,
        problem(401, "AUTHENTICATION_REQUIRED", "Authentication required."),
        "application/problem+json",
      );
    }
    return sendJson(res, 200, envelope({ user_id: ids.user, locale: "fr", roles: ["student"] }));
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

  if (pathname === "/v1/academic-tracks") {
    return sendJson(res, 200, envelope({ tracks: [{
      id: ids.track,
      labels: {
        en: "Synthetic academic track for offline browser evidence",
        ar: "مسار أكاديمي تجريبي لاختبار وضع عدم الاتصال في المتصفح",
        fr: "Parcours académique synthétique avec un libellé long pour la preuve hors ligne du navigateur",
      },
    }] }));
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
    return sendJson(res, 200, envelope([{ 
      academic_context_id: ids.context,
      curriculum_node_id: ids.node,
      mastery: 0.72,
      source_version: 1,
      calculated_at: "2026-08-21T00:00:00Z",
    }]));
  }

  return sendJson(
    res,
    404,
    problem(404, "RESOURCE_NOT_FOUND", "Synthetic offline browser route not found."),
    "application/problem+json",
  );
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
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
      // Retry only inside the bounded startup interval.
    }
    await sleep(200);
  }
  fail("E2E_LEARNING_OFFLINE_APP_START_TIMEOUT");
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
      MODRIK_BUILD_VERSION: "offline-e2e",
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

async function setTextScale(page) {
  const fontSize = await page.evaluate(() => {
    const rule = "html { font-size: 200% !important; }";
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
    if (!inserted) document.documentElement.style.fontSize = "200%";
    return Number.parseFloat(getComputedStyle(document.documentElement).fontSize);
  });
  check(fontSize >= 30, "E2E_LEARNING_OFFLINE_TEXT_SCALE_NOT_APPLIED");
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

async function main() {
  check(fs.existsSync(path.join(appDir, "package.json")), "E2E_LEARNING_OFFLINE_TARGET_MISSING");
  fs.mkdirSync(evidenceDir, { recursive: true });

  const mock = http.createServer((req, res) => {
    handleMock(req, res).catch(() => {
      if (!res.headersSent) sendJson(res, 500, { code: "E2E_LEARNING_OFFLINE_MOCK_FAILURE" });
      else res.end();
    });
  });
  await listen(mock, mockPort);

  let app = null;
  let browser = null;
  let context = null;
  try {
    app = startNext();
    await waitForHttp(baseURL);

    browser = await chromium.launch({ headless: true });
    context = await browser.newContext({ viewport: { width: 360, height: 800 } });
    await context.addCookies([{
      name: "modrik_web_session",
      value: crypto.randomBytes(32).toString("base64url"),
      url: baseURL,
      httpOnly: true,
      sameSite: "Lax",
    }]);
    const page = await context.newPage();

    await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await page.locator(".student-shell").waitFor({ state: "visible", timeout: 15000 });
    await page.locator(".dashboard-stack").waitFor({ state: "visible", timeout: 15000 });
    await page.locator(".student-shell").evaluate((element) => {
      const localeButton = Array.from(element.querySelectorAll(".locale-switcher button"))
        .find((button) => button.textContent?.trim() === "FR");
      if (localeButton instanceof HTMLElement) localeButton.click();
    });
    await setTextScale(page);

    check(await page.locator(".student-shell").getAttribute("lang") === "fr", "E2E_LEARNING_OFFLINE_LOCALE");
    check(await page.locator(".student-shell").getAttribute("dir") === "ltr", "E2E_LEARNING_OFFLINE_DIRECTION");

    await context.setOffline(true);
    const banner = page.locator(".offline-banner");
    await banner.waitFor({ state: "visible", timeout: 5000 });
    await noHorizontalOverflow(page, "E2E_LEARNING_OFFLINE_HORIZONTAL_OVERFLOW");
    const retry = banner.locator("button");
    await reachable(retry, page, "E2E_LEARNING_OFFLINE_RETRY");

    const study = page.locator(".student-nav button").nth(1);
    await reachable(study, page, "E2E_LEARNING_OFFLINE_STUDY_NAV");
    await study.click();
    await page.locator(".lesson-reader").waitFor({ state: "visible", timeout: 5000 });
    await noHorizontalOverflow(page, "E2E_LEARNING_OFFLINE_STUDY_HORIZONTAL_OVERFLOW");

    await context.setOffline(false);
    await banner.waitFor({ state: "hidden", timeout: 15000 });
    await page.locator(".student-shell").waitFor({ state: "visible", timeout: 15000 });
    await noHorizontalOverflow(page, "E2E_LEARNING_OFFLINE_RECOVERY_HORIZONTAL_OVERFLOW");

    evidence.status = "PASS";
  } catch (error) {
    const message = error instanceof Error ? error.message : "";
    evidence.status = "FAIL";
    evidence.failure_code = /^E2E_[A-Z0-9_:-]+$/.test(message)
      ? message
      : "E2E_LEARNING_OFFLINE_ASSERTION_FAILED";
  } finally {
    if (context) await context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await shutdownServer(mock);
  }

  const output = path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`);
  fs.writeFileSync(output, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });

  if (evidence.status === "PASS") {
    console.log("Learning offline browser acceptance: PASS");
  } else {
    console.error(`Learning offline browser acceptance: FAIL (${evidence.failure_code})`);
    process.exitCode = 1;
  }
}

main().catch(() => {
  evidence.status = "FAIL";
  evidence.failure_code = "E2E_LEARNING_OFFLINE_HARNESS_FAILURE";
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.writeFileSync(
    path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`),
    `${JSON.stringify(evidence, null, 2)}\n`,
    { mode: 0o600 },
  );
  console.error("Learning offline browser acceptance aborted: E2E_LEARNING_OFFLINE_HARNESS_FAILURE");
  process.exitCode = 1;
});
