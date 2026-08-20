"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  learningApi,
  LearningApiError,
  type AcademicContext,
  type Attempt,
  type AttemptResult,
  type Lesson,
  type Locale,
  type Progress,
  type Session,
} from "@/lib/learning-api";
import { directionForLocale, localize, studentCopy } from "./student-copy";

const fixtureLessonId = "01J00000000000000000000003";
const activeAttemptStorageKey = "modrik.student.active-attempt";

type ViewState = "loading" | "ready" | "offline" | "error" | "permission";
type WorkspaceView = "home" | "study" | "practice" | "progress";

function commandKey(scope: string) {
  const storageKey = `modrik.fixture.command.${scope}`;
  const existing = window.localStorage.getItem(storageKey);
  if (existing) return existing;
  const value = `modrik-${scope}-${crypto.randomUUID()}`;
  window.localStorage.setItem(storageKey, value);
  return value;
}

function acknowledge(scope: string) {
  window.localStorage.removeItem(`modrik.fixture.command.${scope}`);
}

function attemptStatusLabel(status: Attempt["status"], locale: Locale) {
  const labels = studentCopy[locale];
  return {
    in_progress: labels.statusInProgress,
    submitted: labels.statusSubmitted,
    graded: labels.statusGraded,
    abandoned: labels.statusAbandoned,
  }[status];
}

