import assert from "node:assert/strict";
import { writeFile } from "node:fs/promises";
import { GET as authGet } from "../src/app/api/auth/[...path]/route";
import { GET as learningGet } from "../src/app/api/learning/[...path]/route";
import {
  clearRuntimeDiagnostics,
  configureRuntimeDiagnostics,
  diagnosticFetch,
  getRuntimeDiagnosticSnapshot,
  recordBrowserException,
  serializeRuntimeDiagnosticBundle,
  setRuntimeDiagnosticContext,
} from "../src/lib/runtime-diagnostics";

const mainSha = process.env.ACCEPTANCE_MAIN_SHA ?? "unknown";
const backendBase = (process.env.MODRIK_API_BASE_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");
const evidencePath = process.env.OBS_WEB_EVIDENCE ?? "/tmp/modrik-observability-web.json";
const fixtureBearer = process.env.MODRIK_FIXTURE_BEARER_TOKEN ?? "SENTINEL_BEARER_101_FIXTURE_ONLY";

process.env.MODRIK_FIXTURE_MODE = "true";
process.env.MODRIK_FIXTURE_BEARER_TOKEN = fixtureBearer;
process.env.MODRIK_API_BASE_URL = backendBase;

const sentinels = [
  fixtureBearer,
  "SENTINEL_COOKIE_101_FIXTURE_ONLY",
  "SENTINEL_PASSWORD_101_FIXTURE_ONLY",
  "SENTINEL_PROVIDER_SECRET_101_FIXTURE_ONLY",
  "SENTINEL_LEARNER_ANSWER_101_FIXTURE_ONLY",
  "SENTINEL_QUESTION_TEXT_101_FIXTURE_ONLY",
  "sentinel.person.101@example.test",
  "SENTINEL_NAME_101_FIXTURE_ONLY",
];

type UpstreamEvidence = {
  status: number;
  body: string;
  correlationId: string | null;
};

const upstreamByRequestedCorrelation = new Map<string, UpstreamEvidence>();
let backendRequestCount = 0;
const realFetch = globalThis.fetch;
const browserOrigin = "http://web.fixture";
const backendOrigin = new URL(backendBase).origin;

function routeContext(path: string[]) {
  return { params: Promise.resolve({ path }) };
}

function requestUrl(input: RequestInfo | URL): URL {
  if (input instanceof Request) return new URL(input.url);
  return new URL(String(input));
}

function requestHeaders(input: RequestInfo | URL, init?: RequestInit): Headers {
  if (init?.headers) return new Headers(init.headers);
  if (input instanceof Request) return new Headers(input.headers);
  return new Headers();
}

async function browserRoute(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const request = input instanceof Request ? new Request(input, init) : new Request(input, init);
  const url = new URL(request.url);
  const segments = url.pathname.split("/").filter(Boolean);
  const apiIndex = segments.indexOf("api");
  const surface = segments[apiIndex + 1];
  const path = segments.slice(apiIndex + 2);
  assert.ok(path.length > 0, `missing BFF path for ${url.pathname}`);

  if (surface === "learning") return learningGet(request, routeContext(path));
  if (surface === "auth") return authGet(request, routeContext(path));
  throw new Error(`Unexpected browser fixture route: ${url.pathname}`);
}

globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
  const url = requestUrl(input);
  if (url.origin === browserOrigin) return browserRoute(input, init);
  if (url.origin === "http://timeout.fixture") throw new DOMException("synthetic timeout", "AbortError");

  const headers = requestHeaders(input, init);
  const requestedCorrelation = headers.get("X-Correlation-ID");
  const response = await realFetch(input, init);
  if (url.origin === backendOrigin) {
    backendRequestCount += 1;
    if (requestedCorrelation) {
      upstreamByRequestedCorrelation.set(requestedCorrelation, {
        status: response.status,
        body: await response.clone().text(),
        correlationId: response.headers.get("X-Correlation-ID"),
      });
    }
  }
  return response;
};

function finalEvent(operation: string) {
  const event = [...getRuntimeDiagnosticSnapshot()]
    .reverse()
    .find((candidate) => candidate.operation === operation && candidate.status !== null);
  assert.ok(event, `missing final Web diagnostic event for ${operation}`);
  return event;
}

