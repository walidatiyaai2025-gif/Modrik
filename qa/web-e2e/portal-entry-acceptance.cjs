const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.MODRIK_PORTAL_BASE_URL || 'http://127.0.0.1:3100';
const evidenceDir = process.env.MODRIK_PORTAL_EVIDENCE_DIR || path.resolve('evidence/web-portals-runtime');
const expectedSha = process.env.MODRIK_PORTAL_EXPECTED_SHA || 'unknown';

fs.mkdirSync(evidenceDir, { recursive: true });

const copy = {
  en: { studentCta: 'Student sign in', signInTitle: 'Sign in' },
  fr: { studentCta: 'Connexion élève', signInTitle: 'Se connecter' },
  ar: { studentCta: 'دخول الطالب', signInTitle: 'تسجيل الدخول' },
};

function problem(status, code, detail) {
  return {
    type: `https://modrik.org/problems/${code.toLowerCase()}`,
    title: 'Authentication required',
    status,
    code,
    detail,
    request_id: '123e4567-e89b-42d3-a456-426614174000',
    retryable: false,
  };
}

async function installUnauthenticatedBoundary(page) {
  await page.route('**/api/auth/session', async (route) => {
    await route.fulfill({
      status: 401,
      contentType: 'application/problem+json',
      headers: { 'Cache-Control': 'no-store, private' },
      body: JSON.stringify(problem(401, 'AUTHENTICATION_REQUIRED', 'A valid session is required.')),
    });
  });
}

async function geometry(page) {
  return page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
    bodyScrollWidth: document.body.scrollWidth,
    bodyClientWidth: document.body.clientWidth,
  }));
}

async function requireNoHorizontalOverflow(page, name) {
  const value = await geometry(page);
  if (value.scrollWidth > value.clientWidth + 1 || value.bodyScrollWidth > value.bodyClientWidth + 1) {
    throw new Error(`${name}: horizontal overflow document=${value.scrollWidth}/${value.clientWidth} body=${value.bodyScrollWidth}/${value.bodyClientWidth}`);
  }
  return value;
}

async function requireVisibleFocus(page, name) {
  await page.keyboard.press('Tab');
  const focus = await page.evaluate(() => {
    const node = document.activeElement;
    if (!node || node === document.body) return null;
    const style = getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return {
      tag: node.tagName,
      text: (node.textContent || '').trim().slice(0, 80),
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth,
      width: rect.width,
      height: rect.height,
    };
  });
  if (!focus) throw new Error(`${name}: keyboard focus did not reach an interactive element`);
  if (focus.outlineStyle === 'none' || Number.parseFloat(focus.outlineWidth) < 2) {
    throw new Error(`${name}: visible focus ring missing on ${focus.tag}`);
  }
  return focus;
}

async function requireTarget(locator, name) {
  const box = await locator.boundingBox();
  if (!box) throw new Error(`${name}: target has no geometry`);
  if (box.width < 44 || box.height < 44) {
    throw new Error(`${name}: target smaller than 44px (${box.width.toFixed(1)}x${box.height.toFixed(1)})`);
  }
  return { width: box.width, height: box.height };
}

async function setLocale(page, locale) {
  if (locale === 'en') return;
  await page.getByRole('button', { name: locale.toUpperCase(), exact: true }).click();
}

async function runCase(browser, spec) {
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  const page = await context.newPage();
  await installUnauthenticatedBoundary(page);

  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded' });
  const landing = page.getByTestId('modrik-landing-page');
  await landing.waitFor({ state: 'visible' });
  await setLocale(page, spec.locale);

  if (spec.zoom === 2) {
    await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
  }

  const expectedDirection = spec.locale === 'ar' ? 'rtl' : 'ltr';
  const landingDirection = await landing.getAttribute('dir');
  if (landingDirection !== expectedDirection) {
    throw new Error(`${spec.name}: Landing direction expected ${expectedDirection}, got ${landingDirection}`);
  }

  const landingGeometry = await requireNoHorizontalOverflow(page, `${spec.name}:landing`);
  const landingFocus = await requireVisibleFocus(page, `${spec.name}:landing`);
  const studentEntry = page.getByTestId('modrik-student-portal-entry');
  await requireTarget(studentEntry, `${spec.name}:student-entry`);
  const href = await studentEntry.getAttribute('href');
  if (href !== '/student') throw new Error(`${spec.name}: Student CTA points to ${href}`);

  await Promise.all([
    page.waitForURL((url) => url.pathname === '/student'),
    studentEntry.click(),
  ]);

  const studentPortal = page.getByTestId('modrik-student-portal');
  await studentPortal.waitFor({ state: 'visible' });
  if (await page.getByTestId('modrik-landing-page').count()) {
    throw new Error(`${spec.name}: Landing remained mounted on Student route`);
  }

  await page.locator('.auth-card form').waitFor({ state: 'visible' });
  await setLocale(page, spec.locale);
  const authShell = page.locator('section.auth-shell');
  const studentDirection = await authShell.getAttribute('dir');
  if (studentDirection !== expectedDirection) {
    throw new Error(`${spec.name}: Student direction expected ${expectedDirection}, got ${studentDirection}`);
  }

  await page.getByRole('heading', { name: copy[spec.locale].signInTitle, exact: true }).waitFor();
  const studentGeometry = await requireNoHorizontalOverflow(page, `${spec.name}:student`);
  const submit = page.locator('form.auth-form button.auth-primary').first();
  const submitTarget = await requireTarget(submit, `${spec.name}:student-submit`);

  await page.screenshot({ path: path.join(evidenceDir, `${spec.name}.png`), fullPage: true });
  await context.close();

  return {
    name: spec.name,
    status: 'PASS',
    locale: spec.locale,
    viewport: [spec.width, spec.height],
    zoom: spec.zoom,
    landing: { geometry: landingGeometry, focus: landingFocus },
    student: { geometry: studentGeometry, submitTarget },
  };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];
  try {
    const cases = [
      { name: 'desktop-en', locale: 'en', width: 1440, height: 900, zoom: 1 },
      { name: 'mobile-fr-360-200', locale: 'fr', width: 360, height: 800, zoom: 2 },
      { name: 'mobile-ar-320-200', locale: 'ar', width: 320, height: 720, zoom: 2 },
    ];

    for (const spec of cases) results.push(await runCase(browser, spec));

    const payload = {
      schema_version: 'modrik.web-portals-runtime.v1',
      status: 'PASS',
      expected_sha: expectedSha,
      cases: results,
    };
    fs.writeFileSync(path.join(evidenceDir, 'acceptance.json'), `${JSON.stringify(payload, null, 2)}\n`);
    console.log(`MODRIK Landing -> Student Portal acceptance: ${results.length} PASS / 0 FAIL`);
  } catch (error) {
    const payload = {
      schema_version: 'modrik.web-portals-runtime.v1',
      status: 'FAIL',
      expected_sha: expectedSha,
      cases: results,
      error: error instanceof Error ? error.message : String(error),
    };
    fs.writeFileSync(path.join(evidenceDir, 'acceptance.json'), `${JSON.stringify(payload, null, 2)}\n`);
    console.error(error);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
