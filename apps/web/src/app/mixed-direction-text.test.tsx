import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";

import { LocalizedQuestionText, MixedDirectionText } from "./mixed-direction-text";

const workspace = readFileSync(new URL("./learning-workspace.tsx", import.meta.url), "utf8");

test("MixedDirectionText isolates mixed Arabic Latin numbers and math", () => {
  const markup = renderToStaticMarkup(
    <section lang="ar" dir="rtl">
      <MixedDirectionText>احسب 3x + 5 = 20 ثم 12 cm × 8 cm و H2O</MixedDirectionText>
    </section>,
  );

  assert.match(markup, /<bdi dir="auto" data-modrik-mixed-direction="true" style="unicode-bidi:isolate">/);
  assert.match(markup, /3x \+ 5 = 20/);
  assert.match(markup, /12 cm × 8 cm/);
  assert.match(markup, /H2O/);
});

test("LocalizedQuestionText preserves semantic locale direction", () => {
  const ar = renderToStaticMarkup(
    <LocalizedQuestionText locale="ar" text="ما قيمة 25% of 80؟" />,
  );
  const en = renderToStaticMarkup(
    <LocalizedQuestionText locale="en" text="Solve 3x + 5 = 20" />,
  );

  assert.match(ar, /lang="ar" dir="rtl" data-modrik-question-text="true"/);
  assert.match(en, /lang="en" dir="ltr" data-modrik-question-text="true"/);
});

test("Practice question prompt uses the reusable localized mixed-direction component", () => {
  assert.match(workspace, /import \{ LocalizedQuestionText \} from "\.\/mixed-direction-text";/);
  assert.match(
    workspace,
    /<LocalizedQuestionText text=\{localize\(question\.prompt, locale\)\} locale=\{locale\} \/>/,
  );
});
