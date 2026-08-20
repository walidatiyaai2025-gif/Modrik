import assert from "node:assert/strict";
import test from "node:test";
import { AuthApiError, authApi } from "./auth-api";

test("revoked or expired session responses remain an explicit 401 client state", async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => Response.json(
    {
      type: "https://modrik.org/problems/authentication-required",
      title: "Authentication required",
      status: 401,
      code: "AUTHENTICATION_REQUIRED",
      detail: "A valid production account session is required.",
      request_id: "req-test",
      retryable: false,
    },
    { status: 401, headers: { "Content-Type": "application/problem+json" } },
  );

  try {
    await assert.rejects(
      authApi.session(),
      (error: unknown) => error instanceof AuthApiError && error.status === 401 && error.code === "AUTHENTICATION_REQUIRED",
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("email verification consumes only the existing one-time-token contract", async () => {
  const originalFetch = globalThis.fetch;
  let requestUrl = "";
  let requestInit: RequestInit | undefined;
  globalThis.fetch = async (input, init) => {
    requestUrl = String(input);
    requestInit = init;
    return new Response(null, { status: 204 });
  };

  try {
    await authApi.verifyEmail("modrik_v_test_verification_token_value");
    assert.equal(requestUrl, "/api/auth/email/verify");
    assert.equal(requestInit?.method, "POST");
    assert.deepEqual(JSON.parse(String(requestInit?.body)), { token: "modrik_v_test_verification_token_value" });
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("provider login entry point creates only the Backend-owned intent", async () => {
  const originalFetch = globalThis.fetch;
  let requestUrl = "";
  globalThis.fetch = async (input) => {
    requestUrl = String(input);
    return Response.json({
      data: {
        state: "modrik_o_01234567890123456789012345678901",
        nonce: "modrik_n_01234567890123456789012345678901",
        expires_at: "2030-01-01T00:00:00Z",
      },
      meta: { request_id: "req-provider" },
    });
  };

  try {
    const intent = await authApi.providerIntent("google", "login");
    assert.equal(requestUrl, "/api/auth/providers/google/login-intents");
    assert.match(intent.state, /^modrik_o_/);
    assert.equal("client_id" in intent, false);
    assert.equal("client_secret" in intent, false);
  } finally {
    globalThis.fetch = originalFetch;
  }
});
