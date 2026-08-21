import assert from "node:assert/strict";
import test from "node:test";
import { GET as authGet } from "../app/api/auth/[...path]/route";
import { GET as learningGet } from "../app/api/learning/[...path]/route";

const browserCorrelation = "65b28c92-414a-48e9-a0f1-5d14a7cc2048";
const backendCorrelation = "0d9b515b-5bdb-49ff-939f-ecb80bac5995";
const fakeSessionSecret = "opaque-session-secret-never-return";

function routeContext(path: string[]) {
  return { params: Promise.resolve({ path }) };
}

test("Auth BFF forwards browser correlation and returns the Backend replacement without exposing secrets", async () => {
  const originalFetch = globalThis.fetch;
  let forwarded = new Headers();
  globalThis.fetch = async (_input, init) => {
    forwarded = new Headers(init?.headers);
    return Response.json(
      { data: { user_id: "01J000000000000000000000A1", locale: "en", roles: [] }, meta: { request_id: backendCorrelation } },
      { headers: { "X-Correlation-ID": backendCorrelation } },
    );
  };

  try {
    const response = await authGet(
      new Request("https://modrik.org/api/auth/session", {
        headers: { "X-Correlation-ID": browserCorrelation },
      }),
      routeContext(["session"]),
    );
    assert.equal(forwarded.get("X-Correlation-ID"), browserCorrelation);
    assert.equal(response.headers.get("X-Correlation-ID"), backendCorrelation);
    assert.equal((await response.text()).includes(fakeSessionSecret), false);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("Learning BFF preserves #80 stale-session clearing while propagating the same diagnostic correlation", async () => {
  const originalFetch = globalThis.fetch;
  let forwarded = new Headers();
  const problem = JSON.stringify({
    type: "https://modrik.org/problems/authentication_required",
    title: "Authentication required",
    status: 401,
    code: "AUTHENTICATION_REQUIRED",
    detail: "Authentication required.",
    request_id: backendCorrelation,
    retryable: false,
  });
  globalThis.fetch = async (_input, init) => {
    forwarded = new Headers(init?.headers);
    return new Response(problem, {
      status: 401,
      headers: {
        "Content-Type": "application/problem+json",
        "X-Correlation-ID": backendCorrelation,
      },
    });
  };

  try {
    const response = await learningGet(
      new Request("https://modrik.org/api/learning/academic-tracks", {
        headers: {
          cookie: `modrik_web_session=${fakeSessionSecret}`,
          "X-Correlation-ID": browserCorrelation,
        },
      }),
      routeContext(["academic-tracks"]),
    );

    assert.equal(forwarded.get("X-Correlation-ID"), browserCorrelation);
    assert.equal(forwarded.get("Authorization"), `Bearer ${fakeSessionSecret}`);
    assert.equal(response.headers.get("X-Correlation-ID"), backendCorrelation);
    assert.match(response.headers.get("Set-Cookie") ?? "", /modrik_web_session=;/);
    assert.match(response.headers.get("Set-Cookie") ?? "", /Max-Age=0/);
    const body = await response.text();
    assert.equal(body, problem);
    assert.equal(body.includes(fakeSessionSecret), false);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("BFF replaces an arbitrary inbound correlation string instead of reflecting it", async () => {
  const originalFetch = globalThis.fetch;
  let forwarded = new Headers();
  globalThis.fetch = async (_input, init) => {
    forwarded = new Headers(init?.headers);
    return Response.json({ data: { user_id: "01J000000000000000000000A1", locale: "en", roles: [] }, meta: { request_id: "server" } });
  };

  try {
    const response = await authGet(
      new Request("https://modrik.org/api/auth/session", {
        headers: { "X-Correlation-ID": fakeSessionSecret },
      }),
      routeContext(["session"]),
    );
    assert.notEqual(forwarded.get("X-Correlation-ID"), fakeSessionSecret);
    assert.match(forwarded.get("X-Correlation-ID") ?? "", /^[0-9a-f-]{36}$/i);
    assert.equal(response.headers.get("X-Correlation-ID"), forwarded.get("X-Correlation-ID"));
  } finally {
    globalThis.fetch = originalFetch;
  }
});
