"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import styles from "./runtime-inspector.module.css";
import { RuntimeInspectorEventList } from "../lib/runtime-inspector-event-list";
import {
  clearRuntimeDiagnostics,
  configureRuntimeDiagnostics,
  getRuntimeDiagnosticSnapshot,
  recordBrowserException,
  serializeRuntimeDiagnosticBundle,
  setRuntimeDiagnosticContext,
  subscribeRuntimeDiagnostics,
  type RuntimeDiagnosticEvent,
} from "../lib/runtime-diagnostics";

type Locale = "ar" | "en" | "fr";
type Direction = "rtl" | "ltr";

type Props = {
  enabled: boolean;
  environment: string;
  build: string;
  commit: string;
};

const copy = {
  en: {
    open: "Runtime inspector",
    title: "MODRIK Runtime Inspector",
    close: "Close inspector",
    environment: "Environment",
    build: "Build",
    commit: "Commit",
    route: "Route",
    locale: "Locale",
    direction: "Direction",
    connectivity: "Connectivity",
    cache: "Local cache",
    sync: "Local sync",
    unknown: "Unknown / not exposed",
    online: "Online",
    offline: "Offline",
    timeline: "Recent diagnostic timeline",
    filter: "Filter by correlation ID",
    clear: "Clear diagnostics",
    copyBundle: "Copy diagnostic JSON",
    download: "Download diagnostic JSON",
    copyCorrelation: "Copy correlation ID",
    empty: "No diagnostic events captured in this browser session.",
    support: "Support reference",
    status: "Result",
  },
  ar: {
    open: "فاحص التشغيل",
    title: "فاحص تشغيل مُدرك",
    close: "إغلاق الفاحص",
    environment: "البيئة",
    build: "الإصدار",
    commit: "النسخة البرمجية",
    route: "المسار",
    locale: "اللغة",
    direction: "الاتجاه",
    connectivity: "الاتصال",
    cache: "التخزين المحلي",
    sync: "المزامنة المحلية",
    unknown: "غير معروف / غير معروض",
    online: "متصل",
    offline: "غير متصل",
    timeline: "آخر أحداث التشخيص",
    filter: "تصفية بمعرّف الارتباط",
    clear: "مسح التشخيصات",
    copyBundle: "نسخ JSON التشخيصي",
    download: "تنزيل JSON التشخيصي",
    copyCorrelation: "نسخ معرّف الارتباط",
    empty: "لا توجد أحداث تشخيصية في جلسة المتصفح الحالية.",
    support: "مرجع الدعم",
    status: "النتيجة",
  },
  fr: {
    open: "Inspecteur d’exécution",
    title: "Inspecteur d’exécution MODRIK",
    close: "Fermer l’inspecteur",
    environment: "Environnement",
    build: "Version",
    commit: "Commit",
    route: "Route",
    locale: "Langue",
    direction: "Direction",
    connectivity: "Connexion",
    cache: "Cache local",
    sync: "Synchronisation locale",
    unknown: "Inconnu / non exposé",
    online: "En ligne",
    offline: "Hors ligne",
    timeline: "Chronologie de diagnostic récente",
    filter: "Filtrer par identifiant de corrélation",
    clear: "Effacer les diagnostics",
    copyBundle: "Copier le JSON de diagnostic",
    download: "Télécharger le JSON de diagnostic",
    copyCorrelation: "Copier l’identifiant de corrélation",
    empty: "Aucun événement de diagnostic dans cette session du navigateur.",
    support: "Référence de support",
    status: "Résultat",
  },
} as const;

function pageLocale(): Locale {
  if (typeof document === "undefined") return "en";
  const scoped = document.querySelector<HTMLElement>("[lang][dir]:not(html)");
  const value = scoped?.lang || document.documentElement.lang;
  return value === "ar" || value === "fr" ? value : "en";
}

function pageDirection(locale: Locale): Direction {
  if (typeof document === "undefined") return locale === "ar" ? "rtl" : "ltr";
  const scoped = document.querySelector<HTMLElement>("[lang][dir]:not(html)");
  const value = scoped?.dir || document.documentElement.dir;
  return value === "rtl" ? "rtl" : "ltr";
}

function currentRoute(): string {
  return typeof window === "undefined" ? "/" : window.location.pathname;
}

