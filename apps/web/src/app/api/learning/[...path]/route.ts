import {
  isSameOriginMutation,
  readWebSessionToken,
  webSessionClearCookie,
} from "../../../../lib/web-session";

const ulid = "[0-9A-HJKMNP-TV-Z]{26}";
const allowedPaths = [
  /^session$/,
  /^academic-tracks$/,
  /^academic-context$/,
  /^academic-context\/(activate|reset)$/,
  new RegExp(`^lessons/${ulid}$`),
  /^progress$/,
  /^attempts$/,
  new RegExp(`^attempts/${ulid}$`),
  new RegExp(`^attempts/${ulid}/answers/${ulid}$`),
  new RegExp(`^attempts/${ulid}/submit$`),
];

type RouteParameters = { params: Promise<{ path: string[] }> };

function problem(status: number, code: string, detail: string) {
  return Response.json(
    {
      type: `https://modrik.org/problems/${code.toLowerCase()}`,
      title: status === 503 ? "Learning service unavailable" : "Request rejected",
      status,
      code,
      detail,
      request_id: crypto.randomUUID(),
      retryable: status >= 500,
    },
    { status, headers: { "Content-Type": "application/problem+json", "Cache-Control": "no-store" } },
  );
}

function bearerToken(request: Request): string | null {
  const productionSession = readWebSessionToken(request.headers.get("cookie"));
  if (productionSession) return productionSession;

  if (process.env.MODRIK_FIXTURE_MODE === "true") {
    return process.env.MODRIK_FIXTURE_BEARER_TOKEN ?? null;
  }

  return null;
}

async function proxy(request: Request, context: RouteParameters) {
  const { path } = await context.params;
  const relativePath = path.join("/");
  if (!allowedPaths.some((pattern) => pattern.test(relativePath))) {
    return problem(404, "RESOURCE_NOT_FOUND", "The requested learning route is not available.");
  }
  if (!isSameOriginMutation(request)) {
    return problem(403, "CSRF_CHECK_FAILED", "The learning request did not originate from this MODRIK Web application.");
  }

  const token = bearerToken(request);
  if (!token) {
    return problem(401, "AUTHENTICATION_REQUIRED", "Sign in with a valid account session to continue.");
  }

  const baseUrl = (process.env.MODRIK_API_BASE_URL ?? "http://localhost:8000").replace(/\/$/, "");
  const headers = new Headers({
    Accept: "application/json, application/problem+json",
    Authorization: `Bearer ${token}`,
  });
  const contentType = request.headers.get("content-type");
  const idempotencyKey = request.headers.get("idempotency-key");
  if (contentType) headers.set("Content-Type", contentType);
  if (idempotencyKey) headers.set("Idempotency-Key", idempotencyKey);

  try {
    const upstream = await fetch(`${baseUrl}/v1/${relativePath}`, {
      method: request.method,
      headers,
      body: request.method === "GET" || request.method === "HEAD" ? undefined : await request.text(),
      cache: "no-store",
      signal: AbortSignal.timeout(10_000),
    });
    const responseHeaders = new Headers({
      "Content-Type": upstream.headers.get("content-type") ?? "application/json",
      "Cache-Control": "no-store, private",
    });
    for (const header of ["idempotency-replayed", "location"]) {
      const value = upstream.headers.get(header);
      if (value) responseHeaders.set(header, value);
    }
    if (upstream.status === 401) {
      responseHeaders.append("Set-Cookie", webSessionClearCookie());
    }

    return new Response(await upstream.text(), {
      status: upstream.status,
      headers: responseHeaders,
    });
  } catch {
    return problem(503, "LEARNING_SERVICE_UNAVAILABLE", "The learning service could not be reached. Check the Backend and retry.");
  }
}

export function GET(request: Request, context: RouteParameters) {
  return proxy(request, context);
}

export function POST(request: Request, context: RouteParameters) {
  return proxy(request, context);
}

export function PUT(request: Request, context: RouteParameters) {
  return proxy(request, context);
}
