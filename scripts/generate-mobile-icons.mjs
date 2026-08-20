import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import pngToIco from "png-to-ico";
import { Resvg } from "@resvg/resvg-js";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const logo = await readFile(path.join(root, "deploy", "coming-soon", "assets", "logo-mark.svg"), "utf8");
const navy = "#0D1B2A";

function renderPng(size) {
  return new Resvg(logo, {
    background: navy,
    fitTo: { mode: "width", value: size },
  }).render().asPng();
}

async function render(target, size) {
  const png = renderPng(size);
  await writeFile(target, png);
}

const androidSizes = {
  "mipmap-mdpi": 48,
  "mipmap-hdpi": 72,
  "mipmap-xhdpi": 96,
  "mipmap-xxhdpi": 144,
  "mipmap-xxxhdpi": 192,
};

for (const [density, size] of Object.entries(androidSizes)) {
  await render(path.join(root, "apps", "mobile", "android", "app", "src", "main", "res", density, "ic_launcher.png"), size);
}

const iosDirectory = path.join(root, "apps", "mobile", "ios", "Runner", "Assets.xcassets", "AppIcon.appiconset");
const iosContents = JSON.parse(await readFile(path.join(iosDirectory, "Contents.json"), "utf8"));
for (const image of iosContents.images) {
  if (!image.filename) continue;
  const points = Number.parseFloat(image.size.split("x")[0]);
  const scale = Number.parseInt(image.scale, 10);
  await render(path.join(iosDirectory, image.filename), Math.round(points * scale));
}

await render(path.join(root, "apps", "web", "src", "app", "icon.png"), 512);
await render(path.join(root, "apps", "backend", "public", "favicon.png"), 128);
const favicon = await pngToIco([renderPng(32), renderPng(64)]);
await writeFile(path.join(root, "apps", "web", "src", "app", "favicon.ico"), favicon);
await writeFile(path.join(root, "apps", "backend", "public", "favicon.ico"), favicon);

console.log(`Generated ${Object.keys(androidSizes).length + iosContents.images.length + 4} application icons from the canonical MODRIK SVG.`);
