"use client";

import type { FormEvent } from "react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { AuthApiError, authApi, type AccountSummary, type AuthSession, type Locale, type Provider } from "../lib/auth-api";
import { authCopy, localeDirection } from "./auth-copy";
import LearningWorkspace from "./learning-workspace";

type AuthScreen = "login" | "register" | "recovery" | "verify" | "reset";
type NoticeKind = "status" | "error" | "permission" | "provider";
type Notice = { kind: NoticeKind; title?: string; body: string } | null;
type SessionsState = "idle" | "loading" | "ready" | "error";

function value(form: FormData, key: string): string {
  const candidate = form.get(key);
  return typeof candidate === "string" ? candidate : "";
}

function errorNotice(error: unknown, locale: Locale, context: "login" | "provider" | "generic" = "generic"): Notice {
  const copy = authCopy[locale];
  if (context === "login" && error instanceof AuthApiError && error.status === 401) {
    return { kind: "error", body: copy.loginRejected };
  }
  if (context === "provider" && error instanceof AuthApiError && (error.status === 503 || error.code === "PROVIDER_CONFIGURATION_PENDING")) {
    return { kind: "provider", title: copy.providerPendingTitle, body: copy.providerPendingBody };
  }
  if (context === "provider") return { kind: "error", body: copy.providerError };
  return { kind: "error", body: copy.genericError };
}

export function ProviderPendingNotice({ locale }: { locale: Locale }) {
  const copy = authCopy[locale];
  return (
    <div className="auth-notice auth-notice-provider" role="status" aria-live="polite">
      <strong>{copy.providerPendingTitle}</strong>
      <span>{copy.providerPendingBody}</span>
    </div>
  );
}

export function SessionExpiredNotice({ locale }: { locale: Locale }) {
  const copy = authCopy[locale];
  return (
    <div className="auth-notice auth-notice-error" role="alert">
      <strong>{copy.sessionExpiredTitle}</strong>
      <span>{copy.sessionExpiredBody}</span>
    </div>
  );
}

function LocalePicker({ locale, onChange }: { locale: Locale; onChange: (locale: Locale) => void }) {
  return (
    <div className="auth-locale" aria-label="Language">
      {(["ar", "en", "fr"] as const).map((item) => (
        <button
          key={item}
          type="button"
          className={locale === item ? "auth-locale-active" : undefined}
          aria-pressed={locale === item}
          onClick={() => onChange(item)}
        >
          {item.toUpperCase()}
        </button>
      ))}
    </div>
  );
}

function NoticeRegion({ notice }: { notice: Notice }) {
  if (!notice) return <div className="auth-live sr-only" role="status" aria-live="polite" />;
  const role = notice.kind === "error" ? "alert" : "status";
  return (
    <div className={`auth-notice auth-notice-${notice.kind}`} role={role} aria-live={role === "status" ? "polite" : undefined}>
      {notice.title ? <strong>{notice.title}</strong> : null}
      <span>{notice.body}</span>
    </div>
  );
}

function OfflineBanner({ locale }: { locale: Locale }) {
  const copy = authCopy[locale];
  return (
    <div className="auth-notice auth-notice-offline" role="status" aria-live="polite">
      <strong>{copy.offlineTitle}</strong>
      <span>{copy.offlineBody}</span>
    </div>
  );
}

