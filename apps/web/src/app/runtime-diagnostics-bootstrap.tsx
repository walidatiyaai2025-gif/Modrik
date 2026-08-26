"use client";

import { useLayoutEffect, type ReactNode } from "react";
import {
  configureRuntimeDiagnostics,
  setRuntimeDiagnosticContext,
} from "../lib/runtime-diagnostics";
import type { RuntimeInspectorConfig } from "../lib/runtime-inspector-config";

const diagnosticStorageKey = "modrik_runtime_diagnostics_v1";

type Props = RuntimeInspectorConfig & { children: ReactNode };

/**
 * Initializes browser diagnostics before descendant passive effects can issue
 * Student/Public API requests. This keeps the very first failing request in the
 * trace instead of starting collection only after the Inspector UI mounts.
 *
 * A previous MODRIK build stored the already-sanitized ring in sessionStorage.
 * If that legacy ring is present, remove a newer localStorage placeholder first
 * so runtime-diagnostics can sanitize/migrate the legacy data through its normal
 * bounded persistence path. Raw storage payloads are never copied between stores.
 */
export default function RuntimeDiagnosticsBootstrap({
  enabled,
  environment,
  build,
  commit,
  children,
}: Props) {
  useLayoutEffect(() => {
    if (enabled) {
      try {
        if (window.sessionStorage.getItem(diagnosticStorageKey)) {
          window.localStorage.removeItem(diagnosticStorageKey);
        }
      } catch {
        // Diagnostics persistence is best-effort and may never block the app.
      }
    }

    configureRuntimeDiagnostics(enabled, { environment, build, commit });
    if (enabled) {
      const html = document.documentElement;
      const locale = html.lang === "ar" || html.lang === "en" || html.lang === "fr" ? html.lang : "unknown";
      const direction = html.dir === "rtl" || html.dir === "ltr" ? html.dir : "unknown";
      setRuntimeDiagnosticContext({
        route: window.location.pathname,
        locale,
        direction,
        online: navigator.onLine,
      });
    }
  }, [enabled, environment, build, commit]);

  return children;
}
