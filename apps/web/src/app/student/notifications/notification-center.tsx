"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  learningApi,
  LearningApiError,
  type Locale,
  type StudentNotificationInbox,
} from "@/lib/learning-api";
import { directionForLocale, localize } from "../../student-copy";
import { notificationCopy } from "../notification-copy";
import styles from "./notification-center.module.css";

type ViewState = "loading" | "ready" | "offline" | "error" | "permission";

export default function NotificationCenter() {
  const [locale, setLocale] = useState<Locale>("en");
  const [state, setState] = useState<ViewState>("loading");
  const [inbox, setInbox] = useState<StudentNotificationInbox>({ items: [], unread_count: 0 });
  const [busyId, setBusyId] = useState<string | "all" | null>(null);
  const [message, setMessage] = useState("");

  const labels = notificationCopy[locale];
  const direction = directionForLocale(locale);

  const handleError = useCallback((error: unknown) => {
    if (typeof navigator !== "undefined" && !navigator.onLine) {
      setState("offline");
      return;
    }
    if (error instanceof LearningApiError && (error.status === 401 || error.status === 403)) {
      setState("permission");
      return;
    }
    setState("error");
  }, []);

  const load = useCallback(async () => {
    if (typeof navigator !== "undefined" && !navigator.onLine) {
      setState("offline");
      return;
    }

    setState("loading");
    setMessage("");
    try {
      const [session, nextInbox] = await Promise.all([learningApi.session(), learningApi.notifications()]);
      setLocale(session.locale);
      setInbox(nextInbox);
      setState("ready");
    } catch (error) {
      handleError(error);
    }
  }, [handleError]);

  useEffect(() => {
    void load();
    const offline = () => setState("offline");
    const online = () => void load();
    window.addEventListener("offline", offline);
    window.addEventListener("online", online);
    return () => {
      window.removeEventListener("offline", offline);
      window.removeEventListener("online", online);
    };
  }, [load]);

  async function markRead(notificationId: string) {
    if (!navigator.onLine) {
      setState("offline");
      return;
    }

    setBusyId(notificationId);
    setMessage("");
    try {
      const updated = await learningApi.markNotificationRead(notificationId);
      setInbox((current) => ({
        items: current.items.map((item) => (item.id === updated.id ? updated : item)),
        unread_count: Math.max(0, current.unread_count - (current.items.find((item) => item.id === updated.id)?.is_read ? 0 : 1)),
      }));
    } catch (error) {
      handleError(error);
    } finally {
      setBusyId(null);
    }
  }

  async function markAllRead() {
    if (!navigator.onLine) {
      setState("offline");
      return;
    }

    setBusyId("all");
    setMessage("");
    try {
      const result = await learningApi.markAllNotificationsRead();
      const readAt = new Date().toISOString();
      setInbox((current) => ({
        unread_count: result.unread_count,
        items: current.items.map((item) =>
          item.is_read ? item : { ...item, is_read: true, read_at: item.read_at ?? readAt },
        ),
      }));
      setMessage(labels.markedAll);
    } catch (error) {
      handleError(error);
    } finally {
      setBusyId(null);
    }
  }

  const stateCopy = {
    loading: { title: labels.loading, body: "" },
    offline: { title: labels.offline, body: "" },
    error: { title: labels.unavailable, body: "" },
    permission: { title: labels.permission, body: "" },
    ready: { title: "", body: "" },
  }[state];

  return (
    <main
      className={styles.shell}
      lang={locale}
      dir={direction}
      data-testid="modrik-student-notification-center"
    >
      <div className={styles.frame}>
        <header className={styles.header}>
          <div>
            <p className={styles.eyebrow}>MODRIK · مُدرك</p>
            <h1 className={styles.title}>{labels.title}</h1>
            <p className={styles.subtitle}>{labels.subtitle}</p>
          </div>
          <div className={styles.actions}>
            <Link className={styles.linkButton} href="/student">
              {labels.back}
            </Link>
            <div className={styles.localeSwitcher} aria-label="Language">
              {(["ar", "en", "fr"] as const).map((language) => (
                <button
                  type="button"
                  key={language}
                  className={styles.localeButton}
                  aria-pressed={locale === language}
                  lang={language}
                  onClick={() => setLocale(language)}
                >
                  {language.toUpperCase()}
                </button>
              ))}
            </div>
          </div>
        </header>

        {state !== "ready" ? (
          <section className={styles.state} role={state === "loading" ? "status" : "alert"} aria-live="polite">
            <div className={styles.stateInner}>
              <h2 className={styles.stateTitle}>{stateCopy.title}</h2>
              {stateCopy.body && <p className={styles.stateBody}>{stateCopy.body}</p>}
              {state !== "loading" && state !== "permission" && (
                <button type="button" className={styles.button} onClick={() => void load()}>
                  {labels.retry}
                </button>
              )}
              {state === "permission" && (
                <Link className={styles.linkButton} href="/student">
                  {labels.back}
                </Link>
              )}
            </div>
          </section>
        ) : (
          <>
            <section className={styles.summary} aria-live="polite">
              <div className={styles.count}>
                <span>{labels.unread}</span>
                <span className={styles.countBadge}>{inbox.unread_count}</span>
                <span>· {inbox.items.length} {labels.notifications}</span>
              </div>
              <button
                type="button"
                className={styles.button}
                disabled={inbox.unread_count === 0 || busyId !== null}
                onClick={() => void markAllRead()}
              >
                {labels.markAll}
              </button>
            </section>

            {message && <p role="status" aria-live="polite">{message}</p>}

            {inbox.items.length === 0 ? (
              <section className={styles.state} role="status">
                <div className={styles.stateInner}>
                  <h2 className={styles.stateTitle}>{labels.emptyTitle}</h2>
                  <p className={styles.stateBody}>{labels.emptyBody}</p>
                </div>
              </section>
            ) : (
              <ul className={styles.list} aria-label={labels.title}>
                {inbox.items.map((notification) => (
                  <li key={notification.id}>
                    <article className={styles.card} data-unread={!notification.is_read}>
                      <div className={styles.cardHeader}>
                        <div>
                          <h2 className={styles.cardTitle} dir="auto">
                            {localize(notification.title, locale)}
                          </h2>
                          <p className={styles.body} dir="auto">
                            {localize(notification.body, locale)}
                          </p>
                        </div>
                        <span className={styles.status} data-unread={!notification.is_read}>
                          {notification.is_read ? labels.read : labels.unread}
                        </span>
                      </div>
                      <div className={styles.meta}>
                        <span>{notification.kind.replaceAll("_", " ")}</span>
                        <span aria-hidden="true">·</span>
                        <time dateTime={notification.occurred_at}>
                          {new Intl.DateTimeFormat(locale, { dateStyle: "medium", timeStyle: "short" }).format(
                            new Date(notification.occurred_at),
                          )}
                        </time>
                      </div>
                      {!notification.is_read && (
                        <div className={styles.cardAction}>
                          <button
                            type="button"
                            className={styles.button}
                            disabled={busyId !== null}
                            onClick={() => void markRead(notification.id)}
                          >
                            {labels.markRead}
                          </button>
                        </div>
                      )}
                    </article>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </div>
    </main>
  );
}
