export const WEB_SESSION_COOKIE = "modrik_web_session";

function decodeCookieValue(value: string): string {
  try {
    return decodeURIComponent(value);
  } catch {
    return "";
  }
}

export function readWebSessionToken(cookieHeader: string | null): string | null {
  if (!cookieHeader) return null;

  for (const pair of cookieHeader.split(";")) {
    const separator = pair.indexOf("=");
    if (separator < 0) continue;
    const name = pair.slice(0, separator).trim();
    if (name !== WEB_SESSION_COOKIE) continue;
    const token = decodeCookieValue(pair.slice(separator + 1).trim());
    return token.length >= 16 ? token : null;
  }

  return null;
}

function cookieSecurityAttribute(): string {
  return process.env.NODE_ENV === "production" ? "; Secure" : "";
}

export function webSessionSetCookie(token: string, expiresAt?: string | null): string {
  const expires = expiresAt ? new Date(expiresAt) : null;
  const expiryAttribute = expires && Number.isFinite(expires.getTime()) ? `; Expires=${expires.toUTCString()}` : "";

  return `${WEB_SESSION_COOKIE}=${encodeURIComponent(token)}; Path=/; HttpOnly; SameSite=Lax${expiryAttribute}${cookieSecurityAttribute()}`;
}

export function webSessionClearCookie(): string {
  return `${WEB_SESSION_COOKIE}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0${cookieSecurityAttribute()}`;
}

function expectedBrowserOrigin(request: Request): string | null {
  const forwardedHost = request.headers.get("x-forwarded-host");
  const host = forwardedHost ?? request.headers.get("host");
  if (!host) return new URL(request.url).origin;

  const forwardedProtocol = request.headers.get("x-forwarded-proto")?.split(",")[0]?.trim();
  const protocol = forwardedProtocol || new URL(request.url).protocol.replace(":", "");
  return `${protocol}://${host.split(",")[0]?.trim()}`;
}

export function isSameOriginMutation(request: Request): boolean {
  if (["GET", "HEAD", "OPTIONS"].includes(request.method.toUpperCase())) return true;

  const fetchSite = request.headers.get("sec-fetch-site");
  if (fetchSite && fetchSite !== "same-origin" && fetchSite !== "none") return false;

  const origin = request.headers.get("origin");
  if (!origin) return false;

  try {
    return new URL(origin).origin === expectedBrowserOrigin(request);
  } catch {
    return false;
  }
}

export type SanitizedAuthEnvelope = {
  body: string;
  accessToken: string | null;
  expiresAt: string | null;
};

export function sanitizeAuthEnvelope(body: string): SanitizedAuthEnvelope {
  if (!body) return { body, accessToken: null, expiresAt: null };

  try {
    const parsed = JSON.parse(body) as {
      data?: {
        access_token?: unknown;
        token_type?: unknown;
        session?: { expires_at?: unknown };
      };
    };
    const token = typeof parsed.data?.access_token === "string" ? parsed.data.access_token : null;
    const expiresAt = typeof parsed.data?.session?.expires_at === "string" ? parsed.data.session.expires_at : null;
    if (!token || !parsed.data) return { body, accessToken: null, expiresAt: null };

    delete parsed.data.access_token;
    delete parsed.data.token_type;

    return { body: JSON.stringify(parsed), accessToken: token, expiresAt };
  } catch {
    return { body, accessToken: null, expiresAt: null };
  }
}
