import assert from "node:assert/strict";

export const CAPABILITY_CLASSIFICATIONS = Object.freeze([
  "admin_manageable",
  "user_facing",
  "read_only_operational",
  "internal_non_editable",
  "deferred_disabled",
]);

const hasOwn = (value, key) => Object.prototype.hasOwnProperty.call(value, key);
const nonEmptyString = (value) => typeof value === "string" && value.trim().length > 0;
const semanticVersion = (value) => nonEmptyString(value) && /^\d+\.\d+\.\d+$/.test(value);
const isoDate = (value) => nonEmptyString(value) && /^\d{4}-\d{2}-\d{2}$/.test(value);

export function validateCapabilitySurfaceMatrix(matrix) {
  assert(matrix && typeof matrix === "object" && !Array.isArray(matrix), "capability matrix must be an object");
  assert.equal(matrix.governance_id, "GOV-SURFACE-001", "capability matrix must bind to GOV-SURFACE-001");
  assert(semanticVersion(matrix.schema_version), "capability matrix schema_version must be x.y.z");
  assert(isoDate(matrix.updated), "capability matrix updated must be YYYY-MM-DD");
  assert(Array.isArray(matrix.source) && matrix.source.length > 0, "capability matrix source must be non-empty");
  assert(matrix.source.every(nonEmptyString), "capability matrix source entries must be non-empty strings");

  assert(Array.isArray(matrix.classifications), "capability matrix classifications must be an array");
  assert.equal(new Set(matrix.classifications).size, matrix.classifications.length, "capability classifications must be unique");
  assert.deepEqual(
    [...matrix.classifications].sort(),
    [...CAPABILITY_CLASSIFICATIONS].sort(),
    "capability matrix must declare the complete governance classification inventory",
  );

  assert(Array.isArray(matrix.capabilities) && matrix.capabilities.length > 0, "capability matrix capabilities must be non-empty");

  const seenIds = new Set();
  for (const capability of matrix.capabilities) {
    assert(capability && typeof capability === "object" && !Array.isArray(capability), "capability entries must be objects");
    assert(nonEmptyString(capability.id), "capability id must be non-empty");
    assert(!seenIds.has(capability.id), `duplicate capability id: ${capability.id}`);
    seenIds.add(capability.id);

    assert.match(capability.id, /^[a-z0-9]+(?:[._][a-z0-9]+)*$/, `invalid capability id: ${capability.id}`);
    assert(nonEmptyString(capability.area), `${capability.id} area must be non-empty`);
    assert(
      CAPABILITY_CLASSIFICATIONS.includes(capability.classification),
      `${capability.id} has unknown classification: ${capability.classification}`,
    );
    assert(hasOwn(capability, "surface"), `${capability.id} must explicitly declare surface (string or null)`);
    assert(
      capability.surface === null || nonEmptyString(capability.surface),
      `${capability.id} surface must be a non-empty string or null`,
    );
    assert(nonEmptyString(capability.status), `${capability.id} status must be non-empty`);

    if (hasOwn(capability, "notes")) {
      assert(nonEmptyString(capability.notes), `${capability.id} notes must be non-empty when present`);
    }

    if (capability.status === "present") {
      assert(
        nonEmptyString(capability.surface),
        `${capability.id} status=present requires a discoverable surface or contract/test reference`,
      );
    }

    if (capability.classification === "deferred_disabled") {
      assert.notEqual(capability.status, "present", `${capability.id} deferred_disabled capability cannot be status=present`);
    }

    if (
      capability.classification === "admin_manageable" &&
      capability.status === "present"
    ) {
      assert(nonEmptyString(capability.surface), `${capability.id} admin_manageable present capability requires a surface`);
    }
  }

  return {
    capabilityCount: matrix.capabilities.length,
    classificationCount: matrix.classifications.length,
  };
}
