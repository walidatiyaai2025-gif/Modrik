import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import { createCorrelationId, validCorrelationId } from "./diagnostic-correlation";
import { learningApi } from "./learning-api";
import { RuntimeInspectorEventList } from "./runtime-inspector-event-list";
import { resolveRuntimeInspectorConfig } from "./runtime-inspector-config";
import {
  RUNTIME_DIAGNOSTIC_BUFFER_LIMIT,
  RUNTIME_DIAGNOSTIC_BYTE_LIMIT,
  clearRuntimeDiagnostics,
  configureRuntimeDiagnostics,
  diagnosticFetch,
  getRuntimeDiagnosticSnapshot,
  recordBrowserException,
  recordRuntimeDiagnostic,
  serializeRuntimeDiagnosticBundle,
  setRuntimeDiagnosticContext,
} from "./runtime-diagnostics";

const sentinels = {
  bearer: "Bearer fake-secret-token.abc.def",
  cookie: "modrik_web_session=fake-cookie-secret",
  password: "FakePassword-DoNotCapture-123!",
  provider: "fake-provider-secret-never-log",
  answer: "LEARNER_ANSWER_SENTINEL_7391",
  question: "QUESTION_TEXT_SENTINEL_8422",
  email: "learner.sentinel@example.test",
  name: "Learner Name Sentinel 5521",
};

const timelineLabels = {
  timeline: "Recent diagnostic timeline",
  empty: "No diagnostic events captured.",
  status: "Result",
  support: "Support reference",
  copyCorrelation: "Copy correlation ID",
};

function eventInput(index: number) {
  return {
    severity: "info" as const,
    category: "runtime" as const,
    operation: "runtime:heartbeat",
    correlationId: createCorrelationId(),
    supportReference: null,
    resultClass: "ok",
    status: 200,
    errorCode: null,
    durationMs: index,
    retryState: "none" as const,
  };
}

test("runtime diagnostics are bounded, clearable and never capture exception messages", () => {
  configureRuntimeDiagnostics(true, { environment: "pilot", build: "web-1", commit: "abc123" });
  clearRuntimeDiagnostics();

  for (let index = 0; index < RUNTIME_DIAGNOSTIC_BUFFER_LIMIT + 7; index += 1) {
    recordRuntimeDiagnostic(eventInput(index));
  }
  recordBrowserException("error", new Error(Object.values(sentinels).join(" | ")));

  const snapshot = getRuntimeDiagnosticSnapshot();
  assert.equal(snapshot.length, RUNTIME_DIAGNOSTIC_BUFFER_LIMIT);
  const serialized = serializeRuntimeDiagnosticBundle();
  for (const sentinel of Object.values(sentinels)) assert.equal(serialized.includes(sentinel), false);
  assert.match(serialized, /CLIENT_UI_EXCEPTION/);

  clearRuntimeDiagnostics();
  assert.equal(getRuntimeDiagnosticSnapshot().length, 0);
  configureRuntimeDiagnostics(false);
});

test("runtime diagnostics enforce a deterministic oldest-first byte budget", () => {
  configureRuntimeDiagnostics(true, { environment: "pilot", build: "web-byte-budget", commit: "abc123" });
  clearRuntimeDiagnostics();
  setRuntimeDiagnosticContext({ route: `/${["r".repeat(63), "s".repeat(63), "t".repeat(63), "u".repeat(63)].join("/")}` });

  for (let index = 0; index < RUNTIME_DIAGNOSTIC_BUFFER_LIMIT; index += 1) {
    recordRuntimeDiagnostic({
      severity: "error",
      category: "runtime",
      operation: `runtime:${"x".repeat(72)}`,
      correlationId: createCorrelationId(),
      supportReference: `S${"R".repeat(126)}`,
      resultClass: "client_error",
      status: 500,
      errorCode: "E".repeat(80),
      durationMs: index,
      retryState: "retryable",
    });
  }

  const snapshot = getRuntimeDiagnosticSnapshot();
  assert.ok(snapshot.length < RUNTIME_DIAGNOSTIC_BUFFER_LIMIT);
  assert.ok((snapshot[0]?.durationMs ?? 0) > 0);
  assert.equal(snapshot.at(-1)?.durationMs, RUNTIME_DIAGNOSTIC_BUFFER_LIMIT - 1);

  const serialized = serializeRuntimeDiagnosticBundle();
  assert.ok(new TextEncoder().encode(serialized).byteLength <= RUNTIME_DIAGNOSTIC_BYTE_LIMIT);
  const bundle = JSON.parse(serialized) as { events: Array<{ durationMs: number | null }> };
  assert.ok((bundle.events[0]?.durationMs ?? 0) > 0);
  assert.equal(bundle.events.at(-1)?.durationMs, RUNTIME_DIAGNOSTIC_BUFFER_LIMIT - 1);

  configureRuntimeDiagnostics(false);
});

