"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  learningApi,
  LearningApiError,
  type AcademicContext,
  type Attempt,
  type AttemptResult,
  type CatalogueAssessment,
  type CatalogueNode,
  type ContentCatalogue,
  type Lesson,
  type Locale,
  type Progress,
  type Session,
} from "@/lib/learning-api";
import AcademicTrackSelector from "./academic-track-selector";
import { directionForLocale, localize, studentCopy } from "./student-copy";

const activeAttemptStorageKey = "modrik.student.active-attempt";

type ViewState = "loading" | "ready" | "offline" | "error" | "permission";
type WorkspaceView = "catalogue" | "study" | "practice" | "progress" | "academic";

const catalogueCopy = {
  en: {
    catalogue: "Published content",
    chooseSubject: "Choose subject",
    chooseLesson: "Choose a lesson",
    noContent: "No published content is available for this academic context yet.",
    lessons: "Lessons",
    assessments: "Practice & exams",
    openLesson: "Open lesson",
    openAssessment: "Open assessment",
    practice: "Practice",
    quiz: "Quiz",
    mock_exam: "Mock exam",
    backCatalogue: "Back to content",
    trackContent: "Published curriculum for your active track",
    publishedOnly: "Only published lessons and assessments are shown.",
  },
  ar: {
    catalogue: "المحتوى المنشور",
    chooseSubject: "اختر المادة",
    chooseLesson: "اختر درسًا",
    noContent: "لا يوجد محتوى منشور لهذا المسار الأكاديمي حتى الآن.",
    lessons: "الدروس",
    assessments: "التدريبات والاختبارات",
    openLesson: "فتح الدرس",
    openAssessment: "فتح الاختبار",
    practice: "تدريب",
    quiz: "اختبار",
    mock_exam: "اختبار تجريبي",
    backCatalogue: "العودة للمحتوى",
    trackContent: "المنهج المنشور لمسارك الأكاديمي الحالي",
    publishedOnly: "تظهر هنا الدروس والتدريبات والاختبارات المنشورة فقط.",
  },
  fr: {
    catalogue: "Contenu publié",
    chooseSubject: "Choisir la matière",
    chooseLesson: "Choisir une leçon",
    noContent: "Aucun contenu publié n’est disponible pour ce parcours pour le moment.",
    lessons: "Leçons",
    assessments: "Exercices et examens",
    openLesson: "Ouvrir la leçon",
    openAssessment: "Ouvrir l’évaluation",
    practice: "Exercice",
    quiz: "Quiz",
    mock_exam: "Examen blanc",
    backCatalogue: "Retour au contenu",
    trackContent: "Programme publié pour votre parcours actif",
    publishedOnly: "Seules les leçons et évaluations publiées sont affichées.",
  },
} as const;

function operationKey(scope: string) {
  const storageKey = `modrik.student.command.${scope}`;
  const existing = window.localStorage.getItem(storageKey);
  if (existing) return existing;
  const value = `modrik-${scope}-${crypto.randomUUID()}`;
  window.localStorage.setItem(storageKey, value);
  return value;
}

function acknowledge(scope: string) {
  window.localStorage.removeItem(`modrik.student.command.${scope}`);
}

function flattenAssessments(node: CatalogueNode): CatalogueAssessment[] {
  return [node.assessments, ...node.children.map(flattenAssessments)].flat();
}

