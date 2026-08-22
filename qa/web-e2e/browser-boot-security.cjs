"use strict";

const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const appDir = path.join(targetDir, "apps", "web");
const evidenceDir = path.resolve(process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(targetDir, ".e2e-evidence"));
const appPort = Number(process.env.MODRIK_E2E_APP_PORT || 3210);
const mockPort = Number(process.env.MODRIK_E2E_MOCK_PORT || 4210);
const baseURL = `http://127.0.0.1:${appPort}`;
const studentURL = `${baseURL}/student`;
const expectedSha = process.env.MODRIK_E2E_EXPECTED_SHA || null;
const harnessSha = process.env.MODRIK_E2E_HARNESS_SHA || null;

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function fail(code) {
  throw new Error(code);
}

function check(value, code) {
  if (!value) fail(code);
}

function safeCode(error) {
  const message = error instanceof Error ? error.message : "";
  return /^E2E_[A-Z0-9_:-]+$/.test(message) ? message : "E2E_BROWSER_BOOT_ASSERTION_FAILED";
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

async function closeServer(server) {
  if (!server.listening) return;
  await new Promise((resolve) => server.close(() => resolve()));
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
    env: {
      ...process.env,
      MODRIK_API_BASE_URL: `http://127.0.0.1:${mockPort}`,
      MODRIK_FIXTURE_MODE: "false",
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
  if (!exited && child.exitCode === null) {
    child.kill("SIGKILL");
    await Promise.race([new Promise((resolve) => child.once("exit", resolve)), sleep(1000)]);
  }
}

function strictProductionNonce(csp, codePrefix) {
  check(typeof csp === "string" && csp.length > 0, `${codePrefix}_HEADER_MISSING`);
  check(/(?:^|;\s*)script-src\s/u.test(csp), `${codePrefix}_SCRIPT_SRC_MISSING`);
  check(csp.includes("'strict-dynamic'"), `${codePrefix}_STRICT_DYNAMIC_MISSING`);
  check(!csp.includes("'unsafe-inline'"), `${codePrefix}_UNSAFE_INLINE_PRESENT`);
  check(!csp.includes("'unsafe-eval'"), `${codePrefix}_UNSAFE_EVAL_PRESENT`);
  const match = csp.match(/'nonce-([^']+)'/u);
  check(Boolean(match?.[1] && match[1].length >= 16), `${codePrefix}_NONCE_MISSING`);
  return match[1];
}

const evidence = {
  schema_version: "modrik.web.browser-boot-security.v2",
  candidate: "current-main-boot-security",
  expected_sha: expectedSha,
  harness_sha: harnessSha,
  generated_at: new Date().toISOString(),
  browser: "chromium",
  security: {
    traces_recorded: false,
    screenshots_recorded: false,
    videos_recorded: false,
    dom_dumps_recorded: false,
    console_text_recorded: false,
    request_urls_recorded: false,
    request_response_bodies_recorded: false,
    credentials_recorded: false,
  },
  status: "FAIL",
  failure_code: null,
  auth_session_request_seen: false,
  csp_directive_classes: [],
  csp_header_present: false,
  strict_dynamic_present: false,
  unsafe_inline_absent: false,
  unsafe_eval_absent: false,
  request_bound_nonce_verified: false,
};

async function run() {
  const mockServer = http.createServer((req, res) => {
    const pathname = new URL(req.url, `http://127.0.0.1:${mockPort}`).pathname;
    if (pathname === "/v1/session") {
      return sendJson(res, 401, {
        type: "https://modrik.org/problems/authentication_required",
        title: "Authentication required",
        status: 401,
        code: "AUTHENTICATION_REQUIRED",
        detail: "Authentication required.",
        request_id: "e2e-boot-request",
        retryable: false,
      }, "application/problem+json");
    }
    return sendJson(res, 404, {
      type: "https://modrik.org/problems/resource_not_found",
      title: "Request rejected",
      status: 404,
      code: "RESOURCE_NOT_FOUND",
      detail: "Synthetic route not found.",
      request_id: "e2e-boot-request",
      retryable: false,
    }, "application/problem+json");
  });

  let child;
  let browser;
  try {
    await listen(mockServer, mockPort);
    child = startNext();
    await waitForHttp(studentURL);

    const probeOne = await fetch(studentURL, { redirect: "manual", cache: "no-store" });
    const cspOne = probeOne.headers.get("content-security-policy") || "";
    const nonceOne = strictProductionNonce(cspOne, "E2E_CSP_PROBE_ONE");

    const probeTwo = await fetch(studentURL, { redirect: "manual", cache: "no-store" });
    const cspTwo = probeTwo.headers.get("content-security-policy") || "";
    const nonceTwo = strictProductionNonce(cspTwo, "E2E_CSP_PROBE_TWO");
    check(nonceOne !== nonceTwo, "E2E_CSP_NONCE_NOT_REQUEST_BOUND");

    evidence.csp_header_present = true;
    evidence.strict_dynamic_present = true;
    evidence.unsafe_inline_absent = true;
    evidence.unsafe_eval_absent = true;
    evidence.request_bound_nonce_verified = true;

    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();

    let sessionRequestSeen = false;
    page.on("request", (request) => {
      try {
        const url = new URL(request.url());
        if (url.origin === baseURL && url.pathname === "/api/auth/session") sessionRequestSeen = true;
      } catch {
        // URL parsing failure is not evidence-bearing.
      }
    });

    await page.addInitScript(() => {
      globalThis.__modrikE2ECspDirectiveClasses = [];
      document.addEventListener("securitypolicyviolation", (event) => {
        const raw = String(event.effectiveDirective || event.violatedDirective || "");
        const normalized = raw.split(/\s+/u)[0].slice(0, 48);
        if (normalized && !globalThis.__modrikE2ECspDirectiveClasses.includes(normalized)) {
          globalThis.__modrikE2ECspDirectiveClasses.push(normalized);
        }
      });
    });

    const navigation = await page.goto(studentURL, { waitUntil: "domcontentloaded" });
    const navigationCsp = navigation?.headers()["content-security-policy"] || "";
    const navigationNonce = strictProductionNonce(navigationCsp, "E2E_CSP_BROWSER_NAVIGATION");
    check(navigationNonce !== nonceOne && navigationNonce !== nonceTwo, "E2E_CSP_BROWSER_NONCE_REUSED");

    const loginVisible = await Promise.race([
      page.locator(".auth-card form").first().waitFor({ state: "visible", timeout: 6000 }).then(() => true).catch(() => false),
      sleep(6200).then(() => false),
    ]);

    const directives = await page.evaluate(() => Array.isArray(globalThis.__modrikE2ECspDirectiveClasses)
      ? globalThis.__modrikE2ECspDirectiveClasses.slice(0, 8)
      : []);
    const loadingVisible = await page.locator(".auth-loading").isVisible().catch(() => false);

    evidence.auth_session_request_seen = sessionRequestSeen;
    evidence.csp_directive_classes = directives;

    if (loginVisible) {
      evidence.status = "PASS";
      return;
    }

    if (!sessionRequestSeen && directives.some((item) => item.startsWith("script-src"))) {
      fail("E2E_AUTH_BOOT_CSP_SCRIPT_BLOCKED");
    }
    if (!sessionRequestSeen && loadingVisible) {
      fail("E2E_AUTH_BOOT_HYDRATION_STALLED");
    }
    if (sessionRequestSeen && loadingVisible) {
      fail("E2E_AUTH_BOOT_SESSION_TRANSITION_STALLED");
    }
    fail("E2E_AUTH_BOOT_LOGIN_UNREACHABLE");
  } finally {
    if (browser) await browser.close();
    await stopChild(child);
    await closeServer(mockServer);
  }
}

(async () => {
  try {
    await run();
  } catch (error) {
    evidence.failure_code = safeCode(error);
  } finally {
    fs.mkdirSync(evidenceDir, { recursive: true });
    fs.writeFileSync(path.join(evidenceDir, "current-main-boot-security.json"), `${JSON.stringify(evidence, null, 2)}\n`, "utf8");
  }

  if (evidence.status !== "PASS") {
    process.stderr.write(`Browser boot security acceptance failed: ${evidence.failure_code}\n`);
    process.exitCode = 1;
  } else {
    process.stdout.write("Browser boot security acceptance: PASS\n");
  }
})();
