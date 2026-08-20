import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const css = readFileSync(new URL("./globals.css", import.meta.url), "utf8");

function ruleBody(selector: RegExp) {
  const match = css.match(new RegExp(`(?:^|\\n)${selector.source}\\s*\\{([\\s\\S]*?)\\n\\}`, "m"));
  assert.ok(match, `Expected CSS rule matching ${selector}`);
  return match[1];
}

test("native select and textarea inherit canonical multilingual typography and focus", () => {
  assert.match(
    css,
    /\[lang="ar"\],\s*\[lang="ar"\] button,\s*\[lang="ar"\] input,\s*\[lang="ar"\] select,\s*\[lang="ar"\] textarea\s*\{[\s\S]*?Noto Kufi Arabic/,
  );
  assert.match(css, /button,\s*input,\s*select,\s*textarea,\s*summary\s*\{\s*font: inherit;/);
  assert.match(
    css,
    /button:focus-visible,\s*input:focus-visible,\s*select:focus-visible,\s*textarea:focus-visible,\s*summary:focus-visible,\s*a:focus-visible\s*\{[\s\S]*?outline: 3px solid var\(--modrik-teal\);[\s\S]*?outline-offset: 3px;/,
  );
});

test("native select and textarea use tokenized large-text-safe control geometry", () => {
  const controls = ruleBody(/select,\s*textarea/);

  assert.match(controls, /box-sizing: border-box;/);
  assert.match(controls, /width: 100%;/);
  assert.match(controls, /max-width: 100%;/);
  assert.match(controls, /min-width: 0;/);
  assert.match(controls, /min-height: 3\.2rem;/);
  assert.match(controls, /padding: 0\.75rem 0\.9rem;/);
  assert.match(controls, /color: var\(--modrik-ink\);/);
  assert.match(controls, /line-height: 1\.5;/);
  assert.match(controls, /background: var\(--modrik-white\);/);
  assert.match(controls, /border: 2px solid color-mix\(in srgb, var\(--modrik-blue\) 20%, transparent\);/);
  assert.match(controls, /border-radius: var\(--modrik-radius-sm\);/);
  assert.match(css, /\ntextarea\s*\{\s*min-height: 7\.5rem;\s*resize: vertical;\s*\}/);
});

test("disabled native controls remain legible and visibly unavailable", () => {
  const disabled = ruleBody(/select:disabled,\s*textarea:disabled/);

  assert.match(disabled, /color: var\(--modrik-slate\);/);
  assert.match(disabled, /background: var\(--modrik-background\);/);
  assert.match(disabled, /border-color: color-mix\(in srgb, var\(--modrik-slate\) 28%, transparent\);/);
  assert.match(disabled, /cursor: not-allowed;/);
  assert.match(disabled, /opacity: 1;/);
});