export default function LearningWorkspace() {
  const [locale, setLocale] = useState<Locale>("en");
  const [state, setState] = useState<ViewState>("loading");
  const [view, setView] = useState<WorkspaceView>("catalogue");
  const [session, setSession] = useState<Session | null>(null);
  const [context, setContext] = useState<AcademicContext | null>(null);
  const [catalogue, setCatalogue] = useState<ContentCatalogue | null>(null);
  const [selectedSubjectReference, setSelectedSubjectReference] = useState("");
  const [lesson, setLesson] = useState<Lesson | null>(null);
  const [progress, setProgress] = useState<Progress[]>([]);
  const [selectedAssessment, setSelectedAssessment] = useState<CatalogueAssessment | null>(null);
  const [attempt, setAttempt] = useState<Attempt | null>(null);
  const [result, setResult] = useState<AttemptResult | null>(null);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [savedAnswers, setSavedAnswers] = useState<Record<string, string>>({});
  const [revisions, setRevisions] = useState<Record<string, number>>({});
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const mounted = useRef(true);

  const labels = studentCopy[locale];
  const copy = catalogueCopy[locale];
  const direction = directionForLocale(locale);

  const selectedSubject = useMemo(() => {
    if (!catalogue || catalogue.state !== "active") return null;
    return catalogue.subjects.find((subject) => subject.reference === selectedSubjectReference)
      ?? catalogue.subjects[0]
      ?? null;
  }, [catalogue, selectedSubjectReference]);

  const allAssessments = useMemo(
    () => selectedSubject ? flattenAssessments(selectedSubject) : [],
    [selectedSubject],
  );

  const applyAttempt = useCallback((nextAttempt: Attempt | null) => {
    setAttempt(nextAttempt);
    if (!nextAttempt) {
      setAnswers({});
      setSavedAnswers({});
      setRevisions({});
      return;
    }
    const nextAnswers = Object.fromEntries(nextAttempt.questions.map((question) => [
      question.attempt_question_id,
      question.current_answer?.value ?? "",
    ]));
    setAnswers(nextAnswers);
    setSavedAnswers(nextAnswers);
    setRevisions(Object.fromEntries(nextAttempt.questions.map((question) => [
      question.attempt_question_id,
      question.current_answer?.revision ?? 0,
    ])));
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
      let nextCatalogue: ContentCatalogue | null = null;
      let nextProgress: Progress[] = [];
      let restoredAttempt: Attempt | null = null;
      if (nextContext.state === "active") {
        [nextCatalogue, nextProgress] = await Promise.all([
          learningApi.contentCatalogue(),
          learningApi.progress(),
        ]);
        const storedAttemptId = window.localStorage.getItem(activeAttemptStorageKey);
        if (storedAttemptId) {
          try {
            const candidate = await learningApi.attempt(storedAttemptId);
            if (candidate.status === "in_progress") restoredAttempt = candidate;
            else window.localStorage.removeItem(activeAttemptStorageKey);
          } catch (error) {
            if (error instanceof LearningApiError && error.status === 404) {
              window.localStorage.removeItem(activeAttemptStorageKey);
            } else throw error;
          }
        }
      } else {
        window.localStorage.removeItem(activeAttemptStorageKey);
      }
      if (!mounted.current) return;
      setSession(nextSession);
      setLocale(nextSession.locale);
      setContext(nextContext);
      setCatalogue(nextCatalogue);
      if (nextCatalogue?.state === "active") {
        setSelectedSubjectReference((current) =>
          nextCatalogue.subjects.some((subject) => subject.reference === current)
            ? current
            : nextCatalogue.subjects[0]?.reference ?? "",
        );
      } else {
        setSelectedSubjectReference("");
      }
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

  async function openLesson(lessonId: string) {
    setBusy(true);
    setMessage("");
    try {
      const nextLesson = await learningApi.lesson(lessonId);
      setLesson(nextLesson);
      setView("study");
      setState("ready");
    } catch (error) {
      handleError(error);
    } finally {
      setBusy(false);
    }
  }

  function openAssessment(assessment: CatalogueAssessment) {
    setSelectedAssessment(assessment);
    setResult(null);
    setView("practice");
  }

  async function startAssessment() {
    if (!selectedAssessment || !navigator.onLine) return;
    setBusy(true);
    setMessage("");
    const scope = `start.${selectedAssessment.id}`;
    try {
      const nextAttempt = await learningApi.startAttempt(selectedAssessment.id, operationKey(scope));
      acknowledge(scope);
      window.localStorage.setItem(activeAttemptStorageKey, nextAttempt.id);
      applyAttempt(nextAttempt);
      setResult(null);
      setState("ready");
    } catch (error) {
      handleError(error);
    } finally {
      setBusy(false);
    }
  }

  async function submitAssessment() {
    if (!attempt || !navigator.onLine) return;
    if (attempt.questions.some((question) => !answers[question.attempt_question_id]?.trim())) {
      setMessage(labels.answerRequired);
      return;
    }
    setBusy(true);
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
          operationKey(scope),
        );
        acknowledge(scope);
        nextRevisions[questionId] = saved.revision;
        nextSavedAnswers[questionId] = saved.value;
      }
      setRevisions(nextRevisions);
      setSavedAnswers(nextSavedAnswers);
      const submitScope = `submit.${attempt.id}`;
      const submitted = await learningApi.submit(attempt.id, operationKey(submitScope));
      acknowledge(submitScope);
      window.localStorage.removeItem(activeAttemptStorageKey);
      setResult(submitted);
      applyAttempt(submitted.attempt);
      setProgress(await learningApi.progress());
    } catch (error) {
      if (error instanceof LearningApiError && error.status === 409) {
        try {
          const latest = await learningApi.attempt(attempt.id);
          applyAttempt(latest);
          setMessage(labels.conflict);
        } catch (reloadError) {
          handleError(reloadError);
        }
      } else {
        handleError(error);
      }
    } finally {
      setBusy(false);
    }
  }

  async function handleAcademicTransition() {
    window.localStorage.removeItem(activeAttemptStorageKey);
    applyAttempt(null);
    setResult(null);
    setLesson(null);
    setSelectedAssessment(null);
    setView("catalogue");
    await load();
  }

  const progressAverage = useMemo(() => {
    if (progress.length === 0) return null;
    return Math.round((progress.reduce((sum, item) => sum + item.mastery, 0) / progress.length) * 100);
  }, [progress]);

  function renderNode(node: CatalogueNode, depth = 0) {
    return (
      <section className="context-panel" key={node.id} data-node-type={node.type}>
        <div className="section-heading-row">
          <div>
            <p className="eyebrow">{node.type}</p>
            <h3 dir="auto">{localize(node.title, locale)}</h3>
          </div>
          <small>{node.reference}</small>
        </div>

        {node.lessons.length > 0 && (
          <div className="next-actions" aria-label={copy.lessons}>
            {node.lessons.map((item) => (
              <button type="button" className="secondary-button" key={item.id} disabled={busy} onClick={() => void openLesson(item.id)}>
                <strong dir="auto">{localize(item.title, locale)}</strong>
                <span>{copy.openLesson}</span>
              </button>
            ))}
          </div>
        )}

        {node.assessments.length > 0 && (
          <div className="next-actions" aria-label={copy.assessments}>
            {node.assessments.map((assessment) => (
              <button type="button" className="secondary-button" key={assessment.id} onClick={() => openAssessment(assessment)}>
                <strong dir="auto">{localize(assessment.title, locale)}</strong>
                <span>{copy[assessment.kind]}</span>
              </button>
            ))}
          </div>
        )}

        {node.children.length > 0 && (
          <div className={depth === 0 ? "dashboard-stack" : "catalogue-children"}>
            {node.children.map((child) => renderNode(child, depth + 1))}
          </div>
        )}
      </section>
    );
  }

  const showWorkspace = state === "ready" || (state === "offline" && session !== null);

  return (
    <section lang={locale} dir={direction} className="student-shell">
      <a className="skip-link" href="#student-main">{labels.skip}</a>
      <div className="student-frame">
        <aside className="student-sidebar" aria-label={labels.navigation}>
          <div className="brand-lockup"><span><strong>MODRIK</strong><small lang="ar" dir="rtl">مُدرك</small></span></div>
          <nav className="student-nav" aria-label={labels.navigation}>
            <button type="button" className="nav-item" aria-current={view === "catalogue" ? "page" : undefined} onClick={() => setView("catalogue")}><span className="nav-marker">01</span><span>{copy.catalogue}</span></button>
            <button type="button" className="nav-item" aria-current={view === "study" ? "page" : undefined} onClick={() => setView("study")}><span className="nav-marker">02</span><span>{labels.study}</span></button>
            <button type="button" className="nav-item" aria-current={view === "practice" ? "page" : undefined} onClick={() => setView("practice")}><span className="nav-marker">03</span><span>{labels.practice}</span></button>
            <button type="button" className="nav-item" aria-current={view === "progress" ? "page" : undefined} onClick={() => setView("progress")}><span className="nav-marker">04</span><span>{labels.progress}</span></button>
            <button type="button" className="nav-item" aria-current={view === "academic" ? "page" : undefined} onClick={() => setView("academic")}><span className="nav-marker">05</span><span>{labels.academicTrack}</span></button>
          </nav>
        </aside>

        <div className="student-stage">
          <header className="student-topbar">
            <div>
              <p className="eyebrow">{labels.appName}</p>
              <h1>{view === "catalogue" ? copy.catalogue : view === "study" ? labels.studyTitle : view === "practice" ? labels.practiceTitle : view === "progress" ? labels.progressTitle : labels.academicTrackTitle}</h1>
              <p>{copy.publishedOnly}</p>
            </div>
            <fieldset className="locale-switcher">
              <legend className="sr-only">{labels.languageSelector}</legend>
              {(["ar", "en", "fr"] as const).map((language) => (
                <button type="button" key={language} lang={language} aria-pressed={locale === language} onClick={() => setLocale(language)}>{language.toUpperCase()}</button>
              ))}
            </fieldset>
          </header>

          <main id="student-main" className="student-main">
            {!showWorkspace ? (
              <div className="state-panel" role={state === "loading" ? "status" : "alert"}>
                <p>{state === "loading" ? labels.loading : state === "permission" ? labels.permission : state === "offline" ? labels.offline : labels.unavailable}</p>
                {state !== "loading" && <button type="button" className="primary-button" onClick={() => void load()}>{labels.retry}</button>}
              </div>
            ) : context?.state !== "active" ? (
              <AcademicTrackSelector context={context} locale={locale} offline={state === "offline"} onTransitioned={handleAcademicTransition} />
            ) : view === "academic" ? (
              <AcademicTrackSelector context={context} locale={locale} offline={state === "offline"} onTransitioned={handleAcademicTransition} />
            ) : view === "catalogue" ? (
              <div className="dashboard-stack">
                <section className="dashboard-hero">
                  <div>
                    <p className="eyebrow">{copy.trackContent}</p>
                    <h2 dir="auto">{catalogue?.state === "active" ? localize(catalogue.context.track_title, locale) : context.year_level}</h2>
                    <p>{catalogue?.state === "active" ? `${catalogue.counts.lessons} ${copy.lessons} · ${catalogue.counts.assessments} ${copy.assessments}` : copy.noContent}</p>
                  </div>
                </section>

                {catalogue?.state === "active" && catalogue.subjects.length > 0 ? (
                  <>
                    <section className="context-panel">
                      <div className="section-heading-row"><h2>{copy.chooseSubject}</h2></div>
                      <div className="next-actions">
                        {catalogue.subjects.map((subject) => (
                          <button
                            type="button"
                            key={subject.id}
                            className={selectedSubject?.id === subject.id ? "primary-button" : "secondary-button"}
                            onClick={() => setSelectedSubjectReference(subject.reference)}
                          >
                            {localize(subject.title, locale)}
                          </button>
                        ))}
                      </div>
                    </section>
                    {selectedSubject ? renderNode(selectedSubject) : null}
                  </>
                ) : (
                  <div className="empty-panel"><p>{copy.noContent}</p></div>
                )}
              </div>
            ) : view === "study" ? (
              <div className="study-layout">
                <section className="context-panel">
                  <div className="section-heading-row">
                    <div><p className="eyebrow">{labels.study}</p><h2 dir="auto">{lesson ? localize(lesson.title, locale) : labels.lessonEmpty}</h2></div>
                    <button type="button" className="secondary-button" onClick={() => setView("catalogue")}>{copy.backCatalogue}</button>
                  </div>
                  {lesson ? lesson.blocks.map((block) => (
                    <article key={block.id} className="lesson-block" data-block-type={block.type}>
                      {block.type === "heading" ? <h3 dir="auto">{localize(block.content, locale)}</h3> : <p dir="auto">{localize(block.content, locale)}</p>}
                    </article>
                  )) : <p>{copy.chooseLesson}</p>}
                </section>
              </div>
            ) : view === "practice" ? (
              <div className="practice-layout">
                <section className="practice-workbench">
                  <div className="section-heading-row">
                    <div><p className="eyebrow">{labels.practice}</p><h2 dir="auto">{selectedAssessment ? localize(selectedAssessment.title, locale) : labels.noAttempt}</h2></div>
                    <button type="button" className="secondary-button" onClick={() => setView("catalogue")}>{copy.backCatalogue}</button>
                  </div>

                  {!attempt && selectedAssessment && !result ? (
                    <button type="button" className="primary-button" disabled={busy || state === "offline"} onClick={() => void startAssessment()}>
                      {busy ? labels.starting : labels.start}
                    </button>
                  ) : null}

                  {attempt?.status === "in_progress" ? (
                    <form onSubmit={(event) => { event.preventDefault(); void submitAssessment(); }}>
                      <div className="question-list">
                        {attempt.questions.map((question) => (
                          <fieldset className="question-card" key={question.attempt_question_id}>
                            <legend><span>{labels.question} {question.position}</span><strong dir="auto">{localize(question.prompt, locale)}</strong></legend>
                            {question.response_contract.kind === "single_choice" ? question.response_contract.options.map((option) => (
                              <label className="answer-option" key={option.id}>
                                <input
                                  type="radio"
                                  name={question.attempt_question_id}
                                  value={option.id}
                                  checked={answers[question.attempt_question_id] === option.id}
                                  onChange={(event) => setAnswers((current) => ({ ...current, [question.attempt_question_id]: event.target.value }))}
                                />
                                <span dir="auto">{localize(option.label, locale)}</span>
                              </label>
                            )) : (
                              <label className="text-answer-label">
                                <span>{labels.textAnswer}</span>
                                <input
                                  className="text-answer"
                                  value={answers[question.attempt_question_id] ?? ""}
                                  maxLength={question.response_contract.max_length}
                                  onChange={(event) => setAnswers((current) => ({ ...current, [question.attempt_question_id]: event.target.value }))}
                                />
                              </label>
                            )}
                          </fieldset>
                        ))}
                      </div>
                      {message ? <p role="alert">{message}</p> : null}
                      <div className="practice-submit-row"><button type="submit" className="primary-button" disabled={busy}>{busy ? labels.submitting : labels.submit}</button></div>
                    </form>
                  ) : null}

                  {result ? (
                    <div className="metric-card"><span>{labels.result}</span><strong>{result.score} / {result.max_score}</strong></div>
                  ) : null}

                  {!selectedAssessment && !attempt ? (
                    <div className="empty-panel"><p>{allAssessments.length > 0 ? copy.openAssessment : labels.noAttempt}</p></div>
                  ) : null}
                </section>
              </div>
            ) : (
              <div className="progress-workspace">
                <section className="context-panel">
                  <div className="section-heading-row"><h2>{labels.progressTitle}</h2><strong className="mastery-summary">{progressAverage === null ? "—" : `${progressAverage}%`}</strong></div>
                  {progress.length === 0 ? <p>{labels.emptyProgress}</p> : progress.map((item) => (
                    <article className="progress-card" key={`${item.academic_context_id}-${item.curriculum_node_id}-${item.source_version}`}>
                      <div><strong>{Math.round(item.mastery * 100)}%</strong><span>{item.curriculum_node_id}</span></div>
                    </article>
                  ))}
                </section>
              </div>
            )}
          </main>
        </div>
      </div>
    </section>
  );
}
