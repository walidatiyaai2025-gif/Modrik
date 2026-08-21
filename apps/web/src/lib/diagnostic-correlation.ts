export const CORRELATION_HEADER = "X-Correlation-ID";

const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const ulidPattern = /^[0-9A-HJKMNP-TV-Z]{26}$/;

export function validCorrelationId(value: string | null | undefined): string | null {
  if (!value) return null;
  const candidate = value.trim();
  return uuidPattern.test(candidate) || ulidPattern.test(candidate) ? candidate : null;
}

export function createCorrelationId(): string {
  return globalThis.crypto.randomUUID();
}

export function correlationIdForRequest(request: Request): string {
  return validCorrelationId(request.headers.get(CORRELATION_HEADER)) ?? createCorrelationId();
}

export function correlationIdFromResponse(response: Response, fallback: string): string {
  return validCorrelationId(response.headers.get(CORRELATION_HEADER)) ?? fallback;
}
