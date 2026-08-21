const BASE_SECURITY_HEADERS = {
  "Referrer-Policy": "strict-origin-when-cross-origin",
  "X-Content-Type-Options": "nosniff",
  "X-Frame-Options": "DENY",
  "Permissions-Policy": [
    "accelerometer=()",
    "camera=()",
    "geolocation=()",
    "gyroscope=()",
    "magnetometer=()",
    "microphone=()",
    "payment=()",
    "usb=()",
  ].join(", "),
} as const;

export function buildContentSecurityPolicy(nonce: string, isDevelopment: boolean): string {
  const scriptDevelopmentAllowance = isDevelopment ? " 'unsafe-eval'" : "";
  const styleDevelopmentAllowance = isDevelopment ? " 'unsafe-inline'" : ` 'nonce-${nonce}'`;

  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${scriptDevelopmentAllowance}`,
    `style-src 'self'${styleDevelopmentAllowance}`,
    "img-src 'self' blob: data:",
    "font-src 'self'",
    "connect-src 'self'",
    "media-src 'self'",
    "worker-src 'self' blob:",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ].join("; ");
}

export function applyBrowserSecurityHeaders(headers: Headers, contentSecurityPolicy: string): void {
  headers.set("Content-Security-Policy", contentSecurityPolicy);

  for (const [name, value] of Object.entries(BASE_SECURITY_HEADERS)) {
    headers.set(name, value);
  }
}

export { BASE_SECURITY_HEADERS };
