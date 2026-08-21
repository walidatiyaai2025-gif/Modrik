"use strict";

// The Demo information architecture intentionally reserves `/` for the public
// landing page and hosts the Student Auth/Learning runtime at `/student`.
// Existing Issue #108 browser runners exercise Student surface semantics rather
// than public-navigation semantics, so adapt only their exact loopback-root
// navigation to the canonical Student route. Public landing/CSP acceptance runs
// without this adapter and continues to exercise `/` directly.

const { chromium } = require("playwright");

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