test("diagnostic fetch propagates a correlation ID but records only allowlisted problem metadata in bundle and DOM", async () => {
  configureRuntimeDiagnostics(true, { environment: "staging", build: "web-2", commit: "def456" });
  clearRuntimeDiagnostics();
  const originalFetch = globalThis.fetch;
  let forwardedHeaders = new Headers();
  const backendCorrelation = "7bc8a04c-2ac4-4f7e-a242-4c1be37e8d18";

  globalThis.fetch = async (_input, init) => {
    forwardedHeaders = new Headers(init?.headers);
    return Response.json(
      {
        type: "https://modrik.org/problems/authentication_required",
        status: 401,
        code: "AUTHENTICATION_REQUIRED",
        detail: Object.values(sentinels).join(" | "),
        request_id: backendCorrelation,
        retryable: false,
      },
      {
        status: 401,
        headers: {
          "Content-Type": "application/problem+json",
          "X-Correlation-ID": backendCorrelation,
        },
      },
    );
  };

  try {
    const response = await diagnosticFetch("auth:login", "/api/auth/login", {
      method: "POST",
      headers: {
        Authorization: sentinels.bearer,
        Cookie: sentinels.cookie,
        "Content-Type": "application/json",
      },
      body: JSON.stringify(sentinels),
    });
    assert.equal(response.status, 401);
    assert.ok(validCorrelationId(forwardedHeaders.get("X-Correlation-ID")));

    const serialized = serializeRuntimeDiagnosticBundle();
    assert.match(serialized, /AUTHENTICATION_REQUIRED/);
    assert.match(serialized, new RegExp(backendCorrelation));
    for (const sentinel of Object.values(sentinels)) assert.equal(serialized.includes(sentinel), false);

    const markup = renderToStaticMarkup(
      <RuntimeInspectorEventList events={getRuntimeDiagnosticSnapshot()} labels={timelineLabels} />,
    );
    assert.match(markup, /auth:login/);
    assert.match(markup, new RegExp(backendCorrelation));
    for (const sentinel of Object.values(sentinels)) assert.equal(markup.includes(sentinel), false);
  } finally {
    globalThis.fetch = originalFetch;
    configureRuntimeDiagnostics(false);
  }
});

test("learning diagnostics use stable operation names instead of learner-linked resource IDs", async () => {
  configureRuntimeDiagnostics(true, { environment: "pilot", build: "web-3", commit: "ghi789" });
  clearRuntimeDiagnostics();
  const originalFetch = globalThis.fetch;
  const learnerLinkedId = "LEARNER_LINKED_RESOURCE_ID_7791";

  globalThis.fetch = async () =>
    Response.json({
      data: {
        id: learnerLinkedId,
        curriculum_node_id: "node-fixture",
        content_version: 1,
        title: { en: "Fixture lesson" },
        practice_quiz_id: "quiz-fixture",
        blocks: [],
      },
      meta: { request_id: createCorrelationId() },
    });

  try {
    const lesson = await learningApi.lesson(learnerLinkedId);
    assert.equal(lesson.id, learnerLinkedId);
    const serialized = serializeRuntimeDiagnosticBundle();
    assert.equal(serialized.includes(learnerLinkedId), false);
    assert.match(serialized, /learning:lesson/);
  } finally {
    globalThis.fetch = originalFetch;
    configureRuntimeDiagnostics(false);
  }
});

test("correlation IDs reject arbitrary secret-shaped strings", () => {
  for (const sentinel of Object.values(sentinels)) assert.equal(validCorrelationId(sentinel), null);
  assert.ok(validCorrelationId(createCorrelationId()));
});

test("runtime inspector is explicit non-production only and sanitizes build labels", () => {
  assert.equal(
    resolveRuntimeInspectorConfig({
      MODRIK_RUNTIME_INSPECTOR_ENABLED: "true",
      MODRIK_RUNTIME_ENVIRONMENT: "production",
    }).enabled,
    false,
  );
  const pilot = resolveRuntimeInspectorConfig({
    MODRIK_RUNTIME_INSPECTOR_ENABLED: "true",
    MODRIK_RUNTIME_ENVIRONMENT: "pilot",
    MODRIK_BUILD_VERSION: "pilot-build-42",
    MODRIK_GIT_SHA: "abc1234",
  });
  assert.equal(pilot.enabled, true);
  assert.equal(pilot.environment, "pilot");
  assert.equal(pilot.build, "pilot-build-42");

  const sanitized = resolveRuntimeInspectorConfig({
    MODRIK_RUNTIME_INSPECTOR_ENABLED: "true",
    MODRIK_RUNTIME_ENVIRONMENT: "pilot",
    MODRIK_BUILD_VERSION: sentinels.email,
    MODRIK_GIT_SHA: sentinels.password,
  });
  assert.equal(sanitized.build, "unknown");
  assert.equal(sanitized.commit, "unknown");
});
