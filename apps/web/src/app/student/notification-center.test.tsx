import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

import { notificationCopy } from "./notification-copy";

const portal = readFileSync(new URL("./page.tsx", import.meta.url), "utf8");
const shortcut = readFileSync(new URL("./notification-shortcut.tsx", import.meta.url), "utf8");
const center = readFileSync(new URL("./notifications/notification-center.tsx", import.meta.url), "utf8");
const stylesheet = readFileSync(new URL("./notifications/notification-center.module.css", import.meta.url), "utf8");
const bff = readFileSync(new URL("../api/learning/[...path]/route.ts", import.meta.url), "utf8");
const api = readFileSync(new URL("../../lib/learning-api.ts", import.meta.url), "utf8");

test("Notification Center is discoverable from the Student portal", () => {
  assert.match(portal, /<NotificationShortcut \/>/);
  assert.match(shortcut, /href="\/student\/notifications"/);
  assert.match(shortcut, /data-testid="modrik-student-notification-shortcut"/);
  assert.match(center, /data-testid="modrik-student-notification-center"/);
});

test("Notification Center covers loading empty offline permission error retry and read states", () => {
  assert.match(center, /type ViewState = "loading" \| "ready" \| "offline" \| "error" \| "permission"/);
  assert.match(center, /inbox\.items\.length === 0/);
  assert.match(center, /window\.addEventListener\("offline"/);
  assert.match(center, /window\.addEventListener\("online"/);
  assert.match(center, /learningApi\.markNotificationRead/);
  assert.match(center, /learningApi\.markAllNotificationsRead/);
  assert.match(center, /aria-live="polite"/);
  assert.match(stylesheet, /:focus-visible/);
  assert.match(stylesheet, /@media \(max-width: 30rem\)/);
});

test("notification API client and BFF expose only the bounded inbox/read contract", () => {
  assert.match(api, /notifications: \(\) => requestData<StudentNotificationInbox>/);
  assert.match(api, /markNotificationRead/);
  assert.match(api, /markAllNotificationsRead/);
  assert.ok(bff.includes('/^notifications$/'));
  assert.ok(bff.includes('/^notifications\\/read-all$/'));
  assert.ok(bff.includes('new RegExp(`^notifications/${ulid}/read$`)'));
  assert.doesNotMatch(api, /registration[_ -]?token/i);
  assert.doesNotMatch(center, /registration[_ -]?token/i);
});

test("Notification Center copy exists for AR EN FR", () => {
  for (const locale of ["ar", "en", "fr"] as const) {
    const copy = notificationCopy[locale];
    assert.ok(copy.title.length > 0);
    assert.ok(copy.subtitle.length > 0);
    assert.ok(copy.emptyTitle.length > 0);
    assert.ok(copy.offline.length > 0);
    assert.ok(copy.permission.length > 0);
    assert.ok(copy.markAll.length > 0);
  }
});
