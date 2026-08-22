"use strict";

const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require("playwright");

const baseURL = process.env.MODRIK_ADMIN_BASE_URL || "http://127.0.0.1:8000";
const evidenceDir = path.resolve(process.env.MODRIK_ADMIN_CONTRAST_EVIDENCE_DIR || "admin-sidebar-contrast-evidence");
const expectedSha = process.env.MODRIK_ADMIN_EXPECTED_SHA || null;
const email = process.env.MODRIK_DEMO_ADMIN_EMAIL || "admin.evidence@example.test";
const password = process.env.MODRIK_DEMO_ADMIN_PASSWORD || "ModrikEvidenceAdmin123!";

fs.mkdirSync(evidenceDir, { recursive: true });

function parseColor(value) {
  const match = String(value).match(/rgba?\(\s*([\d.]+)[, ]+\s*([\d.]+)[, ]+\s*([\d.]+)(?:\s*[,/]\s*([\d.]+))?\s*\)/i);
  if (!match) throw new Error(`UNSUPPORTED_COLOR:${value}`);
  return {
    r: Number(match[1]),
    g: Number(match[2]),
    b: Number(match[3]),
    a: match[4] === undefined ? 1 : Number(match[4]),
  };
}

function composite(foreground, background) {
  const alpha = foreground.a + background.a * (1 - foreground.a);
  if (alpha <= 0) return { r: 0, g: 0, b: 0, a: 0 };
  return {
    r: (foreground.r * foreground.a + background.r * background.a * (1 - foreground.a)) / alpha,
    g: (foreground.g * foreground.a + background.g * background.a * (1 - foreground.a)) / alpha,
    b: (foreground.b * foreground.a + background.b * background.a * (1 - foreground.a)) / alpha,
    a: alpha,
  };
}

function channel(value) {
  const normalized = value / 255;
  return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
}

function luminance(color) {
  return 0.2126 * channel(color.r) + 0.7152 * channel(color.g) + 0.0722 * channel(color.b);
}

function contrast(a, b) {
  const l1 = luminance(a);
  const l2 = luminance(b);
  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

function gradientStops(backgroundImage) {
  const matches = [...String(backgroundImage).matchAll(/rgba?\([^)]*\)/gi)].map((match) => parseColor(match[0]));
  if (matches.length < 2) throw new Error(`SIDEBAR_GRADIENT_UNRESOLVED:${backgroundImage}`);
  return matches;
}

function effectiveRatios(foregroundCss, overlayCss, sidebarStops) {
  const foreground = parseColor(foregroundCss);
  const overlay = parseColor(overlayCss);
  return sidebarStops.map((stop) => {
    const background = composite(overlay, stop);
    const renderedForeground = composite(foreground, background);
    return contrast(renderedForeground, background);
  });
}

async function login(page) {
  await page.goto(`${baseURL}/admin/login`, { waitUntil: "networkidle" });
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(password);
  await Promise.all([
    page.waitForURL(/\/admin(?:\?|$)/, { timeout: 15000 }),
    page.locator('button[type="submit"]').click(),
  ]);
}

