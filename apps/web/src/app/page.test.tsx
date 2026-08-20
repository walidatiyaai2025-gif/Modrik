import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import Home from "./page";

test("renders an accessible session-bootstrap shell without exposing credentials", () => {
  const markup = renderToStaticMarkup(<Home />);

  assert.match(markup, /MODRIK/);
  assert.match(markup, /مُدرك/);
  assert.match(markup, /Checking your account session/);
  assert.match(markup, /role="status"/);
  assert.match(markup, /aria-live="polite"/);
  assert.match(markup, /aria-busy="true"/);
  assert.match(markup, /href="#auth-main"/);
  assert.doesNotMatch(markup, /access_token|Bearer|MODRIK_FIXTURE_BEARER_TOKEN/);
});
