const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.MODRIK_PORTAL_BASE_URL || 'http://127.0.0.1:3100';
const evidenceDir = process.env.MODRIK_PORTAL_EVIDENCE_DIR || path.resolve('evidence/portal-entry');
const expectedSha = process.env.MODRIK_PORTAL_EXPECTED_SHA || 'unknown';
fs.mkdirSync(evidenceDir, { recursive: true });

async function noOverflow(page, name) {
  const geometry = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));
  if (geometry.scrollWidth > geometry.clientWidth + 1) {
    throw new Error(`${name}: horizontal overflow ${geometry.scrollWidth} > ${geometry.clientWidth}`);
  }
  return geometry;
}

async function visibleFocus(page, name) {
  await page.keyboard.press('Tab');
  const focus = await page.evaluate(() => {
    const node = document.activeElement;
    if (!node || node === document.body) return null;
    const style = getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return {
      tag: node.tagName,
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth,
      width: rect.width,
      height: rect.height,
    };
  });
  if (!focus) throw new Error(`${name}: keyboard focus did not reach a control`);
  if (focus.outlineStyle === 'none' || Number.parseFloat(focus.outlineWidth) < 2) {
    throw new Error(`${name}: visible focus ring missing`);
  }
  return focus;
}

async function installAuthBoundary(page) {
  await page.route('**/api/auth/**', async (route) => {
    const request = route.request();
    const pathname = new URL(request.url()).pathname;
    if (pathname.endsWith('/api/auth/session') && request.method() === 'GET') {
      return route.fulfill({
        status: 401,
        contentType: 'application/problem+json',
        body: JSON.stringify({
          type: 'https://modrik.org/problems/authentication-required',
          title: 'Authentication required',
          status: 401,
          code: 'AUTHENTICATION_REQUIRED',
          detail: 'A valid session is required.',
          retryable: false,
        }),
      });
    }
    return route.fulfill({
      status: 404,
      contentType: 'application/problem+json',
      body: JSON.stringify({ status: 404, code: 'RESOURCE_NOT_FOUND', detail: 'Fixture route unavailable.' }),
    });
  });
}

async function requireRelease(page, name) {
  const badge = page.getByTestId('modrik-web-release-badge');
  await badge.waitFor({ state: 'visible' });
  if (expectedSha !== 'unknown') {
    const title = await badge.getAttribute('title');
    if (title !== `MODRIK deployed release: ${expectedSha}`) {
      throw new Error(`${name}: release title mismatch: ${title}`);
    }
    const text = (await badge.textContent() || '').trim();
    if (!text.includes(`Build ${expectedSha.slice(0, 12)}`)) {
      throw new Error(`${name}: short build identity mismatch`);
    }
  }
}

async function landingCase(browser, spec) {
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  const page = await context.newPage();
  await installAuthBoundary(page);
  await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle' });
  await requireRelease(page, spec.name);
  const landing = page.getByTestId('modrik-landing-page');
  await landing.waitFor({ state: 'visible' });
  if (spec.locale !== 'en') await page.getByRole('button', { name: spec.locale.toUpperCase(), exact: true }).click();
  if (spec.zoom === 2) await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
  const expectedDir = spec.locale === 'ar' ? 'rtl' : 'ltr';
  const dir = await landing.getAttribute('dir');
  const lang = await landing.getAttribute('lang');
  if (dir !== expectedDir || lang !== spec.locale) throw new Error(`${spec.name}: locale/direction mismatch ${lang}/${dir}`);
  const cta = page.getByTestId('modrik-student-portal-cta');
  if ((await cta.getAttribute('href')) !== '/student') throw new Error(`${spec.name}: Student CTA route mismatch`);
  const box = await cta.boundingBox();
  if (!box || box.width < 44 || box.height < 44) throw new Error(`${spec.name}: Student CTA target smaller than 44px`);
  const geometry = await noOverflow(page, spec.name);
  const focus = await visibleFocus(page, spec.name);
  await page.screenshot({ path: path.join(evidenceDir, `${spec.name}.png`), fullPage: true });
  await context.close();
  return { name: spec.name, status: 'PASS', locale: spec.locale, viewport: [spec.width, spec.height], zoom: spec.zoom, geometry, focus };
}

async function transitionCase(browser) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  await installAuthBoundary(page);
  await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle' });
  await page.getByTestId('modrik-student-portal-cta').click();
  await page.waitForURL('**/student');
  await page.getByTestId('modrik-student-portal-route').waitFor({ state: 'attached' });
  await page.locator('.auth-shell').waitFor({ state: 'visible' });
  await page.locator('.auth-main').waitFor({ state: 'visible' });
  await requireRelease(page, 'landing-to-student');
  const geometry = await noOverflow(page, 'landing-to-student');
  await page.screenshot({ path: path.join(evidenceDir, 'landing-to-student.png'), fullPage: true });
  await context.close();
  return { name: 'landing-to-student', status: 'PASS', route: '/student', geometry };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];
  try {
    for (const spec of [
      { name: 'landing-en-desktop', locale: 'en', width: 1440, height: 900, zoom: 1 },
      { name: 'landing-fr-360-200', locale: 'fr', width: 360, height: 800, zoom: 2 },
      { name: 'landing-ar-320-200', locale: 'ar', width: 320, height: 720, zoom: 2 },
    ]) results.push(await landingCase(browser, spec));
    results.push(await transitionCase(browser));

    const payload = {
      schema_version: 'modrik.portal-entry-browser-acceptance.v1',
      expected_sha: expectedSha,
      status: 'PASS',
      cases: results,
    };
    fs.writeFileSync(path.join(evidenceDir, 'acceptance.json'), `${JSON.stringify(payload, null, 2)}\n`);
    console.log(`MODRIK Landing/Student Portal browser acceptance: ${results.length} PASS / 0 FAIL`);
  } catch (error) {
    fs.writeFileSync(path.join(evidenceDir, 'acceptance.json'), `${JSON.stringify({ schema_version: 'modrik.portal-entry-browser-acceptance.v1', expected_sha: expectedSha, status: 'FAIL', cases: results, error: error instanceof Error ? error.message : String(error) }, null, 2)}\n`);
    console.error(error);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
