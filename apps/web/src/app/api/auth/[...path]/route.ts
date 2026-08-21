import {
  CORRELATION_HEADER,
  correlationIdForRequest,
  correlationIdFromResponse,
} from "../../../../lib/diagnostic-correlation";
import {
  isSameOriginMutation,
  readWebSessionToken,
  sanitizeAuthEnvelope,
  webSessionClearCookie,
  webSessionSetCookie,
} from "../../../../lib/web-session";

const provider = "(google|apple)";
const allowedPaths = [
  /^session$/,
  /^register$/,
  /^login$/,
  /^email\/verify$/,
  /^email\/verification$/,
  /^password\/recovery$/,
  /^password\/reset$/,
  /^reauthenticate$/,
  /^password$/,
  /^sessions$/,
  /^sessions\/(current|others)$/,
  /^account$/,
  new RegExp(`^providers/${provider}/(login-intents|link-intents|callback)$`),
  new RegExp(`^providers/${provider}$`),
];

type RouteParameters = { params: Promise<{ path: string[] }> };

function problem(status: number, code: string, detail: string, correlationId: string) {
  return Response.json(
    {
      type: `https://modrik.org/problems/${code.toLowerCase()}`,
      title: status === 503 ? "Account service unavailable" : "Request rejected",
      status,
      code,
      detail,
      request_id: correlationId,
      retryable: status >= 500,
    },
    {
      status,
      headers: {
        "Content-Type": "application/problem+json",
        "Cache-Control": "no-store",
        [CORRELATION_HEADER]: correlationId,
      },
    },
  );
}

function upstreamPath(relativePath: string): string {
  return relativePath === "session" ? "/v1/session" : `/v1/auth/${relativePath}`;
}

function shouldClearSession(relativePath: string, method: string, status: number): boolean {
  if (status === 401) return true;
  if (status < 200 || status >= 300) return false;

  return method === "DELETE" && ["sessions/current", "sessions", "account"].includes(relativePath);
}

async function proxy(request: Request, context: RouteParameters) {
  const correlationId = correlationIdForRequest(request);
  const { path } = await context.params;
  const relativePath = path.join("/");
  if (!allowedPaths.some((pattern) => pattern.test(relativePath))) {
    return problem(404, "RESOURCE_NOT_FOUND", "The requested account route is not available.", correlationId);
  }
  if (!isSameOriginMutation(request)) {
    return problem(
      403,
      "CSRF_CHECK_FAILED",
      "The account request did not originate from this MODRIK Web application.",
      correlationId,
    );
  }

  const baseUrl = (process.env.MODRIK_API_BASE_URL ?? "http://localhost:8000").replace(/\/$/, "");
  const sessionToken = readWebSessionToken(request.headers.get("cookie"));
  const headers = new Headers({
    Accept: "application/json, application/problem+json",
    [CORRELATION_HEADER]: correlationId,
  });
  const contentType = request.headers.get("content-type");
  if (contentType) headers.set("Content-Type", contentType);
  if (sessionToken) headers.set("Authorization", `Bearer ${sessionToken}`);

  try {
    const upstream = await fetch(`${baseUrl}${upstreamPath(relativePath)}`, {
      method: request.method,
      headers,
      body: request.method === "GET" || request.method === "HEAD" ? undefined : await request.text(),
      cache: "no-store",
      signal: AbortSignal.timeout(10_000),
    });

    const returnedCorrelationId = correlationIdFromResponse(upstream, correlationId);
    const rawBody = upstream.status === 204 ? "" : await upstream.text();
    const sanitized = upstream.ok ? sanitizeAuthEnvelope(rawBody) : { body: rawBody, accessToken: null, expiresAt: null };
    const responseHeaders = new Headers({
      "Content-Type": upstream.headers.get("content-type") ?? "application/json",
      "Cache-Control": "no-store, private",
      [CORRELATION_HEADER]: returnedCorrelationId,
    });

    if (sanitized.accessToken) {
      responseHeaders.append("Set-Cookie", webSessionSetCookie(sanitized.accessToken, sanitized.expiresAt));
    }
    if (shouldClearSession(relativePath, request.method, upstream.status)) {
      responseHeaders.append("Set-Cookie", webSessionClearCookie());
    }

    return new Response(sanitized.body, {
      status: upstream.status,
      headers: responseHeaders,
    });
  } catch {
    return problem(
      503,
      "AUTH_SERVICE_UNAVAILABLE",
      "The account service could not be reached. Check your connection and retry.",
      correlationId,
    );
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

export function DELETE(request: Request, context: RouteParameters) {
  return proxy(request, context);
}