async function runServerCase(
  operation: string,
  url: string,
  expectedStatus: number,
  init?: RequestInit,
) {
  const response = await diagnosticFetch(operation, url, init);
  const body = await response.clone().text();
  const event = finalEvent(operation);
  assert.equal(response.status, expectedStatus);
  assert.equal(event.status, expectedStatus);
  assert.ok(event.correlationId);
  assert.equal(event.supportReference, event.correlationId);
  assert.equal(response.headers.get("X-Correlation-ID"), event.correlationId);

  const upstream = upstreamByRequestedCorrelation.get(event.correlationId!);
  assert.ok(upstream, `missing live Backend upstream evidence for ${operation}`);
  assert.equal(upstream.status, response.status, `${operation}: BFF status changed`);
  assert.equal(upstream.body, body, `${operation}: BFF changed Backend response body`);
  assert.equal(upstream.correlationId, event.correlationId, `${operation}: Backend correlation differs`);

  const payload = body ? (JSON.parse(body) as Record<string, unknown>) : {};
  if (expectedStatus >= 400) {
    assert.equal(payload.status, expectedStatus);
    assert.equal(payload.request_id, event.correlationId);
    assert.equal(typeof payload.code, "string");
    assert.ok(String(payload.code).length > 0);
  }
  for (const sentinel of sentinels) assert.equal(body.includes(sentinel), false, `${operation}: sentinel in Backend/BFF body`);

  return {
    correlation_id: event.correlationId,
    support_reference: event.supportReference,
    status: response.status,
    code: typeof payload.code === "string" ? payload.code : null,
    rfc9457_body_preserved: upstream.body === body && upstream.status === response.status,
  };
}

try {
  configureRuntimeDiagnostics(true, {
    environment: "fixture-acceptance",
    build: "slot7",
    commit: mainSha,
  });
  clearRuntimeDiagnostics();
  setRuntimeDiagnosticContext({
    route: "/learning/acceptance",
    locale: "en",
    direction: "ltr",
    online: true,
  });

  // Seed every privacy sentinel through the browser exception input. Only the safe
  // error class/category may survive; the raw message must never enter diagnostics.
  recordBrowserException("react", new Error(sentinels.join(" | ")));

  const missingLessonId = `01J${"9".repeat(23)}`;
  const learningFailure = await runServerCase(
    "learning:lesson",
    `${browserOrigin}/api/learning/lessons/${missingLessonId}`,
    404,
  );

  const authFailure = await runServerCase(
    "auth:session",
    `${browserOrigin}/api/auth/session`,
    401,
    { headers: { Cookie: `modrik_web_session=${sentinels[1]}` } },
  );
  const authResponse = await authGet(
    new Request(`${browserOrigin}/api/auth/session`, {
      headers: {
        Cookie: `modrik_web_session=${sentinels[1]}`,
        "X-Correlation-ID": authFailure.correlation_id,
      },
    }),
    routeContext(["session"]),
  );
  assert.match(authResponse.headers.get("Set-Cookie") ?? "", /modrik_web_session=;/);
  assert.match(authResponse.headers.get("Set-Cookie") ?? "", /Max-Age=0/);

  const backendCountBeforeTimeout = backendRequestCount;
  await assert.rejects(
    diagnosticFetch("learning:timeout", "http://timeout.fixture/learning"),
  );
  const timeoutEvent = [...getRuntimeDiagnosticSnapshot()]
    .reverse()
    .find((candidate) => candidate.operation === "learning:timeout" && candidate.category === "timeout");
  assert.ok(timeoutEvent);
  assert.equal(timeoutEvent.status, null);
  assert.equal(timeoutEvent.errorCode, "CLIENT_TIMEOUT");
  assert.equal(backendRequestCount, backendCountBeforeTimeout, "client timeout fabricated a Backend request");
  const timeoutBackendRequestCountDelta = backendRequestCount - backendCountBeforeTimeout;

  const success = await runServerCase(
    "learning:success",
    `${browserOrigin}/api/learning/lessons/01J00000000000000000000003`,
    200,
  );

  const serialized = serializeRuntimeDiagnosticBundle();
  for (const sentinel of sentinels) {
    assert.equal(serialized.includes(sentinel), false, `Web diagnostic export leaked ${sentinel}`);
  }

  const evidence = {
    main_sha: mainSha,
    surface: "web",
    cases: {
      A_web_learning_backend_failure: learningFailure,
      B_web_auth_backend_failure: {
        ...authFailure,
        stale_session_cookie_cleared: true,
      },
      D_client_only_timeout: {
        correlation_id: timeoutEvent.correlationId,
        support_reference: timeoutEvent.supportReference,
        category: timeoutEvent.category,
        code: timeoutEvent.errorCode,
        backend_request_count_delta: timeoutBackendRequestCountDelta,
      },
      E_success_control: success,
    },
    privacy_sentinel_count: sentinels.length,
    diagnostic_export_bytes: new TextEncoder().encode(serialized).byteLength,
  };

  const evidenceSerialized = JSON.stringify(evidence, null, 2);
  for (const sentinel of sentinels) assert.equal(evidenceSerialized.includes(sentinel), false);
  await writeFile(evidencePath, evidenceSerialized, "utf8");
  console.log("WEB OBSERVABILITY CORRELATION ACCEPTANCE: PASS");
  console.log(evidenceSerialized);
} finally {
  globalThis.fetch = realFetch;
}
