import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import Home from "./page";

test("renders a desktop-first accessible multilingual student shell while data loads", () => {
  const markup = renderToStaticMarkup(<Home />);

  assert.match(markup, /MODRIK/);
  assert.match(markup, /مُدرك/);
  assert.match(markup, /Synthetic fixture/);
  assert.match(markup, /Loading your learning workspace/);
  assert.match(markup, /class="student-sidebar"/);
  assert.match(markup, /class="student-stage"/);
  assert.match(markup, /aria-label="Student workspace navigation"/);
  assert.match(markup, /aria-current="page"/);
  assert.match(markup, /role="status"/);
  assert.match(markup, /aria-live="polite"/);
  assert.match(markup, /href="#student-main"/);
  assert.match(markup, /aria-pressed="true"/);
  assert.match(markup, />AR</);
  assert.match(markup, />EN</);
  assert.match(markup, />FR</);
});
