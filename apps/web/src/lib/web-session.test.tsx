import assert from "node:assert/strict";
import test from "node:test";
import {
  readWebSessionToken,
  sanitizeAuthEnvelope,
  webSessionClearCookie,
  webSessionSetCookie,
} from "./web-session";

test("opaque backend token is retained only in an HttpOnly same-origin cookie", () => {
  const cookie = webSessionSetCookie("opaque-production-session-token", "2030-01-01T00:00:00Z");
  assert.match(cookie, /HttpOnly/);
  assert.match(cookie, /SameSite=Lax/);
  assert.match(cookie, /Path=\//);
  assert.equal(readWebSessionToken(`theme=light; ${cookie.split(";")[0]}`), "opaque-production-session-token");
  assert.match(webSessionClearCookie(), /Max-Age=0/);
});

test("auth proxy strips bearer material before browser JSON is returned", () => {
  const source = JSON.stringify({
    data: {
      account: { id: "01TEST", email: "student@example.test" },
      access_token: "opaque-production-session-token",
      token_type: "Bearer",
      session: { expires_at: "2030-01-01T00:00:00Z" },
    },
    meta: { request_id: "request" },
  });
  const sanitized = sanitizeAuthEnvelope(source);
  assert.equal(sanitized.accessToken, "opaque-production-session-token");
  assert.equal(sanitized.expiresAt, "2030-01-01T00:00:00Z");
  assert.doesNotMatch(sanitized.body, /opaque-production-session-token|access_token|token_type|Bearer/);
});

test("malformed or undersized session cookies fail closed", () => {
  assert.equal(readWebSessionToken(null), null);
  assert.equal(readWebSessionToken("modrik_web_session=short"), null);
  assert.equal(readWebSessionToken("modrik_web_session=%E0%A4%A"), null);
});
