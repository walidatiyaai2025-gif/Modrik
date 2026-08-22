const { chromium } = require('playwright');

const baseUrl = process.env.MODRIK_ADMIN_SIDEBAR_BASE_URL || 'http://127.0.0.1:8000';
const expectedSha = process.env.EXPECTED_SHA || 'unknown';
const email = process.env.MODRIK_DEMO_ADMIN_EMAIL || 'admin.sidebar@example.test';
const password = process.env.MODRIK_DEMO_ADMIN_PASSWORD || 'ModrikSidebarContrast123!';

function clamp01(value) {
  return Math.min(1, Math.max(0, value));
}

function alphaValue(value) {
  if (value == null) return 1;
  const text = String(value).trim();
  return text.endsWith('%') ? Number.parseFloat(text) / 100 : Number.parseFloat(text);
}

function gammaEncode(value) {
  const channel = clamp01(value);
  return channel <= 0.0031308
    ? 12.92 * channel
    : 1.055 * (channel ** (1 / 2.4)) - 0.055;
}

function parseColor(value) {
  const text = String(value).trim().toLowerCase();

  if (text === 'transparent') return { r: 0, g: 0, b: 0, a: 0 };

  const rgb = text.match(/^rgba?\(([^)]+)\)$/i);
  if (rgb) {
    const parts = rgb[1].split(/[\s,\/]+/).filter(Boolean);
    const component = (part) => String(part).endsWith('%')
      ? Number.parseFloat(part) * 2.55
      : Number.parseFloat(part);
    return {
      r: component(parts[0]),
      g: component(parts[1]),
      b: component(parts[2]),
      a: alphaValue(parts[3]),
    };
  }

  const hex = text.match(/^#([0-9a-f]{6})([0-9a-f]{2})?$/i);
  if (hex) {
    return {
      r: Number.parseInt(hex[1].slice(0, 2), 16),
      g: Number.parseInt(hex[1].slice(2, 4), 16),
      b: Number.parseInt(hex[1].slice(4, 6), 16),
      a: hex[2] ? Number.parseInt(hex[2], 16) / 255 : 1,
    };
  }

  const srgb = text.match(/^color\(srgb\s+([^\s/]+)\s+([^\s/]+)\s+([^\s/]+)(?:\s*\/\s*([^\s)]+))?\)$/i);
  if (srgb) {
    return {
      r: clamp01(Number.parseFloat(srgb[1])) * 255,
      g: clamp01(Number.parseFloat(srgb[2])) * 255,
      b: clamp01(Number.parseFloat(srgb[3])) * 255,
      a: alphaValue(srgb[4]),
    };
  }

  const oklch = text.match(/^oklch\(\s*([^\s]+)\s+([^\s]+)\s+([^\s/]+)(?:\s*\/\s*([^\s)]+))?\s*\)$/i);
  if (oklch) {
    const lightnessText = oklch[1];
    const lightness = lightnessText.endsWith('%')
      ? Number.parseFloat(lightnessText) / 100
      : Number.parseFloat(lightnessText);
    const chroma = Number.parseFloat(oklch[2]);
    const hue = oklch[3] === 'none' ? 0 : Number.parseFloat(oklch[3]);
    const radians = hue * Math.PI / 180;
    const a = chroma * Math.cos(radians);
    const b = chroma * Math.sin(radians);

    const lPrime = lightness + 0.3963377774 * a + 0.2158037573 * b;
    const mPrime = lightness - 0.1055613458 * a - 0.0638541728 * b;
    const sPrime = lightness - 0.0894841775 * a - 1.2914855480 * b;
    const l = lPrime ** 3;
    const m = mPrime ** 3;
    const s = sPrime ** 3;

    const linearR = 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
    const linearG = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
    const linearB = -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s;

    return {
      r: gammaEncode(linearR) * 255,
      g: gammaEncode(linearG) * 255,
      b: gammaEncode(linearB) * 255,
      a: alphaValue(oklch[4]),
    };
  }

  throw new Error(`Unsupported rendered color: ${value}`);
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

function linearChannel(value) {
  const s = clamp01(value / 255);
  return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
}

function luminance(color) {
  return 0.2126 * linearChannel(color.r)
    + 0.7152 * linearChannel(color.g)
    + 0.0722 * linearChannel(color.b);
}

function contrast(foreground, background) {
  const l1 = luminance(foreground);
  const l2 = luminance(background);
  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

function gradientColors(backgroundImage) {
  const source = String(backgroundImage || '');
  const tokens = source.match(/rgba?\([^)]*\)|oklch\([^)]*\)|color\(srgb\s+[^)]*\)|#[0-9a-f]{6}(?:[0-9a-f]{2})?/gi) || [];
  return tokens.map(parseColor);
}

async function renderedModel(locator) {
  return locator.evaluate((node) => {
    const foreground = getComputedStyle(node).color;
    const sidebar = node.closest('.fi-sidebar');
    if (!sidebar) throw new Error('sidebar ancestor missing');

    let current = node;
    let overlay = 'rgba(0, 0, 0, 0)';
    while (current && current !== sidebar) {
      const candidate = getComputedStyle(current).backgroundColor;
      if (candidate && candidate !== 'transparent' && candidate !== 'rgba(0, 0, 0, 0)') {
        overlay = candidate;
        break;
      }
      current = current.parentElement;
    }

    const sidebarStyle = getComputedStyle(sidebar);
    return {
      foreground,
      overlay,
      sidebarBackgroundColor: sidebarStyle.backgroundColor,
      sidebarBackgroundImage: sidebarStyle.backgroundImage,
    };
  });
}