async function collectCase(page, locale) {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`${baseURL}/admin?admin_locale=${locale}`, { waitUntil: "networkidle" });

  const direction = await page.locator("html").getAttribute("dir");
  if (direction !== (locale === "ar" ? "rtl" : "ltr")) {
    throw new Error(`${locale}:SIDEBAR_DIRECTION_MISMATCH:${direction}`);
  }

  const sidebar = page.locator(".fi-sidebar");
  await sidebar.waitFor({ state: "visible" });

  const styles = await page.evaluate(() => {
    const visible = (element) => element instanceof HTMLElement && element.getClientRects().length > 0;
    const firstVisible = (selector) => Array.from(document.querySelectorAll(selector)).find(visible);
    const styleOf = (element) => {
      if (!(element instanceof Element)) throw new Error("SIDEBAR_ELEMENT_MISSING");
      const style = getComputedStyle(element);
      return {
        color: style.color,
        backgroundColor: style.backgroundColor,
        backgroundImage: style.backgroundImage,
        opacity: style.opacity,
        fontWeight: style.fontWeight,
      };
    };

    const sidebarElement = firstVisible(".fi-sidebar");
    const groupLabel = firstVisible(".fi-sidebar-group-label");
    const inactiveLabel = firstVisible(".fi-sidebar-item:not(.fi-active) .fi-sidebar-item-label");
    const activeLabel = firstVisible(".fi-sidebar-item.fi-active .fi-sidebar-item-label");
    const inactiveButton = inactiveLabel?.closest(".fi-sidebar-item-button");
    const activeButton = activeLabel?.closest(".fi-sidebar-item-button");
    const inactiveIcon = firstVisible(".fi-sidebar-item:not(.fi-active) .fi-sidebar-item-icon");
    const activeIcon = firstVisible(".fi-sidebar-item.fi-active .fi-sidebar-item-icon");

    return {
      sidebar: styleOf(sidebarElement),
      groupLabel: styleOf(groupLabel),
      inactiveLabel: styleOf(inactiveLabel),
      activeLabel: styleOf(activeLabel),
      inactiveButton: styleOf(inactiveButton),
      activeButton: styleOf(activeButton),
      inactiveIcon: styleOf(inactiveIcon),
      activeIcon: styleOf(activeIcon),
    };
  });

  const stops = gradientStops(styles.sidebar.backgroundImage);
  const ratios = {
    groupLabel: effectiveRatios(styles.groupLabel.color, "rgba(0, 0, 0, 0)", stops),
    inactiveLabel: effectiveRatios(styles.inactiveLabel.color, styles.inactiveButton.backgroundColor, stops),
    activeLabel: effectiveRatios(styles.activeLabel.color, styles.activeButton.backgroundColor, stops),
    inactiveIcon: effectiveRatios(styles.inactiveIcon.color, styles.inactiveButton.backgroundColor, stops),
    activeIcon: effectiveRatios(styles.activeIcon.color, styles.activeButton.backgroundColor, stops),
  };

  const minimum = (values) => Math.min(...values);
  if (minimum(ratios.groupLabel) < 4.5) throw new Error(`${locale}:GROUP_LABEL_CONTRAST:${minimum(ratios.groupLabel).toFixed(2)}`);
  if (minimum(ratios.inactiveLabel) < 4.5) throw new Error(`${locale}:INACTIVE_LABEL_CONTRAST:${minimum(ratios.inactiveLabel).toFixed(2)}`);
  if (minimum(ratios.activeLabel) < 4.5) throw new Error(`${locale}:ACTIVE_LABEL_CONTRAST:${minimum(ratios.activeLabel).toFixed(2)}`);
  if (minimum(ratios.inactiveIcon) < 3) throw new Error(`${locale}:INACTIVE_ICON_CONTRAST:${minimum(ratios.inactiveIcon).toFixed(2)}`);
  if (minimum(ratios.activeIcon) < 3) throw new Error(`${locale}:ACTIVE_ICON_CONTRAST:${minimum(ratios.activeIcon).toFixed(2)}`);

  const inactiveButton = page.locator(".fi-sidebar-item:not(.fi-active) .fi-sidebar-item-button").filter({ visible: true }).first();
  const beforeHover = await inactiveButton.evaluate((element) => getComputedStyle(element).backgroundColor);
  await inactiveButton.hover();
  const afterHover = await inactiveButton.evaluate((element) => getComputedStyle(element).backgroundColor);
  if (beforeHover === afterHover) throw new Error(`${locale}:SIDEBAR_HOVER_NOT_DISTINCT`);

  await page.mouse.move(1200, 600);
  await page.locator("body").click({ position: { x: 1000, y: 700 } });
  let focusVisible = false;
  for (let attempt = 0; attempt < 40; attempt += 1) {
    await page.keyboard.press("Tab");
    focusVisible = await page.evaluate(() => {
      const element = document.activeElement;
      return element instanceof HTMLElement
        && element.matches(".fi-sidebar-item-button:focus-visible")
        && parseFloat(getComputedStyle(element).outlineWidth) >= 2;
    });
    if (focusVisible) break;
  }
  if (!focusVisible) throw new Error(`${locale}:SIDEBAR_FOCUS_NOT_VISIBLE`);

  return {
    locale,
    direction,
    computed: styles,
    ratios: Object.fromEntries(Object.entries(ratios).map(([key, values]) => [key, {
      minimum: Number(minimum(values).toFixed(3)),
      samples: values.map((value) => Number(value.toFixed(3))),
    }])),
    hover: { before: beforeHover, after: afterHover },
    focusVisible,
  };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ locale: "en-US" });
  const page = await context.newPage();
  const cases = [];

  try {
    await login(page);
    cases.push(await collectCase(page, "en"));
    cases.push(await collectCase(page, "ar"));

    fs.writeFileSync(path.join(evidenceDir, "sidebar-contrast.json"), JSON.stringify({
      schema_version: "modrik.admin-sidebar-rendered-contrast.v1",
      expected_sha: expectedSha,
      status: "PASS",
      wcag: { text_minimum: 4.5, icon_minimum: 3 },
      cases,
    }, null, 2) + "\n");
  } catch (error) {
    fs.writeFileSync(path.join(evidenceDir, "sidebar-contrast.json"), JSON.stringify({
      schema_version: "modrik.admin-sidebar-rendered-contrast.v1",
      expected_sha: expectedSha,
      status: "FAIL",
      error: String(error && error.stack || error),
      cases,
    }, null, 2) + "\n");
    throw error;
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
