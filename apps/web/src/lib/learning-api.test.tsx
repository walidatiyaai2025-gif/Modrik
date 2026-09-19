import assert from "node:assert/strict";
import test from "node:test";
import { learningApi, type Attempt } from "./learning-api";

const attemptFixture: Attempt = {
  id: "01J00000000000000000000020",
  academic_context_id: "01J00000000000000000000010",
  quiz_id: "01J00000000000000000000004",
  status: "in_progress",
  blueprint_version: 1,
  ordering_algorithm: "modrik-fy-v1",
  started_at: "2026-08-20T12:00:00Z",
  completed_at: null,
  archived_at: null,
  questions: [],
};

test("assessment requests never send a client seed/order and resume reads persisted authority", async () => {
  const originalFetch = globalThis.fetch;
  const calls: Array<{ url: string; init: RequestInit | undefined }> = [];

  globalThis.fetch = async (input, init) => {
    calls.push({ url: String(input), init });
    return Response.json({
      data: attemptFixture,
      meta: { request_id: "01J00000000000000000000090" },
    });
  };

  try {
    await learningApi.startAttempt(attemptFixture.quiz_id, "web-test-idempotency-key");
    await learningApi.attempt(attemptFixture.id);
  } finally {
    globalThis.fetch = originalFetch;
  }

  assert.equal(calls.length, 2);
  assert.equal(calls[0]?.url, "/api/learning/attempts");
  assert.equal(calls[0]?.init?.method, "POST");
  const startBody = JSON.parse(String(calls[0]?.init?.body));
  assert.deepEqual(startBody, { quiz_id: attemptFixture.quiz_id });
  assert.equal("seed" in startBody, false);
  assert.equal("question_order" in startBody, false);
  assert.equal("questions" in startBody, false);
  assert.equal(new Headers(calls[0]?.init?.headers).get("Idempotency-Key"), "web-test-idempotency-key");

  assert.equal(calls[1]?.url, `/api/learning/attempts/${attemptFixture.id}`);
  assert.equal(calls[1]?.init?.method, undefined);
  assert.equal(calls[1]?.init?.body, undefined);
});


test("numeric assessment answers are sent to the Backend as JSON numbers", async () => {
  const originalFetch = globalThis.fetch;
  const calls: Array<{ url: string; init: RequestInit | undefined }> = [];

  globalThis.fetch = async (input, init) => {
    calls.push({ url: String(input), init });
    return Response.json({
      data: {
        revision: 1,
        value: 12.5,
        answered_at: "2026-09-18T10:00:00Z",
      },
      meta: { request_id: "01J00000000000000000000091" },
    });
  };

  try {
    const saved = await learningApi.answer(
      attemptFixture.id,
      "01J00000000000000000000021",
      0,
      12.5,
      "web-numeric-answer-key",
    );
    assert.equal(saved.value, 12.5);
  } finally {
    globalThis.fetch = originalFetch;
  }

  const request = calls[0];
  assert.ok(request);
  assert.equal(request.url, `/api/learning/attempts/${attemptFixture.id}/answers/01J00000000000000000000021`);
  assert.equal(request.init?.method, "PUT");
  const body = JSON.parse(String(request.init?.body)) as { expected_revision: number; value: unknown };
  assert.equal(body.expected_revision, 0);
  assert.equal(body.value, 12.5);
  assert.equal(typeof body.value, "number");
  assert.equal(new Headers(request.init?.headers).get("Idempotency-Key"), "web-numeric-answer-key");
});


test("multi-select and boolean answers preserve Backend JSON types", async () => {
  const originalFetch = globalThis.fetch;
  const calls: Array<{ url: string; init: RequestInit | undefined }> = [];

  globalThis.fetch = async (input, init) => {
    calls.push({ url: String(input), init });
    const body = JSON.parse(String(init?.body)) as { value: unknown };
    return Response.json({
      data: {
        revision: 1,
        value: body.value,
        answered_at: "2026-09-19T20:00:00Z",
      },
      meta: { request_id: "01J00000000000000000000092" },
    });
  };

  try {
    const selected = await learningApi.answer(
      attemptFixture.id,
      "01J00000000000000000000022",
      0,
      ["option-a", "option-c"],
      "web-multi-select-answer-key",
    );
    assert.deepEqual(selected.value, ["option-a", "option-c"]);

    const boolean = await learningApi.answer(
      attemptFixture.id,
      "01J00000000000000000000023",
      0,
      false,
      "web-boolean-answer-key",
    );
    assert.equal(boolean.value, false);
  } finally {
    globalThis.fetch = originalFetch;
  }

  const multiBody = JSON.parse(String(calls[0]?.init?.body)) as { value: unknown };
  assert.deepEqual(multiBody.value, ["option-a", "option-c"]);
  assert.ok(Array.isArray(multiBody.value));

  const booleanBody = JSON.parse(String(calls[1]?.init?.body)) as { value: unknown };
  assert.equal(booleanBody.value, false);
  assert.equal(typeof booleanBody.value, "boolean");
});
