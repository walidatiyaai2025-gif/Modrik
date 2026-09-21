import assert from "node:assert/strict";
import test from "node:test";

import { academicContextRecovery } from "./academic-track-selector";

test("academic-context conflict codes reconcile authoritative Backend state", () => {
  for (const code of [
    "ACADEMIC_CONTEXT_RESET_REQUIRED",
    "ACADEMIC_CONTEXT_ALREADY_ACTIVE",
    "ACADEMIC_CONTEXT_ONBOARDING_REQUIRED",
    "ACADEMIC_CONTEXT_UNCHANGED",
  ]) {
    assert.equal(academicContextRecovery(code), "reconcile_context", code);
  }
});

test("stale track removal refreshes the published catalogue", () => {
  assert.equal(academicContextRecovery("RESOURCE_NOT_FOUND"), "reload_catalogue");
});

test("unknown and server failures remain explicit failures", () => {
  assert.equal(academicContextRecovery("LEARNING_SERVICE_UNAVAILABLE"), "none");
  assert.equal(academicContextRecovery("VALIDATION_FAILED"), "none");
  assert.equal(academicContextRecovery("ACADEMIC_RESET_REQUIRED"), "none");
});
