"use strict";

const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require("playwright");
const playwrightPackage = require("playwright/package.json");

const evidenceDir = path.resolve(
  process.env.MODRIK_E2E_EVIDENCE_DIR || path.join(process.cwd(), ".runtime", "web-browser-evidence"),
);
const observedSha = process.env.MODRIK_E2E_OBSERVED_SHA || null;
const candidate = process.env.MODRIK_E2E_CANDIDATE || "browser-runtime";

async function main() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  let browserVersion = null;
  try {
    browserVersion = browser.version();
  } finally {
    await browser.close();
  }

  const payload = {
    schema_version: "modrik.web.browser-runtime-manifest.v1",
    candidate,
    observed_sha: observedSha,
    generated_at: new Date().toISOString(),
    engine: "chromium",
    browser_version: browserVersion,
    playwright_version: playwrightPackage.version,
    capture_policy: {
      screenshots: false,
      traces: false,
      videos: false,
      dom_dumps: false,
      console_messages: false,
      request_response_bodies: false,
      credentials: false,
    },
  };

  fs.writeFileSync(
    path.join(evidenceDir, "browser-runtime-manifest.json"),
    `${JSON.stringify(payload, null, 2)}\n`,
    { mode: 0o600 },
  );
  console.log(`Browser runtime: Chromium ${browserVersion}; Playwright ${playwrightPackage.version}`);
}

main().catch(() => {
  console.error("Browser runtime manifest: FAIL (E2E_BROWSER_RUNTIME_MANIFEST_FAILURE)");
  process.exitCode = 1;
});
