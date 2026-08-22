import assert from "node:assert/strict";
import test from "node:test";
import {
  CAPABILITY_CLASSIFICATIONS,
  validateCapabilitySurfaceMatrix,
} from "./capability-surface-contract.mjs";

const baseCapability = Object.freeze({
  id: "admin.example.settings",
  area: "operations",
  classification: "admin_manageable",
  surface: "ExampleSettings",
  status: "present",
  notes: "Fixture capability for contract validation.",
});

const validMatrix = () => ({
  schema_version: "1.0.0",
  updated: "2026-08-22",
  governance_id: "GOV-SURFACE-001",
  source: ["docs/product/CAPABILITY_SURFACE_GOVERNANCE.md"],
  classifications: [...CAPABILITY_CLASSIFICATIONS],
  capabilities: [{ ...baseCapability }],
});

test("accepts a well-formed capability surface matrix", () => {
  assert.deepEqual(validateCapabilitySurfaceMatrix(validMatrix()), {
    capabilityCount: 1,
    classificationCount: 5,
  });
});

test("rejects malformed schema metadata", () => {
  const matrix = validMatrix();
  matrix.schema_version = "1";
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /schema_version must be x\.y\.z/);

  matrix.schema_version = "1.0.0";
  matrix.updated = "22-08-2026";
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /updated must be YYYY-MM-DD/);
});

test("rejects duplicate capability IDs", () => {
  const matrix = validMatrix();
  matrix.capabilities.push({ ...baseCapability });
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /duplicate capability id/);
});

test("rejects unknown governance classifications", () => {
  const matrix = validMatrix();
  matrix.capabilities[0].classification = "editable_everywhere";
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /unknown classification/);
});

test("rejects incomplete classification inventory", () => {
  const matrix = validMatrix();
  matrix.classifications = matrix.classifications.filter((value) => value !== "deferred_disabled");
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /complete governance classification inventory/);
});

test("requires every entry to declare surface explicitly", () => {
  const matrix = validMatrix();
  delete matrix.capabilities[0].surface;
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /must explicitly declare surface/);
});

test("requires present capabilities to name a discoverable surface or authority reference", () => {
  const matrix = validMatrix();
  matrix.capabilities[0].surface = null;
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /status=present requires/);
});

test("rejects deferred capabilities falsely marked present", () => {
  const matrix = validMatrix();
  matrix.capabilities[0] = {
    ...baseCapability,
    id: "community.q_and_a",
    area: "community",
    classification: "deferred_disabled",
    surface: "CommunityAdmin",
    status: "present",
  };
  assert.throws(() => validateCapabilitySurfaceMatrix(matrix), /deferred_disabled capability cannot be status=present/);
});

test("allows explicitly deferred capabilities with no active surface", () => {
  const matrix = validMatrix();
  matrix.capabilities[0] = {
    ...baseCapability,
    id: "community.q_and_a",
    area: "community",
    classification: "deferred_disabled",
    surface: null,
    status: "p1_activation_gated",
  };
  assert.doesNotThrow(() => validateCapabilitySurfaceMatrix(matrix));
});