async function requireContrast(locator, kind, locale, minimum) {
  const count = await locator.count();
  for (let index = 0; index < count; index += 1) {
    const sample = locator.nth(index);
    const model = await renderedModel(sample);
    const stops = gradientColors(model.sidebarBackgroundImage);
    const sidebarBackgrounds = stops.length > 0
      ? stops
      : [parseColor(model.sidebarBackgroundColor)];
    const foreground = parseColor(model.foreground);
    const overlay = parseColor(model.overlay);
    const ratios = sidebarBackgrounds.map((sidebarBackground) => {
      const effectiveBackground = composite(overlay, sidebarBackground);
      const effectiveForeground = composite(foreground, effectiveBackground);
      return contrast(effectiveForeground, effectiveBackground);
    });
    const minimumRatio = Math.min(...ratios);
    if (minimumRatio < minimum) {
      throw new Error(
        `${locale} ${kind} ${index} contrast ${minimumRatio.toFixed(2)} < ${minimum}; `
        + `fg=${model.foreground}; overlay=${model.overlay}; sidebar=${model.sidebarBackgroundImage}`,
      );
    }
  }
}

async function requireHoverAndFocus(page, locale) {
  const inactiveButton = page.locator(
    '.fi-sidebar .fi-sidebar-item:not(.fi-active) :is(.fi-sidebar-item-btn, .fi-sidebar-item-button):visible',
  ).first();
  if (!(await inactiveButton.isVisible())) throw new Error(`${locale}: no inactive sidebar button rendered`);

  const beforeHover = await inactiveButton.evaluate((element) => getComputedStyle(element).backgroundColor);
  await inactiveButton.hover();
  await page.waitForTimeout(180);
  const afterHover = await inactiveButton.evaluate((element) => getComputedStyle(element).backgroundColor);
  if (beforeHover === afterHover) throw new Error(`${locale}: sidebar hover is not visually distinct`);

  await page.mouse.move(1200, 700);
  await page.locator('body').click({ position: { x: 1000, y: 700 } });
  let focusVisible = false;
  for (let attempt = 0; attempt < 50; attempt += 1) {
    await page.keyboard.press('Tab');
    focusVisible = await page.evaluate(() => {
      const element = document.activeElement;
      if (!(element instanceof HTMLElement)) return false;
      if (!element.matches('.fi-sidebar-item-btn:focus-visible, .fi-sidebar-item-button:focus-visible')) return false;
      const style = getComputedStyle(element);
      return parseFloat(style.outlineWidth) >= 2 && style.outlineStyle !== 'none';
    });
    if (focusVisible) break;
  }
  if (!focusVisible) throw new Error(`${locale}: sidebar keyboard focus is not visibly outlined`);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();

  try {
    await page.goto(`${baseUrl}/admin/login`, { waitUntil: 'networkidle' });
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await Promise.all([
      page.waitForURL(/\/admin(?:\?|$)/, { timeout: 15000 }),
      page.locator('button[type="submit"]').click(),
    ]);

    for (const locale of ['en', 'ar']) {
      await page.goto(`${baseUrl}/admin?admin_locale=${locale}`, { waitUntil: 'networkidle' });
      const sidebar = page.locator('.fi-sidebar');
      if (!(await sidebar.isVisible())) throw new Error(`${locale}: sidebar is not visible`);

      const assetCount = await page.locator('link[rel="stylesheet"][href*="sidebar-contrast"]').count();
      if (assetCount < 1) throw new Error(`${locale}: sidebar contrast asset is not loaded after build`);

      const inactiveLabels = page.locator('.fi-sidebar .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-label:visible');
      const activeLabels = page.locator('.fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-label:visible');
      const groups = page.locator('.fi-sidebar .fi-sidebar-group-label:visible');
      const navigationIcons = page.locator([
        '.fi-sidebar .fi-sidebar-group-icon:visible',
        '.fi-sidebar .fi-sidebar-item-icon:visible',
        '.fi-sidebar .fi-sidebar-group-button .fi-icon:visible',
        '.fi-sidebar .fi-sidebar-item-btn .fi-icon:visible',
        '.fi-sidebar .fi-sidebar-item-button .fi-icon:visible',
      ].join(', '));

      if ((await inactiveLabels.count()) < 2) throw new Error(`${locale}: insufficient inactive sidebar labels`);
      if ((await activeLabels.count()) < 1) throw new Error(`${locale}: no active sidebar label rendered`);
      if ((await groups.count()) < 2) throw new Error(`${locale}: insufficient sidebar group labels`);
      if ((await navigationIcons.count()) < 2) throw new Error(`${locale}: insufficient visible sidebar navigation icons`);

      await requireContrast(inactiveLabels, 'inactive-item', locale, 4.5);
      await requireContrast(activeLabels, 'active-item', locale, 4.5);
      await requireContrast(groups, 'group', locale, 4.5);
      await requireContrast(navigationIcons, 'navigation-icon', locale, 3);
      await requireHoverAndFocus(page, locale);

      const dir = await page.locator('html').getAttribute('dir');
      if (dir !== (locale === 'ar' ? 'rtl' : 'ltr')) {
        throw new Error(`${locale}: unexpected document direction ${dir}`);
      }
    }

    console.log(`MODRIK_ADMIN_SIDEBAR_CONTRAST_OK head=${expectedSha.slice(0, 12)}`);
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
