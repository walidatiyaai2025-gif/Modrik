import {
  CORRELATION_HEADER,
  correlationIdFromResponse,
  createCorrelationId,
  validCorrelationId,
} from "./diagnostic-correlation";

export const RUNTIME_DIAGNOSTIC_BUFFER_LIMIT = 50;
export const RUNTIME_DIAGNOSTIC_BYTE_LIMIT = 32 * 1024;
const storageKey = "modrik_runtime_diagnostics_v1";

export type DiagnosticSeverity = "debug" | "info" | "warn" | "error" | "critical";
export type DiagnosticCategory =
  | "request"
  | "offline"
  | "network"
  | "timeout"
  | "rfc9457"
  | "validation"
  | "ui_exception"
  | "runtime";

export type RuntimeDiagnosticEvent = {
  timestamp: string;
  severity: DiagnosticSeverity;
  surface: "web" | "public";
  category: DiagnosticCategory;
  operation: string;
  correlationId: string | null;
  supportReference: string | null;
  resultClass: string | null;
  status: number | null;
  errorCode: string | null;
  durationMs: number | null;
  route: string | null;
  locale: "ar" | "en" | "fr" | "unknown";
  direction: "rtl" | "ltr" | "unknown";
  online: boolean | null;
  retryState: "none" | "retryable" | "retrying" | null;
};

export type RuntimeDiagnosticContext = {
  environment: string;
  build: string;
  commit: string;
  route: string;
  locale: "ar" | "en" | "fr" | "unknown";
  direction: "rtl" | "ltr" | "unknown";
  online: boolean | null;
  cacheSummary: { status: "unknown" | "available" | "unavailable"; entries: number | null };
  syncSummary: { status: "unknown" | "idle" | "pending" | "error"; pending: number | null };
};

export type RuntimeDiagnosticBundle = {
  schema_version: "modrik.web.runtime-diagnostics.v1";
  generated_at: string;
  context: RuntimeDiagnosticContext;
  events: RuntimeDiagnosticEvent[];
};

type EventInput = Omit<
  RuntimeDiagnosticEvent,
  "timestamp" | "surface" | "route" | "locale" | "direction" | "online"
> & {
  surface?: RuntimeDiagnosticEvent["surface"];
};

type Listener = () => void;

let enabled = false;
let hydrated = false;
let events: RuntimeDiagnosticEvent[] = [];
let context: RuntimeDiagnosticContext = {
  environment: "unknown",
  build: "unknown",
  commit: "unknown",
  route: "/",
  locale: "unknown",
  direction: "unknown",
  online: null,
  cacheSummary: { status: "unknown", entries: null },
  syncSummary: { status: "unknown", pending: null },
};
const listeners = new Set<Listener>();

function utf8ByteLength(value: string): number {
  return new TextEncoder().encode(value).byteLength;
}

function boundEventsByBytes(
  input: RuntimeDiagnosticEvent[],
  serialize: (candidate: RuntimeDiagnosticEvent[]) => string,
): RuntimeDiagnosticEvent[] {
  let bounded = input.slice(-RUNTIME_DIAGNOSTIC_BUFFER_LIMIT);
  while (bounded.length > 0 && utf8ByteLength(serialize(bounded)) > RUNTIME_DIAGNOSTIC_BYTE_LIMIT) {
    bounded = bounded.slice(1);
  }
  return bounded;
}

function safeLabel(value: unknown, fallback = "unknown", max = 80): string {
  if (typeof value !== "string") return fallback;
  const trimmed = value.trim();
  if (!trimmed || trimmed.length > max) return fallback;
  return /^[A-Za-z0-9._:/ -]+$/.test(trimmed) ? trimmed : fallback;
}

function safeRoute(value: unknown): string {
  if (typeof value !== "string" || !value.startsWith("/")) return "/";
  const path = value.split("?", 1)[0].split("#", 1)[0];
  const parts = path.split("/").map((part) => {
    if (!part) return "";
    if (part.length > 64 || part.includes("@")) return "[redacted]";
    return /^[A-Za-z0-9._~%+-]+$/.test(part) ? part : "[redacted]";
  });
  return parts.join("/").slice(0, 256) || "/";
}

function safeErrorCode(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  return /^[A-Z0-9_]{2,80}$/.test(candidate) ? candidate : null;
}

function safeResultClass(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  return /^(?:[1-5]xx|ok|offline|network_error|timeout|client_error|ui_exception)$/.test(candidate)
    ? candidate
    : null;
}

function safeSupportReference(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  if (validCorrelationId(candidate)) return candidate;
  return /^[A-Za-z0-9][A-Za-z0-9._:-]{5,127}$/.test(candidate) ? candidate : null;
}

function safeNumber(value: unknown, min: number, max: number): number | null {
  return typeof value === "number" && Number.isFinite(value) && value >= min && value <= max
    ? Math.round(value)
    : null;
}

function safeLocale(value: unknown): RuntimeDiagnosticContext["locale"] {
  return value === "ar" || value === "en" || value === "fr" ? value : "unknown";
}

