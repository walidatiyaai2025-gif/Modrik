import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { NextRequest } from "next/server";
import { proxy } from "./proxy";
import { BASE_SECURITY_HEADERS, buildContentSecurityPolicy } from "./security-headers";

const representativeRoutes = ["/landing", "/", "/api/learning/session"] as const;
const rootLayoutSource = readFileSync(new URL("./app/layout.tsx", import.meta.url), "utf8");

for (const path of representativeRoutes) {
  test(`enforces browser security headers for ${path}`, () => {
    const response = proxy(new NextRequest(`https://modrik.test${path}`));
    const csp = response.headers.get("Content-Security-Policy");

    assert.ok(csp);
    assert.match(csp, /default-src 'self'/);
    assert.match(csp, /script-src 'self' 'nonce-[^']+' 'strict-dynamic'/);
    assert.match(csp, /style-src 'self' 'nonce-[^']+'/);
    assert.match(csp, /frame-ancestors 'none'/);
    assert.match(csp, /object-src 'none'/);
    assert.doesNotMatch(csp, /'unsafe-inline'/);
    assert.doesNotMatch(csp, /'unsafe-eval'/);

    for (const [name, value] of Object.entries(BASE_SECURITY_HEADERS)) {
      assert.equal(response.headers.get(name), value);
    }

    assert.equal(response.headers.get("Strict-Transport-Security"), null);
  });
}

test("generates a fresh CSP nonce for each request", () => {
  const first = proxy(new NextRequest("https://modrik.test/"));
  const second = proxy(new NextRequest("https://modrik.test/"));

  assert.notEqual(
    first.headers.get("Content-Security-Policy"),
    second.headers.get("Content-Security-Policy"),
  );
});

test("keeps the root render request-bound for per-request CSP nonces", () => {
  assert.match(rootLayoutSource, /import\s*{\s*headers\s*}\s*from\s*"next\/headers"/);
  assert.match(rootLayoutSource, /export default async function RootLayout/);
  assert.match(rootLayoutSource, /await headers\(\)/);
});

test("development allowances do not leak into the production CSP", () => {
  const production = buildContentSecurityPolicy("production-nonce", false);
  const development = buildContentSecurityPolicy("development-nonce", true);

  assert.doesNotMatch(production, /'unsafe-inline'|'unsafe-eval'/);
  assert.match(development, /script-src[^;]*'unsafe-eval'/);
  assert.match(development, /style-src[^;]*'unsafe-inline'/);
});
