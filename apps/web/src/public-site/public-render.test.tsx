import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import { PublicSiteView } from "./public-site-view";

const testStyles = new Proxy<Record<string, string>>(
  {},
  { get: (_target, property) => String(property) },
);

function render(pageKey: Parameters<typeof PublicSiteView>[0]["pageKey"], locale: Parameters<typeof PublicSiteView>[0]["locale"]) {
  return renderToStaticMarkup(<PublicSiteView pageKey={pageKey} locale={locale} styles={testStyles} />);
}

test("landing renders learner-first semantic keyboard navigation with secondary release disclosure", () => {
  const markup = render("landing", "en");
  const mainEnd = markup.indexOf("</main>");
  const releaseDisclosure = markup.indexOf("This Next.js landing is a release candidate surface only.");

  assert.match(markup, /lang="en"/);
  assert.match(markup, /dir="ltr"/);
  assert.match(markup, /href="#public-main"/);
  assert.match(markup, /<header/);
  assert.match(markup, /<nav/);
  assert.match(markup, /<main id="public-main"/);
  assert.match(markup, /<footer/);
  assert.match(markup, /Learn More\. Achieve More\./);
  assert.match(markup, /A calmer way to learn/);
  assert.match(markup, /Study without the clutter/);
  assert.match(markup, /Practice and return with confidence/);
  assert.match(markup, /Keep progress easy to read/);
  assert.match(markup, /Focused study/);
  assert.match(markup, /href="\/help"/);
  assert.match(markup, /href="\/about"/);
  assert.match(markup, /brand\/logo-horizontal\.svg/);
  assert.ok(mainEnd > 0, "landing must render a main boundary");
  assert.ok(releaseDisclosure > mainEnd, "release disclosure must be secondary footer content");
  assert.doesNotMatch(markup, /<code>\/landing<\/code>/);
  assert.doesNotMatch(markup.slice(0, mainEnd), /backend-owned|server-authoritative|Backend/);
});

test("landing learner-first hierarchy is localized for Arabic and French", () => {
  const arabicMarkup = render("landing", "ar");
  const frenchMarkup = render("landing", "fr");

  assert.match(arabicMarkup, /dir="rtl"/);
  assert.match(arabicMarkup, /تعلّم بهدوء ووضوح/);
  assert.match(arabicMarkup, /ذاكر دون تشتيت/);
  assert.match(arabicMarkup, /تدريب يُستأنف كما هو/);

  assert.match(frenchMarkup, /dir="ltr"/);
  assert.match(frenchMarkup, /Apprendre avec calme et clarté/);
  assert.match(frenchMarkup, /Étudier sans distraction/);
  assert.match(frenchMarkup, /Exercices qui reprennent/);
});

test("Arabic legal template renders RTL with explicit non-final and blocker semantics", () => {
  const markup = render("privacy", "ar");

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
  const markup = render("help", "fr");

  assert.match(markup, /lang="fr"/);
  assert.match(markup, /dir="ltr"/);
  assert.match(markup, /Guide de l’apprenant/);
  assert.match(markup, /Reconnectez-vous en sécurité/);
  assert.match(markup, /SUPPORT_CHANNEL_HOURS/);
  assert.match(markup, /PUBLIC_CONTACT/);
});