function safeDirection(value: unknown): RuntimeDiagnosticContext["direction"] {
  return value === "rtl" || value === "ltr" ? value : "unknown";
}

function sanitizeEvent(input: Partial<RuntimeDiagnosticEvent>): RuntimeDiagnosticEvent | null {
  const category = input.category;
  const severity = input.severity;
  if (
    !["request", "offline", "network", "timeout", "rfc9457", "validation", "ui_exception", "runtime"].includes(
      String(category),
    ) ||
    !["debug", "info", "warn", "error", "critical"].includes(String(severity))
  ) {
    return null;
  }

  return {
    timestamp:
      typeof input.timestamp === "string" && /^\d{4}-\d{2}-\d{2}T/.test(input.timestamp)
        ? input.timestamp
        : new Date().toISOString(),
    severity: severity as DiagnosticSeverity,
    surface: input.surface === "public" ? "public" : "web",
    category: category as DiagnosticCategory,
    operation: safeLabel(input.operation, "unknown", 80),
    correlationId: validCorrelationId(input.correlationId) ?? null,
    supportReference: safeSupportReference(input.supportReference),
    resultClass: safeResultClass(input.resultClass),
    status: safeNumber(input.status, 100, 599),
    errorCode: safeErrorCode(input.errorCode),
    durationMs: safeNumber(input.durationMs, 0, 300_000),
    route: input.route ? safeRoute(input.route) : null,
    locale: safeLocale(input.locale),
    direction: safeDirection(input.direction),
    online: typeof input.online === "boolean" ? input.online : null,
    retryState:
      input.retryState === "none" || input.retryState === "retryable" || input.retryState === "retrying"
        ? input.retryState
        : null,
  };
}

function notify() {
  for (const listener of listeners) {
    try {
      listener();
    } catch {
      // Diagnostics listeners are best-effort and may never break product flows.
    }
  }
}

function persist() {
  if (!enabled || typeof window === "undefined") return;
  try {
    events = boundEventsByBytes(events, (candidate) => JSON.stringify(candidate));
    // Persist only the sanitized, bounded metadata ring. No request bodies,
    // credentials, learner content, exception messages or tokens enter it.
    window.localStorage.setItem(storageKey, JSON.stringify(events));
    window.sessionStorage.removeItem(storageKey);
  } catch {
    // Diagnostics storage is optional and must never block the experience.
  }
}

function hydrate() {
  if (hydrated || typeof window === "undefined") return;
  hydrated = true;
  try {
    const persistentRaw = window.localStorage.getItem(storageKey);
    const legacySessionRaw = persistentRaw ? null : window.sessionStorage.getItem(storageKey);
    const raw = persistentRaw ?? legacySessionRaw;
    if (!raw) return;
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return;
    events = boundEventsByBytes(
      parsed
        .map((item) => (item && typeof item === "object" ? sanitizeEvent(item as Partial<RuntimeDiagnosticEvent>) : null))
        .filter((item): item is RuntimeDiagnosticEvent => item !== null),
      (candidate) => JSON.stringify(candidate),
    );
    if (legacySessionRaw) persist();
  } catch {
    events = [];
  }
}

export function configureRuntimeDiagnostics(
  isEnabled: boolean,
  identity?: Partial<Pick<RuntimeDiagnosticContext, "environment" | "build" | "commit">>,
) {
  enabled = isEnabled;
  context = {
    ...context,
    environment: safeLabel(identity?.environment, "unknown", 48),
    build: safeLabel(identity?.build, "unknown", 64),
    commit: safeLabel(identity?.commit, "unknown", 64),
  };
  if (!enabled) {
    events = [];
    hydrated = false;
    if (typeof window !== "undefined") {
      try {
        window.localStorage.removeItem(storageKey);
        window.sessionStorage.removeItem(storageKey);
      } catch {
        // Best effort only.
      }
    }
    notify();
    return;
  }
  hydrate();
  notify();
}

export function runtimeDiagnosticsEnabled(): boolean {
  return enabled;
}

export function setRuntimeDiagnosticContext(update: Partial<RuntimeDiagnosticContext>) {
  context = {
    ...context,
    route: update.route === undefined ? context.route : safeRoute(update.route),
    locale: update.locale === undefined ? context.locale : safeLocale(update.locale),
    direction: update.direction === undefined ? context.direction : safeDirection(update.direction),
    online: update.online === undefined ? context.online : typeof update.online === "boolean" ? update.online : null,
    cacheSummary:
      update.cacheSummary === undefined
        ? context.cacheSummary
        : {
            status: ["unknown", "available", "unavailable"].includes(update.cacheSummary.status)
              ? update.cacheSummary.status
              : "unknown",
            entries: safeNumber(update.cacheSummary.entries, 0, 1_000_000),
          },
    syncSummary:
      update.syncSummary === undefined
        ? context.syncSummary
        : {
            status: ["unknown", "idle", "pending", "error"].includes(update.syncSummary.status)
              ? update.syncSummary.status
              : "unknown",
            pending: safeNumber(update.syncSummary.pending, 0, 1_000_000),
          },
  };
  notify();
}

