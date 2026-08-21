import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import { authCopy, localeDirection } from "./auth-copy";
import { ProviderPendingNotice, SessionExpiredNotice } from "./auth-workspace";

const authCss = readFileSync(new URL("./auth.css", import.meta.url), "utf8");

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

test("Auth chrome consumes the canonical logo and semantic warning/error tokens", () => {
  assert.match(authCss, /background-image:\s*url\("\/brand\/logo-horizontal\.svg"\)/);
  assert.match(authCss, /\.auth-loading \.auth-mark\s*{[\s\S]*?display:\s*none;/);
  assert.match(authCss, /var\(--modrik-warning\)/);
  assert.match(authCss, /var\(--modrik-error\)/);

  for (const offTokenColor of ["#f5a524", "#8f1d18", "#9f241f", "#b42318"]) {
    assert.doesNotMatch(authCss, new RegExp(offTokenColor, "i"));
  }
});

test("Auth responsive CSS contains narrow and 200 percent text without clipping critical controls", () => {
  assert.match(
    authCss,
    /\.auth-shell,\s*\.auth-shell \*,\s*\.auth-authenticated,\s*\.auth-authenticated \*\s*{[\s\S]*?box-sizing:\s*border-box;/,
  );
  assert.match(authCss, /\.auth-main\s*{[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;/);
  assert.match(authCss, /\.auth-form input\s*{[\s\S]*?box-sizing:\s*border-box;[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;/);
  assert.match(authCss, /\.auth-loading h1\s*{[\s\S]*?flex-wrap:\s*wrap;[\s\S]*?overflow-wrap:\s*anywhere;/);
  assert.match(authCss, /\.auth-brand-lockup\.compact\s*{[\s\S]*?max-width:\s*100%;[\s\S]*?flex:\s*0 1 auto;/);
  assert.match(authCss, /\.auth-authenticated\s*{[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;/);
  assert.match(authCss, /\.auth-account-grid\s*{[\s\S]*?min-width:\s*0;[\s\S]*?max-width:\s*100%;/);
  assert.match(
    authCss,
    /@media \(max-width: 560px\)[\s\S]*?\.auth-locale button\s*{[\s\S]*?flex:\s*1 1 0;[\s\S]*?min-width:\s*0;/,
  );
  assert.doesNotMatch(authCss, /\.auth-shell[\s\S]*?overflow-x:\s*(?:hidden|clip)/);
});
