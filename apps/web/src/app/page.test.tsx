import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import Home from "./page";

test("renders the accessible fixture learning loading shell", () => {
  const markup = renderToStaticMarkup(<Home />);

  assert.match(markup, /MODRIK/);
  assert.match(markup, /مُدرك/);
  assert.match(markup, /Synthetic fixture/);
  assert.match(markup, /Loading your fixture learning workspace/);
  assert.match(markup, /role="status"/);
  assert.match(markup, /aria-live="polite"/);
  assert.match(markup, /aria-pressed="true"/);
  assert.match(markup, />AR</);
  assert.match(markup, />EN</);
  assert.match(markup, />FR</);
});
