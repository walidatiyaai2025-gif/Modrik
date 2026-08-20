"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import {
  learningApi,
  LearningApiError,
  type AcademicContext,
  type Attempt,
  type AttemptResult,
  type Lesson,
  type Locale,
  type LocalizedText,
  type Progress,
  type Session,
} from "@/lib/learning-api";

const fixtureLessonId = "01J00000000000000000000003";

const copy = {
  en: {
    loading: "Loading your fixture learning workspace…",
    offline: "You appear to be offline. Reconnect to load or sync this lesson.",
    unavailable: "The learning service is unavailable.",
    permission: "This fixture session is not available. Check the local Backend fixture configuration.",
    retry: "Retry",
    context: "Academic context",
    lesson: "Study",
    practice: "Practice",
    progress: "Progress",
    start: "Start a new practice",
    starting: "Starting…",
    submit: "Save answers and submit",
    submitting: "Saving…",
    answerRequired: "Answer every question before submitting.",
    emptyProgress: "Complete the practice to create your first progress snapshot.",
    result: "Practice result",
    fixture: "Synthetic fixture · no real curriculum",
  },
  ar: {
    loading: "جارٍ تحميل مساحة التعلّم التجريبية…",
    offline: "يبدو أنك غير متصل. أعد الاتصال لتحميل الدرس أو مزامنته.",
    unavailable: "خدمة التعلّم غير متاحة.",
    permission: "الجلسة التجريبية غير متاحة. تحقق من إعدادات الخادم المحلي.",
    retry: "إعادة المحاولة",
    context: "السياق الأكاديمي",
    lesson: "المذاكرة",
    practice: "التدريب",
    progress: "التقدّم",
    start: "ابدأ تدريبًا جديدًا",
    starting: "جارٍ البدء…",
    submit: "احفظ الإجابات وأرسل",
    submitting: "جارٍ الحفظ…",
    answerRequired: "أجب عن جميع الأسئلة قبل الإرسال.",
    emptyProgress: "أكمل التدريب لإنشاء أول سجل للتقدّم.",
    result: "نتيجة التدريب",
    fixture: "محتوى اصطناعي تجريبي · ليس منهجًا حقيقيًا",
  },
  fr: {
    loading: "Chargement de l’espace d’apprentissage de test…",
    offline: "Vous semblez hors ligne. Reconnectez-vous pour charger ou synchroniser la leçon.",
    unavailable: "Le service d’apprentissage est indisponible.",
    permission: "La session de test est indisponible. Vérifiez la configuration locale du Backend.",
    retry: "Réessayer",
    context: "Contexte académique",
    lesson: "Étude",
    practice: "Exercice",
    progress: "Progression",
    start: "Commencer un nouvel exercice",
    starting: "Démarrage…",
    submit: "Enregistrer et envoyer",
    submitting: "Enregistrement…",
    answerRequired: "Répondez à toutes les questions avant l’envoi.",
    emptyProgress: "Terminez l’exercice pour créer votre premier relevé de progression.",
    result: "Résultat de l’exercice",
    fixture: "Contenu synthétique de test · aucun programme réel",
  },
} as const;

type ViewState = "loading" | "ready" | "offline" | "error" | "permission";

function localize(value: LocalizedText, locale: Locale) {
  return value[locale] ?? value.en ?? value.ar ?? value.fr ?? "";
}

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

