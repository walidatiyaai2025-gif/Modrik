import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import Home from "./page";

test("renders the MODRIK landing page with separate Student and Admin entry points", () => {
  const markup = renderToStaticMarkup(<Home />);

  assert.match(markup, /MODRIK/);
  assert.match(markup, /مُدرك/);
  assert.match(markup, /Student demo/);
  assert.match(markup, /System admin demo/);
  assert.match(markup, /href="\/student"/);
  assert.match(markup, /https:\/\/api\.demo\.modrik\.org\/admin\/login/);
  assert.doesNotMatch(markup, /access_token|Bearer|MODRIK_FIXTURE_BEARER_TOKEN/);
});
