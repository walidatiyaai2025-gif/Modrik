import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import Home from "./page";

test("renders the branded desktop bootstrap shell", () => {
  const markup = renderToStaticMarkup(<Home />);

  assert.match(markup, /MODRIK/);
  assert.match(markup, /مُدرك/);
  assert.match(markup, /Student Web · Bootstrap shell/);
  assert.match(markup, /lang="ar" dir="rtl"/);
  assert.match(markup, /Study/);
  assert.match(markup, /Practice/);
  assert.match(markup, /Progress/);
});
