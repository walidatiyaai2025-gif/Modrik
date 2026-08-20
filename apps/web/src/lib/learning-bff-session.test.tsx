import assert from "node:assert/strict";
import test from "node:test";
import { GET, POST } from "../app/api/learning/[...path]/route";

const sessionToken = "opaque-production-session-token";
const sessionCookie = `modrik_web_session=${sessionToken}`;

type FetchCall = { input: string; init?: RequestInit };

function routeContext(path: string[]) {
  return { params: Promise.resolve({ path }) };
}

function authenticationProblem() {
  return JSON.stringify({
    type: "https://modrik.org/problems/authentication_required",
    title: "Authentication required",
    status: 401,
    code: "AUTHENTICATION_REQUIRED",
    detail: "Authentication required.",
    request_id: "request-401",
    retryable: false,
  });
}

test("Learning BFF clears the stale HttpOnly session cookie on upstream GET 401 without rewriting the problem", async () => {
  const originalFetch = globalThis.fetch;
  const calls: FetchCall[] = [];
  const upstreamBody = authenticationProblem();
  globalThis.fetch = async (input, init) => {
    calls.push({ input: String(input), init });
    return new Response(upstreamBody, {
      status: 401,
      headers: { "Content-Type": "application/problem+json" },
    });
  };

  try {
    const response = await GET(
      new Request("https://modrik.org/api/learning/academic-tracks", {
        headers: { cookie: sessionCookie },
      }),
      routeContext(["academic-tracks"]),
    );

    assert.equal(response.status, 401);
    assert.equal(await response.text(), upstreamBody);
    assert.equal(response.headers.get("Content-Type"), "application/problem+json");
    assert.equal(response.headers.get("Cache-Control"), "no-store, private");
    assert.match(response.headers.get("Set-Cookie") ?? "", /modrik_web_session=;/);
    assert.match(response.headers.get("Set-Cookie") ?? "", /HttpOnly/);
    assert.match(response.headers.get("Set-Cookie") ?? "", /SameSite=Lax/);
    assert.match(response.headers.get("Set-Cookie") ?? "", /Max-Age=0/);
    assert.doesNotMatch(upstreamBody, new RegExp(sessionToken));
    assert.equal(calls.length, 1);
    assert.equal(
      new Headers(calls[0].init?.headers).get("Authorization"),
      `Bearer ${sessionToken}`,
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("Learning BFF clears the stale session cookie on same-origin mutation 401 and preserves request semantics", async () => {
  const originalFetch = globalThis.fetch;
  const calls: FetchCall[] = [];
  const upstreamBody = authenticationProblem();
  globalThis.fetch = async (input, init) => {
    calls.push({ input: String(input), init });
    return new Response(upstreamBody, {
      status: 401,
      headers: { "Content-Type": "application/problem+json" },
    });
  };

  try {
    const body = JSON.stringify({ academic_track_id: "01J000000000000000000000A1" });
    const response = await POST(
      new Request("https://modrik.org/api/learning/academic-context/reset", {
        method: "POST",
        headers: {
          cookie: sessionCookie,
          origin: "https://modrik.org",
          "sec-fetch-site": "same-origin",
          "content-type": "application/json",
          "idempotency-key": "logical-operation-key-001",
        },
        body,
      }),
      routeContext(["academic-context", "reset"]),
    );

    assert.equal(response.status, 401);
    assert.equal(await response.text(), upstreamBody);
    assert.match(response.headers.get("Set-Cookie") ?? "", /Max-Age=0/);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].init?.method, "POST");
    assert.equal(calls[0].init?.body, body);
    const forwardedHeaders = new Headers(calls[0].init?.headers);
    assert.equal(forwardedHeaders.get("Authorization"), `Bearer ${sessionToken}`);
    assert.equal(forwardedHeaders.get("Idempotency-Key"), "logical-operation-key-001");
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("Learning BFF leaves the session cookie intact on non-401 upstream responses", async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () =>
    Response.json({ data: { state: "active" }, meta: { request_id: "request-200" } });

  try {
    const response = await GET(
      new Request("https://modrik.org/api/learning/academic-context", {
        headers: { cookie: sessionCookie },
      }),
      routeContext(["academic-context"]),
    );

    assert.equal(response.status, 200);
    assert.equal(response.headers.get("Set-Cookie"), null);
    assert.doesNotMatch(await response.text(), new RegExp(sessionToken));
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("Learning BFF preserves the existing same-origin CSRF rejection before any upstream call", async () => {
  const originalFetch = globalThis.fetch;
  let upstreamCalled = false;
  globalThis.fetch = async () => {
    upstreamCalled = true;
    return Response.json({ data: {} });
  };

  try {
    const response = await POST(
      new Request("https://modrik.org/api/learning/academic-context/reset", {
        method: "POST",
        headers: {
          cookie: sessionCookie,
          origin: "https://attacker.example",
          "sec-fetch-site": "cross-site",
          "content-type": "application/json",
        },
        body: JSON.stringify({ academic_track_id: "01J000000000000000000000A1" }),
      }),
      routeContext(["academic-context", "reset"]),
    );

    assert.equal(response.status, 403);
    assert.equal(upstreamCalled, false);
    assert.equal(response.headers.get("Set-Cookie"), null);
    const problem = (await response.json()) as { code?: string };
    assert.equal(problem.code, "CSRF_CHECK_FAILED");
  } finally {
    globalThis.fetch = originalFetch;
  }
});