export default function AuthWorkspace() {
  const [locale, setLocale] = useState<Locale>("en");
  const [authenticated, setAuthenticated] = useState<boolean | null>(null);
  const [screen, setScreen] = useState<AuthScreen>("login");
  const [account, setAccount] = useState<AccountSummary | null>(null);
  const [showAccount, setShowAccount] = useState(false);
  const [sessions, setSessions] = useState<AuthSession[]>([]);
  const [sessionsState, setSessionsState] = useState<SessionsState>("idle");
  const [notice, setNotice] = useState<Notice>(null);
  const [offline, setOffline] = useState(false);
  const [busy, setBusy] = useState(false);
  const [recentAuthRequired, setRecentAuthRequired] = useState(false);
  const [verificationToken, setVerificationToken] = useState("");
  const [resetToken, setResetToken] = useState("");
  const copy = authCopy[locale];
  const direction = localeDirection(locale);

  const loadSessions = useCallback(async () => {
    setSessionsState("loading");
    try {
      const result = await authApi.sessions();
      setSessions(result.sessions);
      setSessionsState("ready");
    } catch (error) {
      if (error instanceof AuthApiError && error.status === 401) {
        setAuthenticated(false);
        setShowAccount(false);
        setNotice({ kind: "error", title: copy.sessionExpiredTitle, body: copy.sessionExpiredBody });
        return;
      }
      setSessionsState("error");
    }
  }, [copy.sessionExpiredBody, copy.sessionExpiredTitle]);

  useEffect(() => {
    let active = true;
    const updateOnline = () => {
      if (active) setOffline(!navigator.onLine);
    };
    const initialize = async () => {
      const params = new URLSearchParams(window.location.search);
      const verify = params.get("verify_token");
      const reset = params.get("reset_token");
      if (active && verify) {
        setVerificationToken(verify);
        setScreen("verify");
      } else if (active && reset) {
        setResetToken(reset);
        setScreen("reset");
      }
      updateOnline();

      try {
        const session = await authApi.session();
        if (!active) return;
        setLocale(session.locale);
        setAuthenticated(true);
      } catch (error) {
        if (!active) return;
        if (error instanceof AuthApiError && error.status === 401) {
          setAuthenticated(false);
          return;
        }
        setAuthenticated(false);
        setNotice({ kind: "error", body: authCopy.en.genericError });
      }
    };

    const startup = window.setTimeout(() => void initialize(), 0);
    window.addEventListener("online", updateOnline);
    window.addEventListener("offline", updateOnline);

    return () => {
      active = false;
      window.clearTimeout(startup);
      window.removeEventListener("online", updateOnline);
      window.removeEventListener("offline", updateOnline);
    };
  }, []);

  useEffect(() => {
    if (!authenticated) return;
    let active = true;
    const recheck = async () => {
      try {
        await authApi.session();
      } catch (error) {
        if (!active) return;
        if (error instanceof AuthApiError && error.status === 401) {
          setAuthenticated(false);
          setAccount(null);
          setSessions([]);
          setSessionsState("idle");
          setShowAccount(false);
          setNotice({ kind: "error", title: copy.sessionExpiredTitle, body: copy.sessionExpiredBody });
          return;
        }
        setNotice({ kind: "error", body: copy.genericError });
      }
    };
    const timer = window.setInterval(() => void recheck(), 60_000);
    const onVisibility = () => {
      if (document.visibilityState === "visible") void recheck();
    };
    document.addEventListener("visibilitychange", onVisibility);
    return () => {
      active = false;
      window.clearInterval(timer);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, [authenticated, copy.genericError, copy.sessionExpiredBody, copy.sessionExpiredTitle]);

  const localeDate = useMemo(
    () => (locale === "ar" ? "ar-KW" : locale === "fr" ? "fr-FR" : "en-US"),
    [locale],
  );

  async function protectedAction(action: () => Promise<void>, success?: string) {
    if (offline) return;
    setBusy(true);
    setNotice(null);
    try {
      await action();
      if (success) setNotice({ kind: "status", body: success });
    } catch (error) {
      if (error instanceof AuthApiError && error.status === 401) {
        setAuthenticated(false);
        setShowAccount(false);
        setNotice({ kind: "error", title: copy.sessionExpiredTitle, body: copy.sessionExpiredBody });
      } else if (error instanceof AuthApiError && (error.code === "RECENT_AUTHENTICATION_REQUIRED" || error.status === 403)) {
        setRecentAuthRequired(true);
        setNotice({ kind: "permission", title: copy.recentAuthTitle, body: copy.recentAuthBody });
      } else {
        setNotice(errorNotice(error, locale));
      }
      throw error;
    } finally {
      setBusy(false);
    }
  }

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (offline) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setNotice(null);
    try {
      const result = await authApi.login(value(form, "email"), value(form, "password"));
      setAccount(result.account);
      setAuthenticated(true);
      setNotice({ kind: "status", body: copy.signedIn });
    } catch (error) {
      setNotice(errorNotice(error, locale, "login"));
    } finally {
      setBusy(false);
    }
  }

  async function handleRegister(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (offline) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setNotice(null);
    try {
      const result = await authApi.register(value(form, "name"), value(form, "email"), value(form, "password"));
      setAccount(result.account);
      setAuthenticated(true);
      setNotice({ kind: "permission", title: copy.unverifiedTitle, body: copy.accountCreated });
    } catch (error) {
      setNotice(errorNotice(error, locale));
    } finally {
      setBusy(false);
    }
  }

  async function handleRecovery(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (offline) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    try {
      await authApi.requestRecovery(value(form, "email"));
      setNotice({ kind: "status", body: copy.recoveryAccepted });
    } catch (error) {
      setNotice(errorNotice(error, locale));
    } finally {
      setBusy(false);
    }
  }

  async function handleVerify(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (offline) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    try {
      await authApi.verifyEmail(value(form, "token"));
      setAccount((current) => (current ? { ...current, email_verified: true } : current));
      setNotice({ kind: "status", body: copy.verified });
      if (!authenticated) setScreen("login");
      window.history.replaceState({}, "", window.location.pathname);
    } catch (error) {
      setNotice(errorNotice(error, locale));
    } finally {
      setBusy(false);
    }
  }

  async function handleReset(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (offline) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    try {
      await authApi.resetPassword(value(form, "token"), value(form, "password"));
      setAuthenticated(false);
      setAccount(null);
      setScreen("login");
      setNotice({ kind: "status", body: copy.resetComplete });
      window.history.replaceState({}, "", window.location.pathname);
    } catch (error) {
      setNotice(errorNotice(error, locale));
    } finally {
      setBusy(false);
    }
  }

  async function beginProvider(provider: Provider, purpose: "login" | "link") {
    if (offline) return;
    setBusy(true);
    setNotice(null);
    try {
      await authApi.providerIntent(provider, purpose);
      setNotice({ kind: "provider", title: copy.providerPendingTitle, body: copy.providerPendingBody });
    } catch (error) {
      if (purpose === "link" && error instanceof AuthApiError && error.status === 403) {
        setRecentAuthRequired(true);
        setNotice({ kind: "permission", title: copy.recentAuthTitle, body: copy.recentAuthBody });
      } else {
        setNotice(errorNotice(error, locale, "provider"));
      }
    } finally {
      setBusy(false);
    }
  }

  async function resendVerification() {
    try {
      await protectedAction(() => authApi.resendVerification());
      setNotice({ kind: "status", body: copy.verificationAccepted });
    } catch {
      // protectedAction already rendered the contract-safe state.
    }
  }

  async function logout() {
    try {
      await protectedAction(() => authApi.logoutCurrent());
    } catch {
      // A revoked session is already equivalent to logout in the UI.
    }
    setAuthenticated(false);
    setAccount(null);
    setSessions([]);
    setShowAccount(false);
    setScreen("login");
  }

  async function revokeOthers() {
    try {
      await protectedAction(() => authApi.revokeOthers());
      await loadSessions();
    } catch {
      // State is already rendered.
    }
  }

  async function revokeAll() {
    try {
      await protectedAction(() => authApi.revokeAll());
    } catch {
      // State is already rendered.
    }
    setAuthenticated(false);
    setAccount(null);
    setSessions([]);
    setShowAccount(false);
    setScreen("login");
  }

  async function handleReauthenticate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await protectedAction(() => authApi.reauthenticate(value(form, "password")), copy.signedIn);
      setRecentAuthRequired(false);
    } catch {
      // State is already rendered.
    }
  }

  async function handlePasswordChange(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await protectedAction(
        () => authApi.changePassword(value(form, "current_password"), value(form, "new_password")),
        copy.passwordChanged,
      );
      event.currentTarget.reset();
      await loadSessions();
    } catch {
      // State is already rendered.
    }
  }

  async function handleDelete(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    if (value(form, "confirmation") !== "DELETE") {
      setNotice({ kind: "permission", title: copy.deleteTitle, body: copy.deleteWarning });
      return;
    }
    try {
      await protectedAction(() => authApi.deleteAccount());
      setAuthenticated(false);
      setAccount(null);
      setSessions([]);
      setShowAccount(false);
      setScreen("login");
    } catch {
      // State is already rendered.
    }
  }

  if (authenticated === null) {
    return (
      <section className="auth-shell" lang={locale} dir={direction} aria-busy="true">
        <a className="skip-link" href="#auth-main">Skip to account access</a>
        <div className="auth-loading" id="auth-main" role="status" aria-live="polite">
          <div className="auth-mark" aria-hidden="true">M</div>
          <h1>MODRIK <span lang="ar" dir="rtl">مُدرك</span></h1>
          <p>{copy.loading}</p>
        </div>
      </section>
    );
  }

  if (!authenticated) {
    return (
      <section className="auth-shell" lang={locale} dir={direction}>
        <a className="skip-link" href="#auth-main">Skip to account access</a>
        <aside className="auth-brand-panel" aria-label="MODRIK">
          <div className="auth-brand-lockup">
            <div className="auth-mark" aria-hidden="true">M</div>
            <div>
              <strong>MODRIK</strong>
              <span lang="ar" dir="rtl">مُدرك</span>
            </div>
          </div>
          <h1>{copy.brandTagline}</h1>
          <p>AR · EN · FR</p>
        </aside>
        <main className="auth-main" id="auth-main">
          <div className="auth-toolbar"><LocalePicker locale={locale} onChange={setLocale} /></div>
          {offline ? <OfflineBanner locale={locale} /> : null}
          <NoticeRegion notice={notice} />
          <div className="auth-card">
            {screen === "login" ? (
              <>
                <h2>{copy.signInTitle}</h2>
                <form onSubmit={handleLogin} className="auth-form">
                  <label>{copy.email}<input name="email" type="email" required autoComplete="email" dir="ltr" /></label>
                  <label>{copy.password}<input name="password" type="password" required minLength={12} maxLength={128} autoComplete="current-password" /></label>
                  <button className="auth-primary" type="submit" disabled={busy || offline}>{busy ? copy.working : copy.signIn}</button>
                </form>
                <div className="auth-provider-grid" aria-label={copy.providerTitle}>
                  <button type="button" onClick={() => void beginProvider("google", "login")} disabled={busy || offline}>{copy.providerLogin} {copy.providerGoogle}</button>
                  <button type="button" onClick={() => void beginProvider("apple", "login")} disabled={busy || offline}>{copy.providerLogin} {copy.providerApple}</button>
                </div>
                <nav className="auth-subnav" aria-label="Account access options">
                  <button type="button" onClick={() => setScreen("recovery")}>{copy.forgotPassword}</button>
                  <button type="button" onClick={() => setScreen("register")}>{copy.createAccount}</button>
                  <button type="button" onClick={() => setScreen("verify")}>{copy.useVerificationToken}</button>
                  <button type="button" onClick={() => setScreen("reset")}>{copy.useResetToken}</button>
                </nav>
              </>
            ) : null}

            {screen === "register" ? (
              <>
                <h2>{copy.registerTitle}</h2>
                <form onSubmit={handleRegister} className="auth-form">
                  <label>{copy.name}<input name="name" required minLength={2} maxLength={100} autoComplete="name" /></label>
                  <label>{copy.email}<input name="email" type="email" required autoComplete="email" dir="ltr" /></label>
                  <label>{copy.password}<input name="password" type="password" required minLength={12} maxLength={128} autoComplete="new-password" /></label>
                  <button className="auth-primary" type="submit" disabled={busy || offline}>{busy ? copy.working : copy.register}</button>
                </form>
                <button className="auth-text-button" type="button" onClick={() => setScreen("login")}>{copy.backToLogin}</button>
              </>
            ) : null}

            {screen === "recovery" ? (
              <>
                <h2>{copy.recoveryTitle}</h2>
                <form onSubmit={handleRecovery} className="auth-form">
                  <label>{copy.email}<input name="email" type="email" required autoComplete="email" dir="ltr" /></label>
                  <button className="auth-primary" type="submit" disabled={busy || offline}>{busy ? copy.working : copy.sendRecovery}</button>
                </form>
                <button className="auth-text-button" type="button" onClick={() => setScreen("login")}>{copy.backToLogin}</button>
              </>
            ) : null}

            {screen === "verify" ? (
              <>
                <h2>{copy.verifyTitle}</h2>
                <form onSubmit={handleVerify} className="auth-form">
                  <label>{copy.token}<input name="token" required minLength={16} value={verificationToken} onChange={(event) => setVerificationToken(event.target.value)} dir="ltr" autoComplete="one-time-code" /></label>
                  <button className="auth-primary" type="submit" disabled={busy || offline}>{busy ? copy.working : copy.verify}</button>
                </form>
                <button className="auth-text-button" type="button" onClick={() => setScreen("login")}>{copy.backToLogin}</button>
              </>
            ) : null}

            {screen === "reset" ? (
              <>
                <h2>{copy.resetTitle}</h2>
                <form onSubmit={handleReset} className="auth-form">
                  <label>{copy.token}<input name="token" required minLength={16} value={resetToken} onChange={(event) => setResetToken(event.target.value)} dir="ltr" autoComplete="one-time-code" /></label>
                  <label>{copy.newPassword}<input name="password" type="password" required minLength={12} maxLength={128} autoComplete="new-password" /></label>
                  <button className="auth-primary" type="submit" disabled={busy || offline}>{busy ? copy.working : copy.resetPassword}</button>
                </form>
                <button className="auth-text-button" type="button" onClick={() => setScreen("login")}>{copy.backToLogin}</button>
              </>
            ) : null}
          </div>
        </main>
      </section>
    );
  }

  const otherSessions = sessions.filter((session) => !session.is_current);

  return (
    <section className="auth-authenticated" lang={locale} dir={direction}>
      <a className="skip-link" href="#student-main">Skip to main content</a>
      <header className="auth-topbar">
        <div className="auth-brand-lockup compact">
          <div className="auth-mark" aria-hidden="true">M</div>
          <div><strong>MODRIK</strong><span lang="ar" dir="rtl">مُدرك</span></div>
        </div>
        <nav className="auth-topnav" aria-label="Student account navigation">
          <button type="button" aria-pressed={!showAccount} onClick={() => setShowAccount(false)}>{copy.learning}</button>
          <button type="button" aria-pressed={showAccount} onClick={() => { setShowAccount(true); void loadSessions(); }}>{copy.account}</button>
        </nav>
        <LocalePicker locale={locale} onChange={setLocale} />
        <button className="auth-signout" type="button" onClick={() => void logout()} disabled={busy}>{copy.logout}</button>
      </header>
      {offline ? <OfflineBanner locale={locale} /> : null}
      <NoticeRegion notice={notice} />
      {account && !account.email_verified ? (
        <div className="auth-permission-banner" role="status">
          <div><strong>{copy.unverifiedTitle}</strong><span>{copy.unverifiedBody}</span></div>
          <button type="button" onClick={() => void resendVerification()} disabled={busy || offline}>{copy.resendVerification}</button>
        </div>
      ) : null}

      {showAccount ? (
        <main className="auth-account-page" id="student-main">
          <header className="auth-account-header">
            <div>
              <p className="auth-eyebrow">MODRIK ID</p>
              <h1>{copy.accountTitle}</h1>
              <p>{account?.email ?? copy.accountSummaryUnavailable}</p>
            </div>
          </header>

          <div className="auth-account-grid">
            <section className="auth-security-card" aria-labelledby="sessions-title">
              <div className="auth-card-heading">
                <div><h2 id="sessions-title">{copy.sessionsTitle}</h2><p>{otherSessions.length === 0 && sessionsState === "ready" ? copy.noOtherSessions : ""}</p></div>
                <button type="button" onClick={() => void loadSessions()} disabled={sessionsState === "loading" || offline}>{copy.retry}</button>
              </div>
              {sessionsState === "loading" ? <p role="status" aria-live="polite">{copy.sessionsLoading}</p> : null}
              {sessionsState === "error" ? <div className="auth-inline-error" role="alert"><span>{copy.genericError}</span><button type="button" onClick={() => void loadSessions()}>{copy.retry}</button></div> : null}
              {sessionsState === "ready" ? (
                <ul className="auth-session-list">
                  {sessions.map((session) => (
                    <li key={session.id}>
                      <div><strong>{session.is_current ? copy.currentSession : session.name ?? copy.sessionsTitle}</strong><span>{copy.lastUsed}: {new Intl.DateTimeFormat(localeDate, { dateStyle: "medium", timeStyle: "short" }).format(new Date(session.last_used_at))}</span></div>
                      {session.is_current ? <span className="auth-current-pill">{copy.currentSession}</span> : null}
                    </li>
                  ))}
                </ul>
              ) : null}
              <div className="auth-actions-row">
                <button type="button" onClick={() => void revokeOthers()} disabled={busy || offline || otherSessions.length === 0}>{copy.revokeOthers}</button>
                <button type="button" className="auth-danger-outline" onClick={() => void revokeAll()} disabled={busy || offline}>{copy.revokeAll}</button>
              </div>
            </section>

            <section className="auth-security-card" aria-labelledby="provider-title">
              <h2 id="provider-title">{copy.providerTitle}</h2>
              <p>{copy.accountSummaryUnavailable}</p>
              <div className="auth-provider-grid">
                <button type="button" onClick={() => void beginProvider("google", "link")} disabled={busy || offline}>{copy.providerLink} {copy.providerGoogle}</button>
                <button type="button" onClick={() => void beginProvider("apple", "link")} disabled={busy || offline}>{copy.providerLink} {copy.providerApple}</button>
              </div>
            </section>

            {recentAuthRequired ? (
              <section className="auth-security-card auth-permission-card" aria-labelledby="recent-auth-title">
                <h2 id="recent-auth-title">{copy.recentAuthTitle}</h2>
                <p>{copy.recentAuthBody}</p>
                <form className="auth-form" onSubmit={handleReauthenticate}>
                  <label>{copy.password}<input name="password" type="password" required minLength={12} maxLength={128} autoComplete="current-password" /></label>
                  <button className="auth-primary" type="submit" disabled={busy || offline}>{copy.reauthenticate}</button>
                </form>
              </section>
            ) : null}

            <section className="auth-security-card" aria-labelledby="password-title">
              <h2 id="password-title">{copy.changePasswordTitle}</h2>
              <form className="auth-form" onSubmit={handlePasswordChange}>
                <label>{copy.currentPassword}<input name="current_password" type="password" required minLength={12} maxLength={128} autoComplete="current-password" /></label>
                <label>{copy.newPassword}<input name="new_password" type="password" required minLength={12} maxLength={128} autoComplete="new-password" /></label>
                <button className="auth-primary" type="submit" disabled={busy || offline}>{copy.changePassword}</button>
              </form>
            </section>

            <section className="auth-security-card auth-danger-card" aria-labelledby="delete-title">
              <h2 id="delete-title">{copy.deleteTitle}</h2>
              <p>{copy.deleteWarning}</p>
              <form className="auth-form" onSubmit={handleDelete}>
                <label>{copy.deleteConfirm}<input name="confirmation" required pattern="DELETE" dir="ltr" autoComplete="off" /></label>
                <button className="auth-danger" type="submit" disabled={busy || offline}>{copy.deleteAccount}</button>
              </form>
            </section>
          </div>
        </main>
      ) : (
        <LearningWorkspace />
      )}
    </section>
  );
}
