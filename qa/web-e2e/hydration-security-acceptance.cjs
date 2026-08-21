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
const candidate = process.env.MODRIK_E2E_CANDIDATE || "hydration-security";
const evidenceDir = path.resolve(process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".e2e-evidence"));

const evidence = {
  schema_version: "modrik.web.hydration-security-evidence.v1",
  candidate,
  observed_sha: process.env.MODRIK_E2E_OBSERVED_SHA || null,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  security: {
    console_text_recorded: false,
    page_error_text_recorded: false,
    request_response_bodies_recorded: false,
    dom_dump_recorded: false,
    screenshots_recorded: false,
    traces_recorded: false,
  },
  result: null,
};

function json(res, status, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(status, {
    "Content-Type": status >= 400 ? "application/problem+json" : "application/json",
    "Cache-Control": "no-store",
    "Content-Length": Buffer.byteLength(body),
  });
  res.end(body);
}

function listen(server, port) {
  return new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(port, "127.0.0.1", () => {
      server.removeAllListeners("error");
      resolve();
    });
  });
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
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
    await delay(200);
  }
  throw new Error("E2E_HYDRATION_APP_START_TIMEOUT");
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
  await Promise.race([new Promise((resolve) => child.once("exit", resolve)), delay(2500)]);
  if (child.exitCode === null) child.kill("SIGKILL");
}

async function main() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const mock = http.createServer((req, res) => {
    const pathname = new URL(req.url, `http://127.0.0.1:${mockPort}`).pathname;
    if (pathname === "/v1/session") {
      return json(res, 401, {
        type: "https://modrik.org/problems/authentication_required",
        title: "Authentication required",
        status: 401,
        code: "AUTHENTICATION_REQUIRED",
        detail: "Authentication required.",
        request_id: "e2e-hydration-request",
        retryable: false,
      });
    }
    return json(res, 404, {
      type: "https://modrik.org/problems/resource_not_found",
      title: "Request rejected",
      status: 404,
      code: "RESOURCE_NOT_FOUND",
      detail: "Synthetic hydration route not found.",
      request_id: "e2e-hydration-request",
      retryable: false,
    });
  });

  await listen(mock, mockPort);
  let app = null;
  let browser = null;
  let exitCode = 0;
  try {
    app = startNext();
    await waitForHttp(baseURL);
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });

    let cspViolationObserved = false;
    let pageErrorObserved = false;
    let scriptRequestFailureObserved = false;
    page.on("console", (message) => {
      if (message.type() !== "error") return;
      const text = message.text().toLowerCase();
      if (text.includes("content security policy") || text.includes("refused to execute") || text.includes("refused to load")) {
        cspViolationObserved = true;
      }
    });
    page.on("pageerror", () => {
      pageErrorObserved = true;
    });
    page.on("requestfailed", (request) => {
      if (request.resourceType() === "script") scriptRequestFailureObserved = true;
    });

    const navigation = await page.goto(baseURL, { waitUntil: "domcontentloaded" });
    await delay(2000);

    const state = await page.evaluate(() => {
      const frameworkScripts = Array.from(document.scripts).filter((script) => script.src.includes("/_next/"));
      return {
        loading_visible: Boolean(document.querySelector(".auth-loading")),
        auth_form_visible: Boolean(document.querySelector(".auth-card form")),
        framework_script_count: frameworkScripts.length,
        framework_script_nonce_count: frameworkScripts.filter((script) => Boolean(script.nonce)).length,
      };
    });

    const cspHeaderPresent = Boolean(navigation?.headers()["content-security-policy"]);
    let status = "PASS";
    let failureCode = null;
    if (!state.auth_form_visible) {
      status = "FAIL";
      if (state.loading_visible && cspViolationObserved) failureCode = "E2E_HYDRATION_CSP_BLOCKED";
      else if (state.loading_visible && cspHeaderPresent && state.framework_script_count > 0 && state.framework_script_nonce_count === 0) failureCode = "E2E_HYDRATION_NONCE_MISSING";
      else if (state.loading_visible && pageErrorObserved) failureCode = "E2E_HYDRATION_CLIENT_ERROR";
      else if (state.loading_visible && scriptRequestFailureObserved) failureCode = "E2E_HYDRATION_SCRIPT_REQUEST_FAILED";
      else if (state.loading_visible) failureCode = "E2E_HYDRATION_STUCK_LOADING";
      else failureCode = "E2E_HYDRATION_AUTH_FORM_MISSING";
      exitCode = 1;
    }

    evidence.result = {
      status,
      failure_code: failureCode,
      csp_header_present: cspHeaderPresent,
      csp_violation_observed: cspViolationObserved,
      page_error_observed: pageErrorObserved,
      script_request_failure_observed: scriptRequestFailureObserved,
      loading_visible: state.loading_visible,
      auth_form_visible: state.auth_form_visible,
      framework_script_count: state.framework_script_count,
      framework_script_nonce_count: state.framework_script_nonce_count,
    };
  } finally {
    if (browser) await browser.close().catch(() => {});
    await stopChild(app);
    await new Promise((resolve) => mock.close(resolve));
  }

  const output = path.join(evidenceDir, `${candidate.replace(/[^A-Za-z0-9._-]/g, "-")}-hydration-security.json`);
  fs.writeFileSync(output, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });
  const result = evidence.result || { status: "FAIL", failure_code: "E2E_HYDRATION_HARNESS_FAILURE" };
  console.log(`Hydration security acceptance: ${result.status}${result.failure_code ? ` (${result.failure_code})` : ""}`);
  process.exitCode = exitCode;
}

main().catch(() => {
  console.error("Hydration security acceptance aborted: E2E_HYDRATION_HARNESS_FAILURE");
  process.exitCode = 1;
});
