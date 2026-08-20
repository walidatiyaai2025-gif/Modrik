import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import { PublicSite } from "./public-site";

test("landing renders semantic keyboard-first public navigation", () => {
  const markup = renderToStaticMarkup(<PublicSite pageKey="landing" locale="en" />);

  assert.match(markup, /lang="en"/);
  assert.match(markup, /dir="ltr"/);
  assert.match(markup, /href="#public-main"/);
  assert.match(markup, /<header/);
  assert.match(markup, /<nav/);
  assert.match(markup, /<main id="public-main"/);
  assert.match(markup, /<footer/);
  assert.match(markup, /Learn More\. Achieve More\./);
  assert.match(markup, /href="\/help"/);
  assert.match(markup, /href="\/about"/);
  assert.match(markup, /\/brand\/logo-horizontal\.svg/);
});

test("Arabic legal template renders RTL with explicit non-final and blocker semantics", () => {
  const markup = renderToStaticMarkup(<PublicSite pageKey="privacy" locale="ar" />);

  assert.match(markup, /lang="ar"/);
  assert.match(markup, /dir="rtl"/);
  assert.match(markup, /نموذج الخصوصية/);
  assert.match(markup, /ليس نصًا قانونيًا معتمدًا/);
  assert.match(markup, /LEGAL_ENTITY_CONTROLLER/);
  assert.match(markup, /VENDOR_INVENTORY/);
  assert.match(markup, /RETENTION_SCHEDULE/);
  assert.match(markup, /href="\/privacy\?lang=fr"/);
});

test("French learner guide remains LTR and exposes reconnect and support guidance", () => {
  const markup = renderToStaticMarkup(<PublicSite pageKey="help" locale="fr" />);

  assert.match(markup, /lang="fr"/);
  assert.match(markup, /dir="ltr"/);
  assert.match(markup, /Guide de l’apprenant/);
  assert.match(markup, /Reconnectez-vous en sécurité/);
  assert.match(markup, /SUPPORT_CHANNEL_HOURS/);
  assert.match(markup, /PUBLIC_CONTACT/);
});
