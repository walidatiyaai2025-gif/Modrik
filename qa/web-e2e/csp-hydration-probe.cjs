"use strict";

const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const appDir = path.join(targetDir, "apps", "web");
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3250);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4250);
const baseURL = `http://127.0.0.1:${appPort}`;
const evidenceDir = path.resolve(process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".e2e-evidence"));
const candidate = process.env.MODRIK_E2E_CANDIDATE || "csp-hydration-probe";
const observedSha = process.env.MODRIK_E2E_OBSERVED_SHA || null;

let sessionRequests = 0;
const signals = {
  console_csp_script_block: false,
  console_csp_style_block: false,
  page_error: false,
};

const evidence = {
  schema_version: "modrik.web.csp-hydration-evidence.v1",
  candidate,
  observed_sha: observedSha,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  security: {
    traces_recorded: false,
    screenshots_recorded: false,
    videos_recorded: false,
    dom_dumps_recorded: false,
    console_messages_recorded: false,
    request_response_bodies_recorded: false,
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
    request_id: "e2e-hydration-probe",
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
  return sendJson(res, 404, {
    type: "https://modrik.org/problems/resource_not_found",
    title: "Request rejected",
    status: 404,
    code: "RESOURCE_NOT_FOUND",
    detail: "Synthetic hydration-probe route not found.",
    request_id: "e2e-hydration-probe",
    retryable: false,
  }, "application/problem+json");
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
      // Bounded startup retry.
    }
    await sleep(200);
  }
  throw new Error("E2E_HYDRATION_APP_START_TIMEOUT");
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
      MODRIK_BUILD_VERSION: "e2e-hydration",
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

function observeConsoleMessage(message) {
  const text = message.text().toLowerCase();
  const cspLike = text.includes("content security policy") || text.includes("content-security-policy") || text.includes("refused to");
  if (!cspLike) return;
  if (text.includes("script") || text.includes("execute")) signals.console_csp_script_block = true;
  if (text.includes("style")) signals.console_csp_style_block = true;
}

async function main() {
  if (!fs.existsSync(path.join(appDir, "package.json"))) throw new Error("E2E_HYDRATION_TARGET_MISSING");
  fs.mkdirSync(evidenceDir, { recursive: true });

  const mock = http.createServer((req, res) => {
    handleMock(req, res).catch(() => {
      if (!res.headersSent) sendJson(res, 500, { code: "E2E_HYDRATION_MOCK_FAILURE" });
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
    page.on("console", observeConsoleMessage);
    page.on("pageerror", () => {
      signals.page_error = true;
    });

    const response = await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    const csp = response?.headers()["content-security-policy"] || "";
    const initialLoadingVisible = await page.locator(".auth-loading").isVisible().catch(() => false);

    let hydrated = false;
    try {
      await page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 7000 });
      hydrated = true;
    } catch {
      hydrated = false;
    }

    const scriptMetrics = await page.evaluate(() => ({
      script_count: document.scripts.length,
      script_nonce_count: Array.from(document.scripts).filter((script) => Boolean(script.nonce || script.getAttribute("nonce"))).length,
      style_count: document.querySelectorAll("style").length,
      style_nonce_count: Array.from(document.querySelectorAll("style")).filter((style) => Boolean(style.nonce || style.getAttribute("nonce"))).length,
    }));

    let failureCode = null;
    if (!hydrated) {
      if (signals.console_csp_script_block) failureCode = "E2E_CSP_SCRIPT_BLOCKED_HYDRATION";
      else if (scriptMetrics.script_count > 0 && scriptMetrics.script_nonce_count === 0 && csp.includes("nonce-")) failureCode = "E2E_CSP_SCRIPT_NONCE_MISSING";
      else if (sessionRequests === 0) failureCode = "E2E_CLIENT_INITIALIZER_NOT_RUNNING";
      else failureCode = "E2E_AUTH_HYDRATION_STALLED";
    }

    evidence.result = {
      status: hydrated ? "PASS" : "FAIL",
      failure_code: failureCode,
      initial_loading_visible: initialLoadingVisible,
      login_visible: hydrated,
      session_request_count: sessionRequests,
      csp_present: Boolean(csp),
      csp_nonce_directive_present: csp.includes("nonce-"),
      csp_strict_dynamic_present: csp.includes("strict-dynamic"),
      ...scriptMetrics,
      ...signals,
    };

    await context.close();
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await new Promise((resolve) => mock.close(resolve));
  }

  const outputPath = path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`);
  fs.writeFileSync(outputPath, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });

  if (evidence.result?.status !== "PASS") {
    console.error(`CSP hydration probe: FAIL (${evidence.result?.failure_code || "E2E_HYDRATION_UNKNOWN"})`);
    process.exitCode = 1;
  } else {
    console.log("CSP hydration probe: PASS");
  }
}

main().catch((error) => {
  const code = error instanceof Error && /^E2E_[A-Z0-9_:-]+$/.test(error.message)
    ? error.message
    : "E2E_HYDRATION_PROBE_FAILURE";
  evidence.result = { status: "FAIL", failure_code: code };
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.writeFileSync(path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}.json`), `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });
  console.error(`CSP hydration probe aborted: ${code}`);
  process.exitCode = 1;
});
