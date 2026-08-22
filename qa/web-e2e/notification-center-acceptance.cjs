const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.MODRIK_NOTIFICATION_BASE_URL || 'http://127.0.0.1:3100';
const evidenceDir = process.env.MODRIK_NOTIFICATION_EVIDENCE_DIR || path.resolve('evidence/notification-center');
const expectedSha = process.env.MODRIK_NOTIFICATION_EXPECTED_SHA || 'unknown';
const requestId = '123e4567-e89b-42d3-a456-426614174000';
const notificationId = '01J00000000000000000000098';

fs.mkdirSync(evidenceDir, { recursive: true });

const copy = {
  en: {
    title: 'Notifications',
    loading: 'Loading notifications…',
    empty: "You're all caught up",
    offline: "You're offline. Reconnect to refresh your notification inbox.",
    permission: 'Sign in to view your notifications.',
    unavailable: 'Notifications are temporarily unavailable.',
    markRead: 'Mark as read',
    read: 'Read',
  },
  ar: {
    title: 'الإشعارات',
    loading: 'جارٍ تحميل الإشعارات…',
    empty: 'لا توجد إشعارات جديدة',
    offline: 'أنت غير متصل. أعد الاتصال لتحديث صندوق الإشعارات.',
    permission: 'سجّل الدخول لعرض إشعاراتك.',
    unavailable: 'الإشعارات غير متاحة مؤقتًا.',
    markRead: 'تحديد كمقروء',
    read: 'مقروء',
  },
  fr: {
    title: 'Notifications',
    loading: 'Chargement des notifications…',
    empty: 'Vous êtes à jour',
    offline: 'Vous êtes hors ligne. Reconnectez-vous pour actualiser vos notifications.',
    permission: 'Connectez-vous pour voir vos notifications.',
    unavailable: 'Les notifications sont temporairement indisponibles.',
    markRead: 'Marquer comme lue',
    read: 'Lue',
  },
};

function envelope(data) {
  return { data, meta: { request_id: requestId } };
}

function problem(status, code, detail) {
  return {
    type: `https://modrik.org/problems/${code.toLowerCase()}`,
    title: status === 403 ? 'Request rejected' : 'Learning service unavailable',
    status,
    code,
    detail,
    request_id: requestId,
    retryable: status >= 500,
  };
}

function notification(locale, isRead = false) {
  const titles = {
    en: 'Progress update',
    ar: 'تحديث التقدم',
    fr: 'Mise à jour de progression',
  };
  const bodies = {
    en: 'Review your latest MODRIK progress.',
    ar: 'راجع أحدث تقدم لك في مُدرك.',
    fr: 'Consultez votre progression MODRIK la plus récente.',
  };
  return {
    id: notificationId,
    kind: 'progress_update',
    title: titles,
    body: bodies,
    action: 'progress',
    occurred_at: '2026-08-22T12:00:00Z',
    read_at: isRead ? '2026-08-22T12:01:00Z' : null,
    is_read: isRead,
  };
}

async function installApiRoutes(page, { locale, mode = 'ready', delayMs = 0 }) {
  let read = false;
  await page.route('**/api/learning/**', async (route) => {
    const request = route.request();
    const pathname = new URL(request.url()).pathname;
    const relative = pathname.replace(/^\/api\/learning\//, '');

    if (relative === 'session' && request.method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(envelope({
          user_id: '01J00000000000000000000030',
          locale,
          roles: ['student'],
        })),
      });
    }

    if (relative === 'notifications' && request.method() === 'GET') {
      if (delayMs > 0) await new Promise((resolve) => setTimeout(resolve, delayMs));
      if (mode === 'permission') {
        return route.fulfill({
          status: 403,
          contentType: 'application/problem+json',
          body: JSON.stringify(problem(403, 'FORBIDDEN', 'The current account cannot access this inbox.')),
        });
      }
      if (mode === 'error') {
        return route.fulfill({
          status: 503,
          contentType: 'application/problem+json',
          body: JSON.stringify(problem(503, 'NOTIFICATION_SERVICE_UNAVAILABLE', 'The notification service is temporarily unavailable.')),
        });
      }
      const items = mode === 'empty' ? [] : [notification(locale, read)];
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: { 'Cache-Control': 'no-store, private' },
        body: JSON.stringify(envelope({ items, unread_count: items.length > 0 && !read ? 1 : 0 })),
      });
    }

    if (relative === `notifications/${notificationId}/read` && request.method() === 'PUT') {
      read = true;
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: { 'Cache-Control': 'no-store, private' },
        body: JSON.stringify(envelope(notification(locale, true))),
      });
    }

    if (relative === 'notifications/read-all' && request.method() === 'PUT') {
      read = true;
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: { 'Cache-Control': 'no-store, private' },
        body: JSON.stringify(envelope({ updated_count: 1, unread_count: 0 })),
      });
    }

    return route.fulfill({
      status: 404,
      contentType: 'application/problem+json',
      body: JSON.stringify(problem(404, 'RESOURCE_NOT_FOUND', `Unexpected browser fixture route: ${relative}`)),
    });
  });
}

