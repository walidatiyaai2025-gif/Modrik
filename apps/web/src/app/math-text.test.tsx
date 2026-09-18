import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";

import MathText from "./math-text";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");

test("MathText keeps math LTR and bidi-isolated inside Arabic RTL", () => {
  const markup = renderToStaticMarkup(
    <section lang="ar" dir="rtl">
      <p>
        احسب <MathText>3x + 5 = 20</MathText>
      </p>
    </section>,
  );

  assert.match(markup, /<section lang="ar" dir="rtl">/);
  assert.match(
    markup,
    /<span dir="ltr" data-modrik-math-text="true" style="unicode-bidi:isolate">3x \+ 5 = 20<\/span>/,
  );
});

test("MathText preserves the same LTR isolation inside English LTR", () => {
  const markup = renderToStaticMarkup(
    <section lang="en" dir="ltr">
      <p>
        Solve <MathText>25% of 80</MathText>
      </p>
    </section>,
  );

  assert.match(markup, /<section lang="en" dir="ltr">/);
  assert.match(
    markup,
    /<span dir="ltr" data-modrik-math-text="true" style="unicode-bidi:isolate">25% of 80<\/span>/,
  );
});

test("Practice result uses the shared MathText component without local bidi styling", () => {
  assert.match(workspace, /import MathText from "\.\/math-text";/);
  assert.match(
    workspace,
    /<span>\{labels\.result\}<\/span><strong><MathText>\{result\.score\} \/ \{result\.max_score\}<\/MathText><\/strong>/,
  );
  assert.doesNotMatch(workspace, /result\.score[\s\S]{0,120}unicodeBidi/);
});