export function recordRuntimeDiagnostic(input: EventInput) {
  if (!enabled) return;
  hydrate();
  const event = sanitizeEvent({
    ...input,
    timestamp: new Date().toISOString(),
    route: context.route,
    locale: context.locale,
    direction: context.direction,
    online: context.online,
  });
  if (!event) return;
  events = boundEventsByBytes([...events, event], (candidate) => JSON.stringify(candidate));
  persist();
  notify();
}

export function clearRuntimeDiagnostics() {
  events = [];
  persist();
  notify();
}

export function getRuntimeDiagnosticSnapshot(): RuntimeDiagnosticEvent[] {
  if (enabled) hydrate();
  return [...events];
}

function bundleForEvents(candidateEvents: RuntimeDiagnosticEvent[]): RuntimeDiagnosticBundle {
  return {
    schema_version: "modrik.web.runtime-diagnostics.v1",
    generated_at: new Date().toISOString(),
    context: {
      ...context,
      cacheSummary: { ...context.cacheSummary },
      syncSummary: { ...context.syncSummary },
    },
    events: candidateEvents,
  };
}

export function getRuntimeDiagnosticBundle(): RuntimeDiagnosticBundle {
  const bounded = boundEventsByBytes(getRuntimeDiagnosticSnapshot(), (candidate) =>
    JSON.stringify(bundleForEvents(candidate), null, 2),
  );
  return bundleForEvents(bounded);
}

export function serializeRuntimeDiagnosticBundle(): string {
  return JSON.stringify(getRuntimeDiagnosticBundle(), null, 2);
}

export function subscribeRuntimeDiagnostics(listener: Listener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function currentOnlineState(): boolean | null {
  return typeof navigator === "undefined" ? null : navigator.onLine;
}

function resultClassForStatus(status: number): string {
  return `${Math.floor(status / 100)}xx`;
}

async function safeProblemMetadata(response: Response): Promise<{ code: string | null; requestId: string | null }> {
  const contentType = response.headers.get("content-type") ?? "";
  if (!contentType.toLowerCase().includes("application/problem+json")) return { code: null, requestId: null };
  try {
    const payload = (await response.clone().json()) as { code?: unknown; request_id?: unknown };
    return {
      code: safeErrorCode(payload.code),
      requestId: safeSupportReference(payload.request_id),
    };
  } catch {
    return { code: null, requestId: null };
  }
}

export async function diagnosticFetch(operation: string, input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const startedAt = Date.now();
  const requestedCorrelationId = createCorrelationId();
  const headers = new Headers(init?.headers);
  headers.set(CORRELATION_HEADER, requestedCorrelationId);

  recordRuntimeDiagnostic({
    severity: "debug",
    category: "request",
    operation,
    correlationId: requestedCorrelationId,
    supportReference: null,
    resultClass: null,
    status: null,
    errorCode: null,
    durationMs: null,
    retryState: "none",
  });

  try {
    const response = await fetch(input, { ...init, headers });
    const correlationId = correlationIdFromResponse(response, requestedCorrelationId);
    const problem = await safeProblemMetadata(response);
    const durationMs = Date.now() - startedAt;
    recordRuntimeDiagnostic({
      severity: response.ok ? "info" : response.status >= 500 ? "error" : "warn",
      category: problem.code ? "rfc9457" : "request",
      operation,
      correlationId,
      supportReference: problem.requestId ?? correlationId,
      resultClass: resultClassForStatus(response.status),
      status: response.status,
      errorCode: problem.code,
      durationMs,
      retryState: response.status >= 500 ? "retryable" : "none",
    });
    return response;
  } catch (error) {
    const online = currentOnlineState();
    const name = error instanceof Error ? error.name : "UnknownError";
    const isTimeout = name === "TimeoutError" || name === "AbortError";
    const category: DiagnosticCategory = online === false ? "offline" : isTimeout ? "timeout" : "network";
    recordRuntimeDiagnostic({
      severity: "error",
      category,
      operation,
      correlationId: requestedCorrelationId,
      supportReference: requestedCorrelationId,
      resultClass: online === false ? "offline" : isTimeout ? "timeout" : "network_error",
      status: null,
      errorCode: online === false ? "CLIENT_OFFLINE" : isTimeout ? "CLIENT_TIMEOUT" : "CLIENT_NETWORK_FAILURE",
      durationMs: Date.now() - startedAt,
      retryState: "retryable",
    });
    throw error;
  }
}

export function recordBrowserException(kind: "error" | "unhandledrejection" | "react", value: unknown) {
  const errorName = value instanceof Error ? safeLabel(value.name, "Error", 40) : safeLabel(typeof value, "unknown", 40);
  recordRuntimeDiagnostic({
    severity: "error",
    category: "ui_exception",
    operation: `browser:${kind}:${errorName}`,
    correlationId: null,
    supportReference: null,
    resultClass: "ui_exception",
    status: null,
    errorCode: "CLIENT_UI_EXCEPTION",
    durationMs: null,
    retryState: "none",
  });
}