export default function LearningWorkspace() {
  const [locale, setLocale] = useState<Locale>("en");
  const [state, setState] = useState<ViewState>("loading");
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

  const handleError = useCallback((error: unknown) => {
    if (!navigator.onLine) {
      setState("offline");
      return;
    }
    if (error instanceof LearningApiError && error.status === 401) {
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
      const [nextSession, nextContext, nextLesson, nextProgress] = await Promise.all([
        learningApi.session(),
        learningApi.academicContext(),
        learningApi.lesson(fixtureLessonId),
        learningApi.progress(),
      ]);
      if (!mounted.current) return;
      setSession(nextSession);
      setLocale(nextSession.locale);
      setContext(nextContext);
      setLesson(nextLesson);
      setProgress(nextProgress);
      setState("ready");
    } catch (error) {
      if (mounted.current) handleError(error);
    }
  }, [handleError]);

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
    const scope = `start.${lesson.practice_quiz_id}`;
    try {
      const nextAttempt = await learningApi.startAttempt(lesson.practice_quiz_id, commandKey(scope));
      acknowledge(scope);
      setAttempt(nextAttempt);
      setResult(null);
      const existingAnswers = Object.fromEntries(nextAttempt.questions.map((question) => [question.attempt_question_id, question.current_answer?.value ?? ""]));
      setAnswers(existingAnswers);
      setSavedAnswers(existingAnswers);
      setRevisions(Object.fromEntries(nextAttempt.questions.map((question) => [question.attempt_question_id, question.current_answer?.revision ?? 0])));
    } catch (error) {
      handleError(error);
    } finally {
      setBusy(null);
    }
  }

  async function submitPractice() {
    if (!attempt || !navigator.onLine) {
      setState("offline");
      return;
    }
    if (attempt.questions.some((question) => !answers[question.attempt_question_id]?.trim())) {
      setMessage(copy[locale].answerRequired);
      return;
    }

    setBusy("submit");
    setMessage("");
    try {
      const nextRevisions = { ...revisions };
      for (const question of attempt.questions) {
        const questionId = question.attempt_question_id;
        if (savedAnswers[questionId] === answers[questionId]) continue;
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
        setRevisions({ ...nextRevisions });
        setSavedAnswers((current) => ({ ...current, [questionId]: saved.value }));
      }

      const submitScope = `submit.${attempt.id}`;
      const submitted = await learningApi.submit(attempt.id, commandKey(submitScope));
      acknowledge(submitScope);
      setResult(submitted);
      setAttempt(submitted.attempt);
      setProgress(await learningApi.progress());
    } catch (error) {
      handleError(error);
    } finally {
      setBusy(null);
    }
  }

  const labels = copy[locale];
  const direction = locale === "ar" ? "rtl" : "ltr";
  const showWorkspace = state === "ready" || (state === "offline" && lesson !== null);

  return (
    <section lang={locale} dir={direction} className="workspace-shell" aria-labelledby="workspace-title">
      <header className="workspace-header">
        <div>
          <p className="eyebrow">MODRIK | مُدرك</p>
          <h1 id="workspace-title">{lesson ? localize(lesson.title, locale) : "Fixture learning workspace"}</h1>
          <p className="fixture-note">{labels.fixture}</p>
        </div>
        <fieldset className="locale-switcher">
          <legend className="sr-only">Interface language</legend>
          {(["ar", "en", "fr"] as const).map((language) => (
            <button
              type="button"
              key={language}
              aria-pressed={locale === language}
              onClick={() => setLocale(language)}
            >
              {language.toUpperCase()}
            </button>
          ))}
        </fieldset>
      </header>

      {state === "offline" && lesson !== null && (
        <div className="offline-banner" role="status" aria-live="polite">
          <span>{labels.offline}</span>
          <button type="button" onClick={() => void load()}>{labels.retry}</button>
        </div>
      )}

      {!showWorkspace ? (
        <div className="state-panel" role={state === "loading" ? "status" : "alert"} aria-live="polite">
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
        <div className="workspace-grid">
          <aside className="context-card" aria-labelledby="context-heading">
            <p className="step-number">01</p>
            <h2 id="context-heading">{labels.context}</h2>
            <p>{session?.roles.join(", ")}</p>
            <p>{context?.state === "active" ? context.year_level : "Onboarding required"}</p>
          </aside>

          <article className="lesson-card" aria-labelledby="lesson-heading">
            <p className="step-number">02</p>
            <h2 id="lesson-heading">{labels.lesson}</h2>
            {lesson?.blocks.map((block) => (
              <section key={block.id} className="lesson-block">
                {block.type === "heading" ? (
                  <h3>{localize(block.content, locale)}</h3>
                ) : (
                  <p>{localize(block.content, locale)}</p>
                )}
              </section>
            ))}
          </article>

          <section className="practice-card" aria-labelledby="practice-heading">
            <p className="step-number">03</p>
            <h2 id="practice-heading">{labels.practice}</h2>
            {!attempt ? (
              <button type="button" className="primary-button" disabled={busy !== null} onClick={() => void startPractice()}>
                {busy === "start" ? labels.starting : labels.start}
              </button>
            ) : (
              <form onSubmit={(event) => { event.preventDefault(); void submitPractice(); }}>
                {attempt.questions.map((question) => (
                  <fieldset className="question-card" key={question.attempt_question_id} disabled={attempt.status !== "in_progress"}>
                    <legend>
                      {question.position}. {localize(question.prompt, locale)}
                    </legend>
                    {question.response_contract.kind === "single_choice" ? (
                      question.response_contract.options.map((option) => (
                        <label key={option.id} className="answer-option">
                          <input
                            type="radio"
                            name={question.attempt_question_id}
                            value={option.id}
                            checked={answers[question.attempt_question_id] === option.id}
                            onChange={(event) => setAnswers((current) => ({ ...current, [question.attempt_question_id]: event.target.value }))}
                          />
                          <span>{localize(option.label, locale)}</span>
                        </label>
                      ))
                    ) : (
                      <input
                        className="text-answer"
                        maxLength={question.response_contract.max_length}
                        value={answers[question.attempt_question_id] ?? ""}
                        onChange={(event) => setAnswers((current) => ({ ...current, [question.attempt_question_id]: event.target.value }))}
                      />
                    )}
                  </fieldset>
                ))}
                {attempt.status === "in_progress" && (
                  <button type="submit" className="primary-button" disabled={busy !== null}>
                    {busy === "submit" ? labels.submitting : labels.submit}
                  </button>
                )}
              </form>
            )}
            {message && <p className="inline-error" role="alert">{message}</p>}
            {result && (
              <p className="result-panel" role="status">
                {labels.result}: <strong>{result.score}/{result.max_score}</strong>
              </p>
            )}
          </section>

          <section className="progress-card" aria-labelledby="progress-heading">
            <p className="step-number">04</p>
            <h2 id="progress-heading">{labels.progress}</h2>
            {progress.length === 0 ? (
              <p>{labels.emptyProgress}</p>
            ) : (
              progress.map((snapshot) => (
                <div key={`${snapshot.curriculum_node_id}-${snapshot.source_version}`}>
                  <p className="progress-value">{Math.round(snapshot.mastery * 100)}%</p>
                  <progress max="1" value={snapshot.mastery} aria-label={`${labels.progress}: ${Math.round(snapshot.mastery * 100)}%`} />
                </div>
              ))
            )}
          </section>
        </div>
      )}
    </section>
  );
}
