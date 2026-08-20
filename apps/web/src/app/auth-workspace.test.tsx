import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import { authCopy, localeDirection } from "./auth-copy";
import { ProviderPendingNotice, SessionExpiredNotice } from "./auth-workspace";

test("AR EN FR auth copy stays complete and Arabic is RTL", () => {
  const englishKeys = Object.keys(authCopy.en).sort();
  assert.deepEqual(Object.keys(authCopy.ar).sort(), englishKeys);
  assert.deepEqual(Object.keys(authCopy.fr).sort(), englishKeys);
  assert.equal(localeDirection("ar"), "rtl");
  assert.equal(localeDirection("en"), "ltr");
  assert.equal(localeDirection("fr"), "ltr");
});

test("provider pending and revoked-session states are screen-reader announced", () => {
  const provider = renderToStaticMarkup(<ProviderPendingNotice locale="ar" />);
  assert.match(provider, /role="status"/);
  assert.match(provider, /aria-live="polite"/);
  assert.match(provider, /إعداد المزود قيد الانتظار/);

  const expired = renderToStaticMarkup(<SessionExpiredNotice locale="fr" />);
  assert.match(expired, /role="alert"/);
  assert.match(expired, /session est terminée/);
});

test("enumeration-resistant recovery copy does not assert account existence", () => {
  for (const locale of ["ar", "en", "fr"] as const) {
    const text = authCopy[locale].recoveryAccepted;
    assert.ok(text.length > 20);
  }
  assert.match(authCopy.en.recoveryAccepted, /^If an eligible account/);
  assert.doesNotMatch(authCopy.en.recoveryAccepted, /account exists|account does not exist/i);
});
