import assert from "node:assert/strict";
import test from "node:test";
import { GET } from "./app/api/learning/[...path]/route";

const context = { params: Promise.resolve({ path: ["session"] }) };

test("learning BFF ignores legacy fixture environment and fails closed without the session cookie", async () => {
  const originalMode = process.env.MODRIK_FIXTURE_MODE;
  const originalToken = process.env.MODRIK_FIXTURE_BEARER_TOKEN;
  const originalFetch = global.fetch;
  let upstreamCalled = false;

  process.env.MODRIK_FIXTURE_MODE = "true";
  process.env.MODRIK_FIXTURE_BEARER_TOKEN = "legacy-fixture-token-that-must-never-authorize";
  global.fetch = async () => {
    upstreamCalled = true;
    throw new Error("upstream must not be called without a real Web session");
  };

  try {
    const response = await GET(new Request("http://localhost/api/learning/session"), context);
    const payload = (await response.json()) as { code?: string };

    assert.equal(response.status, 401);
    assert.equal(payload.code, "AUTHENTICATION_REQUIRED");
    assert.equal(upstreamCalled, false);
  } finally {
    if (originalMode === undefined) delete process.env.MODRIK_FIXTURE_MODE;
    else process.env.MODRIK_FIXTURE_MODE = originalMode;
    if (originalToken === undefined) delete process.env.MODRIK_FIXTURE_BEARER_TOKEN;
    else process.env.MODRIK_FIXTURE_BEARER_TOKEN = originalToken;
    global.fetch = originalFetch;
  }
});

test("learning BFF forwards only the HttpOnly Web session token as Backend bearer authority", async () => {
  const originalFetch = global.fetch;
  let authorization: string | null = null;

  global.fetch = async (_input, init) => {
    authorization = new Headers(init?.headers).get("authorization");
    return Response.json({ data: { user_id: "01J00000000000000000000001" } });
  };

  try {
    const response = await GET(
      new Request("http://localhost/api/learning/session", {
        headers: { Cookie: "modrik_web_session=real-session-token-1234567890" },
      }),
      context,
    );

    assert.equal(response.status, 200);
    assert.equal(authorization, "Bearer real-session-token-1234567890");
  } finally {
    global.fetch = originalFetch;
  }
});