export default function LearningWorkspace() {
  const [locale, setLocale] = useState<Locale>("en");
  const [state, setState] = useState<ViewState>("loading");
  const [view, setView] = useState<WorkspaceView>("home");
  const [session, setSession] = useState<Session | null>(null);
  const [context, setContext] = useState<AcademicContext | null>(null);
  const [lesson, setLesson] = useState<Lesson | null>(null);
  const [progress, setProgress] = useState<Progress[]>([]);
  const [attempt, setAttempt] = useState<Attempt | null>(null);
  const [result, setResult] = useState<AttemptResult | null>(null);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [savedAnswers, setSavedAnswers] = useState<Record<string, string>>({});
  const [revisions, setRevisions] = useState<Record<string, number>>({});
  const [busy, setBusy] = useState<"start" | "submit" | null>(null);
  const [message, setMessage] = useState("");
  const mounted = useRef(true);

  const labels = studentCopy[locale];
  const direction = directionForLocale(locale);

  const applyAttempt = useCallback((nextAttempt: Attempt | null) => {
    setAttempt(nextAttempt);
    if (!nextAttempt) {
      setAnswers({});
      setSavedAnswers({});
      setRevisions({});
      return;
    }

    const currentAnswers = Object.fromEntries(
      nextAttempt.questions.map((question) => [
        question.attempt_question_id,
        question.current_answer?.value ?? "",
      ]),
    );
    setAnswers(currentAnswers);
    setSavedAnswers(currentAnswers);
    setRevisions(
      Object.fromEntries(
        nextAttempt.questions.map((question) => [
          question.attempt_question_id,
          question.current_answer?.revision ?? 0,
        ]),
      ),
    );
  }, []);

  const handleError = useCallback((error: unknown) => {
    if (!navigator.onLine) {
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
    if (!navigator.onLine) {
      setState("offline");
      return;
    }

    setState("loading");
    setMessage("");
    try {
      const [nextSession, nextContext] = await Promise.all([
        learningApi.session(),
        learningApi.academicContext(),
      ]);

      let nextLesson: Lesson | null = null;
      let nextProgress: Progress[] = [];
      let restoredAttempt: Attempt | null = null;

      if (nextContext.state === "active") {
        try {
          nextLesson = await learningApi.lesson(fixtureLessonId);
        } catch (error) {
          if (!(error instanceof LearningApiError) || error.status !== 404) throw error;
        }
        nextProgress = await learningApi.progress();

        const storedAttemptId = window.localStorage.getItem(activeAttemptStorageKey);
        if (storedAttemptId) {
          try {
            const candidate = await learningApi.attempt(storedAttemptId);
            if (candidate.status === "in_progress") {
              restoredAttempt = candidate;
            } else {
              window.localStorage.removeItem(activeAttemptStorageKey);
            }
          } catch (error) {
            if (error instanceof LearningApiError && error.status === 404) {
              window.localStorage.removeItem(activeAttemptStorageKey);
            } else {
              throw error;
            }
          }
        }
      } else {
        window.localStorage.removeItem(activeAttemptStorageKey);
      }

      if (!mounted.current) return;
      setSession(nextSession);
      setLocale(nextSession.locale);
      setContext(nextContext);
      setLesson(nextLesson);
      setProgress(nextProgress);
      applyAttempt(restoredAttempt);
      setResult(null);
      setState("ready");
    } catch (error) {
      if (mounted.current) handleError(error);
    }
  }, [applyAttempt, handleError]);

  useEffect(() => {
    mounted.current = true;
    queueMicrotask(() => void load());
    const offline = () => setState("offline");
    const online = () => void load();
    window.addEventListener("offline", offline);
    window.addEventListener("online", online);
    return () => {
      mounted.current = false;
      window.removeEventListener("offline", offline);
      window.removeEventListener("online", online);
    };
  }, [load]);

  async function startPractice() {
    if (!lesson || !navigator.onLine) {
      setState("offline");
      return;
    }

    setBusy("start");
    setMessage("");
    setView("practice");
    const scope = `start.${lesson.practice_quiz_id}`;
    try {
      const nextAttempt = await learningApi.startAttempt(
        lesson.practice_quiz_id,
        commandKey(scope),
      );
      acknowledge(scope);
      window.localStorage.setItem(activeAttemptStorageKey, nextAttempt.id);
      applyAttempt(nextAttempt);
      setResult(null);
      setState("ready");
    } catch (error) {
      handleError(error);
    } finally {
      setBusy(null);
    }
  }

  async function reconcileConflict() {
    if (!attempt) return false;
    try {
      const authoritativeAttempt = await learningApi.attempt(attempt.id);
      if (authoritativeAttempt.status === "in_progress") {
        window.localStorage.setItem(activeAttemptStorageKey, authoritativeAttempt.id);
      } else {
        window.localStorage.removeItem(activeAttemptStorageKey);
      }
      applyAttempt(authoritativeAttempt);
      setMessage(studentCopy[locale].conflict);
      setState("ready");
      return true;
    } catch (error) {
      handleError(error);
      return false;
    }
  }

  async function submitPractice() {
    if (!attempt || !navigator.onLine) {
      setState("offline");
      return;
    }
    if (attempt.questions.some((question) => !answers[question.attempt_question_id]?.trim())) {
      setMessage(labels.answerRequired);
      return;
    }

    setBusy("submit");
    setMessage("");
    try {
      const nextRevisions = { ...revisions };
      const nextSavedAnswers = { ...savedAnswers };
      for (const question of attempt.questions) {
        const questionId = question.attempt_question_id;
        if (nextSavedAnswers[questionId] === answers[questionId]) continue;
        const expectedRevision = nextRevisions[questionId] ?? 0;
        const scope = `answer.${attempt.id}.${questionId}.${expectedRevision + 1}`;
        const saved = await learningApi.answer(
          attempt.id,
          questionId,
          expectedRevision,
          answers[questionId],
          commandKey(scope),
        );
        acknowledge(scope);
        nextRevisions[questionId] = saved.revision;
        nextSavedAnswers[questionId] = saved.value;
        setRevisions({ ...nextRevisions });
        setSavedAnswers({ ...nextSavedAnswers });
      }

      const submitScope = `submit.${attempt.id}`;
      const submitted = await learningApi.submit(attempt.id, commandKey(submitScope));
      acknowledge(submitScope);
      window.localStorage.removeItem(activeAttemptStorageKey);
      setResult(submitted);
      applyAttempt(submitted.attempt);
      setProgress(await learningApi.progress());
      setState("ready");
    } catch (error) {
      if (error instanceof LearningApiError && error.status === 409) {
        await reconcileConflict();
      } else {
        handleError(error);
      }
    } finally {
      setBusy(null);
    }
  }

  const progressAverage = useMemo(() => {
    if (progress.length === 0) return null;
    return Math.round(
      (progress.reduce((sum, snapshot) => sum + snapshot.mastery, 0) / progress.length) * 100,
    );
  }, [progress]);

  const answeredCount = attempt
    ? attempt.questions.filter((question) => answers[question.attempt_question_id]?.trim()).length
    : 0;
  const showWorkspace = state === "ready" || (state === "offline" && (session !== null || lesson !== null));
  const pageTitle = {
    home: labels.homeTitle,
    study: labels.studyTitle,
    practice: labels.practiceTitle,
    progress: labels.progressTitle,
  }[view];

  const navItems: Array<{ id: WorkspaceView; label: string; marker: string }> = [
    { id: "home", label: labels.home, marker: "01" },
    { id: "study", label: labels.study, marker: "02" },
    { id: "practice", label: labels.practice, marker: "03" },
    { id: "progress", label: labels.progress, marker: "04" },
  ];

  return (
    <section lang={locale} dir={direction} className="student-shell">
      <a className="skip-link" href="#student-main">{labels.skip}</a>
      <div className="student-frame">
        <aside className="student-sidebar" aria-label={labels.navigation}>
          <div className="brand-lockup" aria-label="MODRIK مُدرك">
            <span>
              <strong>MODRIK</strong>
              <small lang="ar" dir="rtl">مُدرك</small>
            </span>
          </div>

          <nav className="student-nav" aria-label={labels.navigation}>
            {navItems.map((item) => (
              <button
                type="button"
                key={item.id}
                className="nav-item"
                aria-current={view === item.id ? "page" : undefined}
                onClick={() => setView(item.id)}
              >
                <span className="nav-marker" aria-hidden="true">{item.marker}</span>
                <span>{item.label}</span>
              </button>
            ))}
          </nav>

          <div className="sidebar-status" aria-label={labels.serviceStatus}>
            <span className={`status-dot status-${state}`} aria-hidden="true" />
            <span>{state === "offline" ? labels.offlineStatus : labels.serviceStatus}</span>
          </div>
        </aside>

        <div className="student-stage">
          <header className="student-topbar">
            <div>
              <p className="eyebrow">{labels.appName}</p>
              <h1 id="workspace-title">{pageTitle}</h1>
              <p className="fixture-note">{labels.synthetic}</p>
            </div>
            <fieldset className="locale-switcher">
              <legend className="sr-only">{labels.languageSelector}</legend>
              {(["ar", "en", "fr"] as const).map((language) => (
                <button
                  type="button"
                  key={language}
                  lang={language}
                  aria-pressed={locale === language}
                  onClick={() => setLocale(language)}
                >
                  {language.toUpperCase()}
                </button>
              ))}
            </fieldset>
          </header>

          {state === "offline" && showWorkspace && (
            <div className="offline-banner" role="status" aria-live="polite">
              <div>
                <strong>{labels.offline}</strong>
                <span>{labels.stale}</span>
              </div>
              <button type="button" onClick={() => void load()}>{labels.retry}</button>
            </div>
          )}

          <main id="student-main" className="student-main" aria-labelledby="workspace-title">
            {!showWorkspace ? (
              <div className="state-panel" role={state === "loading" ? "status" : "alert"} aria-live="polite">
                <div className="state-wordmark" aria-hidden="true">MODRIK</div>
                <p>
                  {state === "loading" && labels.loading}
                  {state === "offline" && labels.offline}
                  {state === "permission" && labels.permission}
                  {state === "error" && labels.unavailable}
                </p>
                {state !== "loading" && (
                  <button type="button" className="primary-button" onClick={() => void load()}>
                    {labels.retry}
                  </button>
                )}
              </div>
            ) : (
              <>
                {view === "home" && (
                  <div className="dashboard-stack">
                    <section className="dashboard-hero" aria-labelledby="dashboard-lesson-title">
                      <div>
                        <p className="eyebrow">{labels.currentLesson}</p>
                        <h2 id="dashboard-lesson-title" dir="auto">
                          {lesson ? localize(lesson.title, locale) : labels.lessonEmpty}
                        </h2>
                        <p>{labels.homeSubtitle}</p>
                      </div>
                      <button
                        type="button"
                        className="primary-button"
                        disabled={!lesson}
                        onClick={() => setView("study")}
                      >
                        {labels.continueStudy}
                      </button>
                    </section>

                    <section className="metric-grid" aria-label={labels.homeTitle}>
                      <article className="metric-card">
                        <span>{labels.academicContext}</span>
                        <strong>{context?.state === "active" ? labels.active : labels.onboarding}</strong>
                        <small>{context?.state === "active" ? context.year_level : "—"}</small>
                      </article>
                      <article className="metric-card">
                        <span>{labels.contentVersion}</span>
                        <strong>{lesson ? `v${lesson.content_version}` : "—"}</strong>
                        <small>{lesson ? labels.lessonStatus : labels.lessonEmpty}</small>
                      </article>
                      <article className="metric-card">
                        <span>{labels.mastery}</span>
                        <strong>{progressAverage === null ? "—" : `${progressAverage}%`}</strong>
                        <small>{labels.snapshots}: {progress.length}</small>
                      </article>
                      <article className="metric-card">
                        <span>{labels.attemptStatus}</span>
                        <strong>{attempt ? attemptStatusLabel(attempt.status, locale) : "—"}</strong>
                        <small>{attempt ? labels.attemptReady : labels.noAttempt}</small>
                      </article>
                    </section>

                    <div className="dashboard-columns">
                      <section className="context-panel" aria-labelledby="academic-context-heading">
                        <div className="section-heading-row">
                          <div>
                            <p className="eyebrow">{labels.academicContext}</p>
                            <h2 id="academic-context-heading">
                              {context?.state === "active" ? labels.active : labels.onboarding}
                            </h2>
                          </div>
                          <span className="status-pill">{context?.state === "active" ? labels.active : labels.onboarding}</span>
                        </div>
                        {context?.state === "active" ? (
                          <dl className="context-definition">
                            <div>
                              <dt>{labels.yearLevel}</dt>
                              <dd>{context.year_level}</dd>
                            </div>
                            <div>
                              <dt>{labels.track}</dt>
                              <dd>{labels.trackConfigured}</dd>
                            </div>
                          </dl>
                        ) : (
                          <p>{labels.onboarding}</p>
                        )}
                        <p className="muted-copy">{labels.contextLocked}</p>
                        <details className="reset-consequence">
                          <summary>{labels.resetReview}</summary>
                          <div>
                            <h3>{labels.resetTitle}</h3>
                            <p>{labels.resetBody}</p>
                            <p className="contract-note">{labels.resetGap}</p>
                          </div>
                        </details>
                      </section>

                      <section className="next-actions" aria-label={labels.navigation}>
                        <button type="button" onClick={() => setView("study")} disabled={!lesson}>
                          <span className="action-index" aria-hidden="true">02</span>
                          <strong>{labels.study}</strong>
                          <small>{labels.openStudy}</small>
                        </button>
                        <button type="button" onClick={() => setView("practice")} disabled={!lesson}>
                          <span className="action-index" aria-hidden="true">03</span>
                          <strong>{attempt ? labels.resume : labels.practice}</strong>
                          <small>{labels.openPractice}</small>
                        </button>
                        <button type="button" onClick={() => setView("progress")}>
                          <span className="action-index" aria-hidden="true">04</span>
                          <strong>{labels.progress}</strong>
                          <small>{labels.openProgress}</small>
                        </button>
                      </section>
                    </div>
                  </div>
                )}

                {view === "study" && (
                  <div className="study-layout">
                    <aside className="workspace-rail" aria-label={labels.currentLesson}>
                      <p className="eyebrow">{labels.currentLesson}</p>
                      <h2 dir="auto">{lesson ? localize(lesson.title, locale) : labels.lessonEmpty}</h2>
                      <dl>
                        <div>
                          <dt>{labels.contentVersion}</dt>
                          <dd>{lesson ? `v${lesson.content_version}` : "—"}</dd>
                        </div>
                        <div>
                          <dt>{labels.yearLevel}</dt>
                          <dd>{context?.state === "active" ? context.year_level : "—"}</dd>
                        </div>
                      </dl>
                      <button type="button" className="secondary-button" disabled={!lesson} onClick={() => setView("practice")}>
                        {labels.openPractice}
                      </button>
                    </aside>
                    <article className="lesson-reader" aria-labelledby="lesson-reader-heading">
                      <header>
                        <p className="eyebrow">{labels.studyTitle}</p>
                        <h2 id="lesson-reader-heading" dir="auto">
                          {lesson ? localize(lesson.title, locale) : labels.lessonEmpty}
                        </h2>
                        <p>{labels.studySubtitle}</p>
                      </header>
                      {lesson ? (
                        <div className="lesson-content">
                          {lesson.blocks.map((block) => (
                            <section key={block.id} className="lesson-block" dir="auto">
                              {block.type === "heading" ? (
                                <h3>{localize(block.content, locale)}</h3>
                              ) : (
                                <p>{localize(block.content, locale)}</p>
                              )}
                            </section>
                          ))}
                        </div>
                      ) : (
                        <div className="empty-panel" role="status"><p>{labels.lessonEmpty}</p></div>
                      )}
                    </article>
                  </div>
                )}

                {view === "practice" && (
                  <div className="practice-layout">
                    <aside className="workspace-rail practice-rail" aria-label={labels.authoritative}>
                      <span className="status-pill">{labels.authoritative}</span>
                      <p>{labels.authoritativeNote}</p>
                      {attempt && (
                        <dl>
                          <div>
                            <dt>{labels.attemptStatus}</dt>
                            <dd>{attemptStatusLabel(attempt.status, locale)}</dd>
                          </div>
                          <div>
                            <dt>{labels.question}</dt>
                            <dd>{answeredCount}/{attempt.questions.length}</dd>
                          </div>
                        </dl>
                      )}
                    </aside>

                    <section className="practice-workbench" aria-labelledby="practice-workbench-heading">
                      <header>
                        <p className="eyebrow">{labels.practice}</p>
                        <h2 id="practice-workbench-heading">{labels.practiceTitle}</h2>
                        <p>{labels.practiceSubtitle}</p>
                      </header>

                      {!attempt ? (
                        <div className="empty-panel practice-empty">
                          <p>{labels.noAttempt}</p>
                          <button
                            type="button"
                            className="primary-button"
                            disabled={busy !== null || state === "offline" || !lesson}
                            onClick={() => void startPractice()}
                          >
                            {busy === "start" ? labels.starting : labels.start}
                          </button>
                        </div>
                      ) : (
                        <form onSubmit={(event) => { event.preventDefault(); void submitPractice(); }}>
                          <div className="question-list">
                            {attempt.questions.map((question) => (
                              <fieldset
                                className="question-card"
                                key={question.attempt_question_id}
                                disabled={attempt.status !== "in_progress" || state === "offline"}
                              >
                                <legend>
                                  <span className="question-number">{labels.question} {question.position}</span>
                                  <span dir="auto">{localize(question.prompt, locale)}</span>
                                </legend>
                                {question.response_contract.kind === "single_choice" ? (
                                  <div className="answer-list">
                                    {question.response_contract.options.map((option) => (
                                      <label key={option.id} className="answer-option">
                                        <input
                                          type="radio"
                                          name={question.attempt_question_id}
                                          value={option.id}
                                          checked={answers[question.attempt_question_id] === option.id}
                                          onChange={(event) => setAnswers((current) => ({
                                            ...current,
                                            [question.attempt_question_id]: event.target.value,
                                          }))}
                                        />
                                        <span dir="auto">{localize(option.label, locale)}</span>
                                      </label>
                                    ))}
                                  </div>
                                ) : (
                                  <label className="text-answer-label">
                                    <span>{labels.textAnswer}</span>
                                    <input
                                      className="text-answer"
                                      dir="auto"
                                      maxLength={question.response_contract.max_length}
                                      value={answers[question.attempt_question_id] ?? ""}
                                      onChange={(event) => setAnswers((current) => ({
                                        ...current,
                                        [question.attempt_question_id]: event.target.value,
                                      }))}
                                    />
                                  </label>
                                )}
                              </fieldset>
                            ))}
                          </div>

                          {attempt.status === "in_progress" && (
                            <div className="practice-submit-row">
                              <span aria-live="polite">{answeredCount}/{attempt.questions.length} {labels.answered}</span>
                              <button type="submit" className="primary-button" disabled={busy !== null || state === "offline"}>
                                {busy === "submit" ? labels.submitting : labels.submit}
                              </button>
                            </div>
                          )}
                        </form>
                      )}

                      {message && <p className="inline-error" role="alert">{message}</p>}
                      {result && (
                        <div className="result-panel" role="status" aria-live="polite">
                          <span>{labels.result}</span>
                          <strong>{result.score}/{result.max_score}</strong>
                          <button
                            type="button"
                            className="secondary-button"
                            disabled={busy !== null || state === "offline" || !lesson}
                            onClick={() => void startPractice()}
                          >
                            {busy === "start" ? labels.starting : labels.start}
                          </button>
                        </div>
                      )}
                    </section>
                  </div>
                )}

                {view === "progress" && (
                  <section className="progress-workspace" aria-labelledby="progress-workspace-heading">
                    <header className="workspace-section-header">
                      <div>
                        <p className="eyebrow">{labels.progress}</p>
                        <h2 id="progress-workspace-heading">{labels.progressTitle}</h2>
                        <p>{labels.progressSubtitle}</p>
                      </div>
                      <div className="mastery-summary" aria-label={labels.mastery}>
                        <span>{labels.mastery}</span>
                        <strong>{progressAverage === null ? "—" : `${progressAverage}%`}</strong>
                      </div>
                    </header>

                    {progress.length === 0 ? (
                      <div className="empty-panel" role="status">
                        <p>{labels.emptyProgress}</p>
                        <button type="button" className="secondary-button" disabled={!lesson} onClick={() => setView("practice")}>
                          {labels.openPractice}
                        </button>
                      </div>
                    ) : (
                      <div className="progress-grid">
                        {progress.map((snapshot) => {
                          const percent = Math.round(snapshot.mastery * 100);
                          return (
                            <article className="progress-card" key={`${snapshot.curriculum_node_id}-${snapshot.source_version}`}>
                              <div>
                                <span>{labels.mastery}</span>
                                <strong>{percent}%</strong>
                              </div>
                              <progress max="1" value={snapshot.mastery} aria-label={`${labels.mastery}: ${percent}%`} />
                              <small>{labels.contentVersion} {snapshot.source_version}</small>
                            </article>
                          );
                        })}
                      </div>
                    )}
                  </section>
                )}
              </>
            )}
          </main>
        </div>
      </div>
    </section>
  );
}
