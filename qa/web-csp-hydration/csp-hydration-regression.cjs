"use strict";

const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const appDir = path.join(targetDir, "apps", "web");
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3260);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4260);
const baseURL = `http://127.0.0.1:${appPort}`;
const evidenceDir = path.resolve(
  process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".csp-hydration-evidence"),
);
const observedSha = process.env.MODRIK_E2E_OBSERVED_SHA || null;

let sessionRequests = 0;
const signals = {
  csp_script_block: false,
  page_error: false,
};

const evidence = {
  schema_version: "modrik.web.csp-hydration-regression.v1",
  observed_sha: observedSha,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  capture_policy: {
    screenshots: false,
    traces: false,
    videos: false,
    dom_dumps: false,
    console_messages: false,
    request_response_bodies: false,
  },
  result: null,
};

function problem401() {
  return {
    type: "https://modrik.org/problems/authentication_required",
    title: "Authentication required",
    status: 401,
    code: "AUTHENTICATION_REQUIRED",
    detail: "Authentication required.",
    request_id: "csp-hydration-regression",
    retryable: false,
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

async function handleMock(req, res) {
  const pathname = new URL(req.url, `http://127.0.0.1:${mockPort}`).pathname;
  if (pathname === "/v1/session") {
    sessionRequests += 1;
    return sendJson(res, 401, problem401(), "application/problem+json");
  }

  return sendJson(
    res,
    404,
    {
      type: "https://modrik.org/problems/resource_not_found",
      title: "Request rejected",
      status: 404,
      code: "RESOURCE_NOT_FOUND",
      detail: "Synthetic CSP hydration route not found.",
      request_id: "csp-hydration-regression",
      retryable: false,
    },
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
      // Bounded startup retry only.
    }
    await sleep(200);
  }
  throw new Error("E2E_CSP_APP_START_TIMEOUT");
}

function startNext() {
  const nextBin = path.join(appDir, "node_modules", "next", "dist", "bin", "next");
  return spawn(process.execPath, [nextBin, "start", "-H", "127.0.0.1", "-p", String(appPort)], {
    cwd: appDir,
    env: {
      ...process.env,
      MODRIK_API_BASE_URL: `http://127.0.0.1:${mockPort}`,
      MODRIK_RUNTIME_INSPECTOR_ENABLED: "false",
      MODRIK_RUNTIME_ENVIRONMENT: "production",
      MODRIK_BUILD_VERSION: "csp-hydration-regression",
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

function observeConsole(message) {
  const text = message.text().toLowerCase();
  const cspLike =
    text.includes("content security policy") ||
    text.includes("content-security-policy") ||
    text.includes("refused to");
  if (!cspLike) return;
  if (text.includes("script") || text.includes("execute")) signals.csp_script_block = true;
}

function cspAssertions(csp) {
  return {
    csp_present: Boolean(csp),
    nonce_directive_present: /script-src[^;]*'nonce-[^']+'/.test(csp),
    strict_dynamic_present: /script-src[^;]*'strict-dynamic'/.test(csp),
    unsafe_inline_absent: !/script-src[^;]*'unsafe-inline'/.test(csp),
    unsafe_eval_absent: !/script-src[^;]*'unsafe-eval'/.test(csp),
  };
}

async function main() {
  if (!fs.existsSync(path.join(appDir, "package.json"))) {
    throw new Error("E2E_CSP_TARGET_MISSING");
  }
  fs.mkdirSync(evidenceDir, { recursive: true });

  const mock = http.createServer((req, res) => {
    handleMock(req, res).catch(() => {
      if (!res.headersSent) sendJson(res, 500, { code: "E2E_CSP_MOCK_FAILURE" });
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
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    page.on("console", observeConsole);
    page.on("pageerror", () => {
      signals.page_error = true;
    });

    const landingResponse = await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    const landingCsp = landingResponse?.headers()["content-security-policy"] || "";

    let landingHydrated = false;
    try {
      const arabicButton = page.locator(".landing-locale button").filter({ hasText: "AR" }).first();
      await arabicButton.waitFor({ state: "visible", timeout: 10000 });
      await arabicButton.click();
      await page.locator('.landing-shell[dir="rtl"]').waitFor({ state: "visible", timeout: 5000 });
      landingHydrated = (await arabicButton.getAttribute("aria-pressed")) === "true";
    } catch {
      landingHydrated = false;
    }

    const landingMetrics = await page.evaluate(() => ({
      script_count: document.scripts.length,
      script_nonce_count: Array.from(document.scripts).filter((script) =>
        Boolean(script.nonce || script.getAttribute("nonce")),
      ).length,
    }));

    const studentResponse = await page.goto(`${baseURL}/student`, { waitUntil: "domcontentloaded" });
    const studentCsp = studentResponse?.headers()["content-security-policy"] || "";
    const initialLoadingVisible = await page.locator(".auth-loading").isVisible().catch(() => false);

    let loginVisible = false;
    try {
      await page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 10000 });
      loginVisible = true;
    } catch {
      loginVisible = false;
    }

    const studentMetrics = await page.evaluate(() => ({
      script_count: document.scripts.length,
      script_nonce_count: Array.from(document.scripts).filter((script) =>
        Boolean(script.nonce || script.getAttribute("nonce")),
      ).length,
    }));

    const landingSecurity = cspAssertions(landingCsp);
    const studentSecurity = cspAssertions(studentCsp);
    const assertions = {
      csp_present: landingSecurity.csp_present && studentSecurity.csp_present,
      nonce_directive_present: landingSecurity.nonce_directive_present && studentSecurity.nonce_directive_present,
      strict_dynamic_present: landingSecurity.strict_dynamic_present && studentSecurity.strict_dynamic_present,
      unsafe_inline_absent: landingSecurity.unsafe_inline_absent && studentSecurity.unsafe_inline_absent,
      unsafe_eval_absent: landingSecurity.unsafe_eval_absent && studentSecurity.unsafe_eval_absent,
      hydration_completed: landingHydrated && loginVisible,
      landing_initializer_ran: landingHydrated,
      session_initializer_ran: sessionRequests > 0,
      scripts_received_nonce:
        landingMetrics.script_count > 0 &&
        landingMetrics.script_nonce_count > 0 &&
        studentMetrics.script_count > 0 &&
        studentMetrics.script_nonce_count > 0,
      no_csp_script_block: !signals.csp_script_block,
      no_page_error: !signals.page_error,
    };

    const failed = Object.entries(assertions)
      .filter(([, value]) => !value)
      .map(([key]) => key);

    evidence.result = {
      status: failed.length === 0 ? "PASS" : "FAIL",
      failure_code: failed.length === 0 ? null : "E2E_CSP_HYDRATION_ASSERTION_FAILED",
      failed_assertions: failed,
      landing_hydrated: landingHydrated,
      initial_loading_visible: initialLoadingVisible,
      session_request_count: sessionRequests,
      landing_script_count: landingMetrics.script_count,
      landing_script_nonce_count: landingMetrics.script_nonce_count,
      student_script_count: studentMetrics.script_count,
      student_script_nonce_count: studentMetrics.script_nonce_count,
      assertions,
    };

    await context.close();
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await new Promise((resolve) => mock.close(resolve));
  }

  const outputPath = path.join(evidenceDir, "csp-hydration-regression.json");
  fs.writeFileSync(outputPath, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });

  if (evidence.result?.status !== "PASS") {
    console.error(`CSP hydration regression: FAIL (${evidence.result?.failure_code || "E2E_CSP_UNKNOWN"})`);
    process.exitCode = 1;
  } else {
    console.log("CSP hydration regression: PASS");
  }
}

main().catch((error) => {
  const code =
    error instanceof Error && /^E2E_[A-Z0-9_:-]+$/.test(error.message)
      ? error.message
      : "E2E_CSP_HYDRATION_PROBE_FAILURE";
  evidence.result = { status: "FAIL", failure_code: code, failed_assertions: [] };
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.writeFileSync(
    path.join(evidenceDir, "csp-hydration-regression.json"),
    `${JSON.stringify(evidence, null, 2)}\n`,
    { mode: 0o600 },
  );
  console.error(`CSP hydration regression aborted: ${code}`);
  process.exitCode = 1;
});