async function geometry(page) {
  return page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
    direction: document.querySelector('[data-testid="modrik-student-notification-center"]')?.getAttribute('dir') || null,
    lang: document.querySelector('[data-testid="modrik-student-notification-center"]')?.getAttribute('lang') || null,
  }));
}

async function requireNoHorizontalOverflow(page, name) {
  const value = await geometry(page);
  if (value.scrollWidth > value.clientWidth + 1) {
    throw new Error(`${name}: horizontal overflow ${value.scrollWidth} > ${value.clientWidth}`);
  }
  return value;
}

async function requireVisibleFocus(page, name) {
  await page.keyboard.press('Tab');
  const focused = await page.evaluate(() => {
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
  if (!focused) throw new Error(`${name}: keyboard focus did not reach an interactive control`);
  if (focused.outlineStyle === 'none' || Number.parseFloat(focused.outlineWidth) < 2) {
    throw new Error(`${name}: visible focus ring missing on ${focused.tag}`);
  }
  return focused;
}

async function runReadyCase(browser, spec) {
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  const page = await context.newPage();
  await installApiRoutes(page, { locale: spec.locale, mode: 'ready', delayMs: 350 });
  const navigation = page.goto(`${baseUrl}/student/notifications`, { waitUntil: 'domcontentloaded' });
  if (spec.locale === 'en') {
    await page.getByText(copy.en.loading, { exact: true }).waitFor({ state: 'visible' });
  }
  await navigation;
  await page.getByTestId('modrik-student-notification-center').waitFor({ state: 'visible' });
  await page.getByRole('heading', { name: copy[spec.locale].title, exact: true }).waitFor();

  if (spec.zoom === 2) {
    await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
  }

  const root = page.getByTestId('modrik-student-notification-center');
  const direction = await root.getAttribute('dir');
  const expectedDirection = spec.locale === 'ar' ? 'rtl' : 'ltr';
  if (direction !== expectedDirection) throw new Error(`${spec.name}: expected ${expectedDirection}, got ${direction}`);

  const geo = await requireNoHorizontalOverflow(page, spec.name);
  const focus = await requireVisibleFocus(page, spec.name);

  const readButton = page.getByRole('button', { name: copy[spec.locale].markRead, exact: true });
  const box = await readButton.boundingBox();
  if (!box || box.height < 44 || box.width < 44) {
    throw new Error(`${spec.name}: mark-read target smaller than 44px`);
  }
  await readButton.click();
  await page.getByText(copy[spec.locale].read, { exact: true }).waitFor();

  if (spec.offline) {
    await context.setOffline(true);
    await page.getByText(copy[spec.locale].offline, { exact: true }).waitFor();
    await context.setOffline(false);
  }

  await page.screenshot({ path: path.join(evidenceDir, `${spec.name}.png`), fullPage: true });
  await context.close();
  return { name: spec.name, status: 'PASS', locale: spec.locale, viewport: [spec.width, spec.height], zoom: spec.zoom, geometry: geo, focus };
}

async function runStateCase(browser, spec) {
  const context = await browser.newContext({ viewport: { width: spec.width, height: spec.height } });
  const page = await context.newPage();
  await installApiRoutes(page, { locale: spec.locale, mode: spec.mode });
  await page.goto(`${baseUrl}/student/notifications`, { waitUntil: 'domcontentloaded' });
  const expected = spec.mode === 'empty'
    ? copy[spec.locale].empty
    : spec.mode === 'permission'
      ? copy[spec.locale].permission
      : copy[spec.locale].unavailable;
  await page.getByText(expected, { exact: true }).waitFor();
  const geo = await requireNoHorizontalOverflow(page, spec.name);
  await page.screenshot({ path: path.join(evidenceDir, `${spec.name}.png`), fullPage: true });
  await context.close();
  return { name: spec.name, status: 'PASS', locale: spec.locale, mode: spec.mode, viewport: [spec.width, spec.height], geometry: geo };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];
  try {
    for (const spec of [
      { name: 'desktop-en', locale: 'en', width: 1440, height: 900, zoom: 1, offline: true },
      { name: 'mobile-fr-360-200', locale: 'fr', width: 360, height: 800, zoom: 2, offline: false },
      { name: 'mobile-ar-320-200', locale: 'ar', width: 320, height: 720, zoom: 2, offline: false },
    ]) {
      results.push(await runReadyCase(browser, spec));
    }
    for (const spec of [
      { name: 'empty-en', locale: 'en', mode: 'empty', width: 390, height: 844 },
      { name: 'permission-ar', locale: 'ar', mode: 'permission', width: 320, height: 720 },
      { name: 'error-fr', locale: 'fr', mode: 'error', width: 360, height: 800 },
    ]) {
      results.push(await runStateCase(browser, spec));
    }

    const payload = {
      schema_version: 'modrik.student-notification-browser-acceptance.v1',
      status: 'PASS',
      expected_sha: expectedSha,
      cases: results,
    };
    fs.writeFileSync(path.join(evidenceDir, 'acceptance.json'), `${JSON.stringify(payload, null, 2)}\n`);
    console.log(`Student Notification Center browser acceptance: ${results.length} PASS / 0 FAIL`);
  } catch (error) {
    const payload = {
      schema_version: 'modrik.student-notification-browser-acceptance.v1',
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
