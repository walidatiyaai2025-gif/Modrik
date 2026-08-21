"use strict";

// The Demo information architecture reserves `/` for the public landing page
// and hosts the Student Auth/Learning runtime at `/student`. During PR browser
// acceptance, however, the full-command slice intentionally executes against
// authoritative `main`. Until this PR is merged, that baseline still hosts the
// Student runtime at `/`. Detect the target tree so the same harness can verify
// both layouts without creating a false regression.

const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require("playwright");

const targetDir = path.resolve(process.env.MODRIK_E2E_TARGET_DIR || process.cwd());
const targetHome = path.join(targetDir, "apps", "web", "src", "app", "page.tsx");
let usesLandingHome = false;

try {
  usesLandingHome = fs.readFileSync(targetHome, "utf8").includes('import LandingPage from "./landing-page"');
} catch {
  usesLandingHome = false;
}

if (usesLandingHome) {
  const originalLaunch = chromium.launch.bind(chromium);

  chromium.launch = async (...launchArgs) => {
    const browser = await originalLaunch(...launchArgs);
    const originalNewContext = browser.newContext.bind(browser);

    browser.newContext = async (...contextArgs) => {
      const context = await originalNewContext(...contextArgs);

      await context.route(/^http:\/\/127\.0\.0\.1:\d+\/$/, async (route) => {
        const request = route.request();
        await route.continue({
          url: `${request.url()}student`,
        });
      });

      return context;
    };

    return browser;
  };
}
