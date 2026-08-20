import assert from "node:assert/strict";
import test from "node:test";
import { learningApi } from "./learning-api";

const tracks = [
  {
    id: "01J000000000000000000000B2",
    labels: { ar: "المسار الثاني", en: "Second track", fr: "Deuxième parcours" },
  },
  {
    id: "01J000000000000000000000A1",
    labels: { ar: "المسار الأول", en: "First track", fr: "Premier parcours" },
  },
];

test("academic catalogue preserves backend order and exposes only id plus localized labels", async () => {
  const originalFetch = globalThis.fetch;
  const calls: string[] = [];
  globalThis.fetch = async (input) => {
    calls.push(String(input));
    return Response.json({ data: { tracks }, meta: { request_id: "req-1" } });
  };

  try {
    const result = await learningApi.academicTracks();
    assert.deepEqual(result, tracks);
    assert.deepEqual(result.map((track) => track.id), [tracks[0].id, tracks[1].id]);
    assert.deepEqual(Object.keys(result[0]).sort(), ["id", "labels"]);
    assert.deepEqual(Object.keys(result[0].labels).sort(), ["ar", "en", "fr"]);
  } finally {
    globalThis.fetch = originalFetch;
  }

  assert.deepEqual(calls, ["/api/learning/academic-tracks"]);
});

test("activate/reset submit only the chosen opaque academic_track_id with stable idempotency key", async () => {
  const originalFetch = globalThis.fetch;
  const calls: Array<{ url: string; init?: RequestInit }> = [];
  globalThis.fetch = async (input, init) => {
    calls.push({ url: String(input), init });
    return Response.json({
      data: {
        state: "active",
        context_id: "01J000000000000000000000C1",
        academic_track_id: tracks[0].id,
        year_level: "fixture-year",
        activated_at: "2026-08-20T12:00:00Z",
      },
      meta: { request_id: "req-2" },
    });
  };

  try {
    await learningApi.activateAcademicContext(tracks[0].id, "logical-operation-key-001");
    await learningApi.resetAcademicContext(tracks[1].id, "logical-operation-key-002");
  } finally {
    globalThis.fetch = originalFetch;
  }

  assert.equal(calls[0].url, "/api/learning/academic-context/activate");
  assert.deepEqual(JSON.parse(String(calls[0].init?.body)), { academic_track_id: tracks[0].id });
  assert.equal(new Headers(calls[0].init?.headers).get("Idempotency-Key"), "logical-operation-key-001");
  assert.equal(calls[1].url, "/api/learning/academic-context/reset");
  assert.deepEqual(JSON.parse(String(calls[1].init?.body)), { academic_track_id: tracks[1].id });
  assert.equal(new Headers(calls[1].init?.headers).get("Idempotency-Key"), "logical-operation-key-002");
});