export default function RuntimeInspector({ enabled, environment, build, commit }: Props) {
  const [open, setOpen] = useState(false);
  const [events, setEvents] = useState<RuntimeDiagnosticEvent[]>([]);
  const [filter, setFilter] = useState("");
  const [locale, setLocale] = useState<Locale>("en");
  const [direction, setDirection] = useState<Direction>("ltr");
  const [online, setOnline] = useState(true);
  const [route, setRoute] = useState("/");
  const launcherRef = useRef<HTMLButtonElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);
  const drawerRef = useRef<HTMLElement>(null);
  const labels = copy[locale];

  useEffect(() => {
    configureRuntimeDiagnostics(enabled, { environment, build, commit });
    if (!enabled) return;

    const refreshContext = () => {
      const nextLocale = pageLocale();
      const nextDirection = pageDirection(nextLocale);
      const nextOnline = navigator.onLine;
      const nextRoute = currentRoute();
      setLocale(nextLocale);
      setDirection(nextDirection);
      setOnline(nextOnline);
      setRoute(nextRoute);
      setRuntimeDiagnosticContext({
        locale: nextLocale,
        direction: nextDirection,
        online: nextOnline,
        route: nextRoute,
      });
    };
    const refreshEvents = () => setEvents(getRuntimeDiagnosticSnapshot());
    const onError = (event: ErrorEvent) => recordBrowserException("error", event.error ?? event.type);
    const onRejection = (event: PromiseRejectionEvent) => recordBrowserException("unhandledrejection", event.reason);

    const startup = window.setTimeout(() => {
      refreshContext();
      refreshEvents();
    }, 0);
    const unsubscribe = subscribeRuntimeDiagnostics(refreshEvents);
    window.addEventListener("online", refreshContext);
    window.addEventListener("offline", refreshContext);
    window.addEventListener("popstate", refreshContext);
    window.addEventListener("error", onError);
    window.addEventListener("unhandledrejection", onRejection);
    const observer = new MutationObserver(refreshContext);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ["lang", "dir"] });
    if (document.body) observer.observe(document.body, { subtree: true, attributes: true, attributeFilter: ["lang", "dir"] });

    return () => {
      window.clearTimeout(startup);
      unsubscribe();
      observer.disconnect();
      window.removeEventListener("online", refreshContext);
      window.removeEventListener("offline", refreshContext);
      window.removeEventListener("popstate", refreshContext);
      window.removeEventListener("error", onError);
      window.removeEventListener("unhandledrejection", onRejection);
    };
  }, [build, commit, enabled, environment]);

  useEffect(() => {
    if (!open) return;
    const returnFocus = launcherRef.current;
    const focusTimer = window.setTimeout(() => closeRef.current?.focus(), 0);
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        setOpen(false);
        return;
      }
      if (event.key !== "Tab" || !drawerRef.current) return;
      const focusable = Array.from(
        drawerRef.current.querySelectorAll<HTMLElement>(
          'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
        ),
      );
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => {
      window.clearTimeout(focusTimer);
      window.removeEventListener("keydown", onKeyDown);
      returnFocus?.focus();
    };
  }, [open]);

  const visibleEvents = useMemo(() => {
    const candidate = filter.trim();
    if (!candidate) return events;
    return events.filter((event) => event.correlationId?.includes(candidate) || event.supportReference?.includes(candidate));
  }, [events, filter]);

  if (!enabled) return null;

  const copyText = async (value: string) => {
    try {
      await navigator.clipboard.writeText(value);
    } catch {
      // Clipboard access is optional; diagnostics must not affect the product flow.
    }
  };

  const downloadBundle = () => {
    try {
      const blob = new Blob([serializeRuntimeDiagnosticBundle()], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = "modrik-runtime-diagnostics.json";
      anchor.click();
      URL.revokeObjectURL(url);
    } catch {
      // Export is best-effort only.
    }
  };

  const clear = () => {
    clearRuntimeDiagnostics();
    setEvents([]);
  };

  return (
    <div className={styles.host} lang={locale} dir={direction} data-runtime-inspector="enabled">
      <button
        ref={launcherRef}
        className={styles.launcher}
        type="button"
        onClick={() => setOpen(true)}
        aria-haspopup="dialog"
        aria-expanded={open}
      >
        {labels.open}
      </button>
      {open ? (
        <div className={styles.backdrop} role="presentation">
          <section
            ref={drawerRef}
            className={styles.drawer}
            role="dialog"
            aria-modal="true"
            aria-labelledby="runtime-inspector-title"
          >
            <header className={styles.header}>
              <h2 id="runtime-inspector-title">{labels.title}</h2>
              <button ref={closeRef} type="button" className={styles.close} onClick={() => setOpen(false)} aria-label={labels.close}>
                ×
              </button>
            </header>

            <dl className={styles.summary}>
              <div><dt>{labels.environment}</dt><dd>{environment}</dd></div>
              <div><dt>{labels.build}</dt><dd>{build}</dd></div>
              <div><dt>{labels.commit}</dt><dd>{commit}</dd></div>
              <div><dt>{labels.route}</dt><dd>{route}</dd></div>
              <div><dt>{labels.locale}</dt><dd>{locale.toUpperCase()}</dd></div>
              <div><dt>{labels.direction}</dt><dd>{direction.toUpperCase()}</dd></div>
              <div><dt>{labels.connectivity}</dt><dd>{online ? labels.online : labels.offline}</dd></div>
              <div><dt>{labels.cache}</dt><dd>{labels.unknown}</dd></div>
              <div><dt>{labels.sync}</dt><dd>{labels.unknown}</dd></div>
            </dl>

            <div className={styles.toolbar}>
              <label>
                <span>{labels.filter}</span>
                <input value={filter} onChange={(event) => setFilter(event.currentTarget.value)} autoComplete="off" />
              </label>
              <button type="button" onClick={() => void copyText(serializeRuntimeDiagnosticBundle())}>{labels.copyBundle}</button>
              <button type="button" onClick={downloadBundle}>{labels.download}</button>
              <button type="button" onClick={clear}>{labels.clear}</button>
            </div>

            <RuntimeInspectorEventList
              events={visibleEvents}
              labels={labels}
              timelineClassName={styles.timeline}
              emptyClassName={styles.empty}
              eventHeadingClassName={styles.eventHeading}
              correlationRowClassName={styles.correlationRow}
              copyCorrelationClassName={styles.copyCorrelation}
              onCopyCorrelation={(correlationId) => void copyText(correlationId)}
            />
          </section>
        </div>
      ) : null}
    </div>
  );
}
