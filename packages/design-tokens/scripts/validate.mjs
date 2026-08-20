import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

const directory = new URL("../", import.meta.url);
const tokens = JSON.parse(await readFile(new URL("tokens.json", directory), "utf8"));
const css = await readFile(new URL("tokens.css", directory), "utf8");

assert.equal(tokens.meta.brand, "MODRIK | مُدرك");
assert.equal(tokens.meta.status, "LOCKED_FOR_PILOT");

const expectedCssVariables = {
  "--modrik-navy": tokens.color.brand.navy.$value,
  "--modrik-blue": tokens.color.brand.blue.$value,
  "--modrik-teal": tokens.color.brand.teal.$value,
  "--modrik-sky": tokens.color.brand.sky.$value,
  "--modrik-amber": tokens.color.brand.amber.$value,
  "--modrik-background": tokens.color.neutral.background.$value,
  "--modrik-ink": tokens.color.neutral.ink.$value,
};

for (const [name, value] of Object.entries(expectedCssVariables)) {
  assert.match(css, new RegExp(`${name}:\\s*${value.replace("#", "\\#")}`, "i"));
}

console.log(`Validated ${Object.keys(expectedCssVariables).length} canonical MODRIK CSS token mappings.`);
