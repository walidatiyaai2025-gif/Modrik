import { readFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parse as parseYaml } from "yaml";
import { validateCapabilitySurfaceMatrix } from "./capability-surface-contract.mjs";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const matrixPath = path.join(root, "docs", "product", "capability-surface-matrix.yaml");
const matrix = parseYaml(await readFile(matrixPath, "utf8"));
const result = validateCapabilitySurfaceMatrix(matrix);

console.log(
  `Validated capability surface matrix: ${result.capabilityCount} capabilities across ${result.classificationCount} governance classifications.`,
);
