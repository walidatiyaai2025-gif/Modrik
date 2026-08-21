const assert = require("node:assert/strict");
const { chromium } = require("playwright");

const targetUrl = process.env.MODRIK_CSP_PROBE_URL ?? "http://127.0.0.1:3000/";
const targetOrigin = new URL(targetUrl).origin;

async function main() {
  const browser = await chromium.launch({ headless: true });

  try {
    const page = await browser.newPage();
    let sessionBootstrapObserved = false;

    page.on("request", (request) => {
      try {
        const url = new URL(request.url());
        if (url.origin === targetOrigin && url.pathname === "/api/auth/session") {
          sessionBootstrapObserved = true;
        }
      } catch {
        // Ignore non-URL browser internals; the probe records no request payloads.
      }
    });

    await page.addInitScript(() => {
      window.__modrikCspScriptViolationCount = 0;
      document.addEventListener("securitypolicyviolation", (event) => {
        if (event.effectiveDirective === "script-src" || event.effectiveDirective === "script-src-elem") {
          window.__modrikCspScriptViolationCount += 1;
        }
      });
    });

    const response = await page.goto(targetUrl, { waitUntil: "domcontentloaded", timeout: 15_000 });
    assert.ok(response, "CSP probe did not receive a document response");
    assert.equal(response.status(), 200, "CSP probe document did not return HTTP 200");

    const csp = response.headers()["content-security-policy"] ?? "";
    const nonceMatch = csp.match(/script-src[^;]*'nonce-([^']+)'[^;]*'strict-dynamic'/);

    assert.ok(nonceMatch, "production CSP is missing its script nonce/strict-dynamic boundary");
    assert.doesNotMatch(csp, /script-src[^;]*'unsafe-inline'/, "production script CSP weakened to unsafe-inline");
    assert.doesNotMatch(csp, /script-src[^;]*'unsafe-eval'/, "production script CSP weakened to unsafe-eval");

    await page.waitForFunction(() => document.querySelector('[aria-busy="true"]') === null, undefined, {
      timeout: 10_000,
    });

    const scriptNonces = await page.locator("script").evaluateAll((scripts) =>
      scripts
        .filter((script) => !script.type || script.type === "text/javascript" || script.type === "application/javascript")
        .map((script) => script.nonce),
    );

    assert.ok(scriptNonces.length > 0, "hydrated document exposed no executable framework scripts");
    assert.ok(
      scriptNonces.every((nonce) => nonce === nonceMatch[1]),
      "one or more executable framework scripts do not carry the request CSP nonce",
    );

    const scriptViolationCount = await page.evaluate(() => window.__modrikCspScriptViolationCount);
    assert.equal(scriptViolationCount, 0, "Chromium observed a script CSP violation during hydration");
    assert.equal(sessionBootstrapObserved, true, "client hydration never initiated the Auth session bootstrap");

    console.log("Web CSP hydration probe: PASS");
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(`Web CSP hydration probe: FAIL — ${error instanceof Error ? error.message : "unknown assertion"}`);
  process.exitCode = 1;
});
