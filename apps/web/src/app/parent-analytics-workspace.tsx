"use client";

import { useEffect, useMemo, useState } from "react";
import {
  ParentApiError,
  parentApi,
  type ParentAnalytics,
  type ParentChild,
  type ParentLocale,
  type ParentLocalizedText,
  type ParentMasterySkill,
} from "../lib/parent-api";
import styles from "./parent-analytics.module.css";

type LoadState = "loading" | "ready" | "empty" | "error";

const copy = {
  en: {
    title: "Parent progress",
    subtitle: "A private view of each linked child's learning evidence.",
    child: "Child",
    noChildren: "No linked children are available for this account.",
    retry: "Retry",
    loading: "Loading parent analytics…",
    error: "Parent analytics are unavailable right now.",
    noContext: "This child has not started an academic track yet.",
    activity: "Learning activity",
    attempts: "Attempts",
    answered: "Answered questions",
    accuracy: "Accuracy",
    practiceTime: "Practice time",
    activeDays: "Active days",
    minutes: "min",
    noAccuracy: "Not enough graded answers",
    mastery: "Mastery",
    subjects: "Subjects",
    skills: "Skills",
    attention: "Needs attention",
    strong: "Strong areas",
    noMastery: "No mastery evidence is available yet.",
    revision: "Revision attention",
    dueNow: "Due now",
    due: "Due",
    noRevision: "No revision attention items are available yet.",
    assessments: "Assessment history",
    noAssessments: "No completed assessments are available yet.",
    evidence: "evidence",
    trend: "Trend",
    latest: "Latest",
    privacy: "Only children explicitly linked to this parent account are shown. Sibling ranking is not used.",
  },
  ar: {
    title: "تقدم الأبناء",
    subtitle: "عرض خاص لأدلة تعلم كل طفل مرتبط بهذا الحساب.",
    child: "الطفل",
    noChildren: "لا يوجد أطفال مرتبطون بهذا الحساب حاليًا.",
    retry: "إعادة المحاولة",
    loading: "جارٍ تحميل تحليلات ولي الأمر…",
    error: "تحليلات ولي الأمر غير متاحة حاليًا.",
    noContext: "هذا الطفل لم يبدأ مسارًا دراسيًا بعد.",
    activity: "نشاط التعلم",
    attempts: "المحاولات",
    answered: "الأسئلة المجابة",
    accuracy: "الدقة",
    practiceTime: "وقت التدريب",
    activeDays: "أيام النشاط",
    minutes: "دقيقة",
    noAccuracy: "لا توجد إجابات مصححة كافية",
    mastery: "الإتقان",
    subjects: "المواد",
    skills: "المهارات",
    attention: "تحتاج اهتمامًا",
    strong: "نقاط القوة",
    noMastery: "لا توجد أدلة إتقان متاحة حتى الآن.",
    revision: "مراجعة تحتاج انتباهًا",
    dueNow: "مستحقة الآن",
    due: "موعد المراجعة",
    noRevision: "لا توجد عناصر مراجعة تحتاج انتباهًا حاليًا.",
    assessments: "سجل التقييمات",
    noAssessments: "لا توجد تقييمات مكتملة حتى الآن.",
    evidence: "أدلة",
    trend: "الاتجاه",
    latest: "الأحدث",
    privacy: "يظهر فقط الأطفال المرتبطون صراحةً بحساب ولي الأمر. لا تتم مقارنة أو ترتيب الإخوة.",
  },
  fr: {
    title: "Progression des enfants",
    subtitle: "Vue privée des preuves d’apprentissage de chaque enfant lié.",
    child: "Enfant",
    noChildren: "Aucun enfant lié n’est disponible pour ce compte.",
    retry: "Réessayer",
    loading: "Chargement des analyses parentales…",
    error: "Les analyses parentales sont indisponibles pour le moment.",
    noContext: "Cet enfant n’a pas encore commencé de parcours scolaire.",
    activity: "Activité d’apprentissage",
    attempts: "Tentatives",
    answered: "Questions répondues",
    accuracy: "Précision",
    practiceTime: "Temps de pratique",
    activeDays: "Jours actifs",
    minutes: "min",
    noAccuracy: "Pas assez de réponses notées",
    mastery: "Maîtrise",
    subjects: "Matières",
    skills: "Compétences",
    attention: "À surveiller",
    strong: "Points forts",
    noMastery: "Aucune preuve de maîtrise n’est encore disponible.",
    revision: "Révisions à surveiller",
    dueNow: "À revoir maintenant",
    due: "Échéance",
    noRevision: "Aucune révision prioritaire n’est disponible.",
    assessments: "Historique des évaluations",
    noAssessments: "Aucune évaluation terminée n’est disponible.",
    evidence: "preuves",
    trend: "Tendance",
    latest: "Dernier",
    privacy: "Seuls les enfants explicitement liés à ce compte parent sont affichés. Aucun classement entre frères et sœurs.",
  },
} as const;

function localize(value: ParentLocalizedText, locale: ParentLocale): string {
  return value[locale] ?? value.en ?? value.ar ?? value.fr ?? "";
}

function percent(value: number | null): string {
  return value === null ? "—" : `${Math.round(value)}%`;
}

function minutes(seconds: number): number {
  return Math.round(seconds / 60);
}

function SkillList({
  items,
  locale,
  empty,
}: {
  items: ParentMasterySkill[];
  locale: ParentLocale;
  empty: string;
}) {
  if (items.length === 0) return <p className={styles.empty}>{empty}</p>;
  return (
    <ul className={styles.skillList}>
      {items.map((skill) => (
        <li key={skill.skill_id}>
          <div>
            <strong dir="auto">{localize(skill.skill_title, locale)}</strong>
            <span dir="auto">{localize(skill.subject.title, locale)}</span>
          </div>
          <div className={styles.skillMetric}>
            <strong>{percent(skill.score_percent)}</strong>
            <span>{skill.display_band}</span>
          </div>
        </li>
      ))}
    </ul>
  );
}

export default function ParentAnalyticsWorkspace({
  initialLocale = "en",
}: {
  initialLocale?: ParentLocale;
}) {
  const [locale, setLocale] = useState<ParentLocale>(initialLocale);
  const [children, setChildren] = useState<ParentChild[]>([]);
  const [selectedChildId, setSelectedChildId] = useState("");
  const [analytics, setAnalytics] = useState<ParentAnalytics | null>(null);
  const [state, setState] = useState<LoadState>("loading");
  const labels = copy[locale];
  const direction = locale === "ar" ? "rtl" : "ltr";

  async function loadChildren() {
    setState("loading");
    setAnalytics(null);
    try {
      const items = await parentApi.children();
      setChildren(items);
      if (items.length === 0) {
        setSelectedChildId("");
        setState("empty");
        return;
      }
      const next = items.some((child) => child.id === selectedChildId)
        ? selectedChildId
        : items[0].id;
      setSelectedChildId(next);
      const snapshot = await parentApi.analytics(next);
      setAnalytics(snapshot);
      setState("ready");
    } catch {
      setState("error");
    }
  }

  useEffect(() => {
    const startup = window.setTimeout(() => void loadChildren(), 0);
    return () => window.clearTimeout(startup);
    // This initial load intentionally runs once; child changes use selectChild.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function selectChild(childId: string) {
    setSelectedChildId(childId);
    setState("loading");
    try {
      setAnalytics(await parentApi.analytics(childId));
      setState("ready");
    } catch (error) {
      if (error instanceof ParentApiError && error.status === 404) {
        await loadChildren();
        return;
      }
      setState("error");
    }
  }

  const selected = useMemo(
    () => children.find((child) => child.id === selectedChildId) ?? null,
    [children, selectedChildId],
  );

  return (
    <main className={styles.shell} dir={direction} lang={locale} data-parent-analytics="workspace">
      <header className={styles.header}>
        <div>
          <p className={styles.eyebrow}>MODRIK</p>
          <h1>{labels.title}</h1>
          <p>{labels.subtitle}</p>
        </div>
        <div className={styles.controls}>
          <div className={styles.locale} aria-label="Language">
            {(["ar", "en", "fr"] as const).map((item) => (
              <button
                key={item}
                type="button"
                aria-pressed={locale === item}
                onClick={() => setLocale(item)}
              >
                {item.toUpperCase()}
              </button>
            ))}
          </div>
          {children.length > 0 ? (
            <label>
              <span>{labels.child}</span>
              <select
                value={selectedChildId}
                onChange={(event) => void selectChild(event.currentTarget.value)}
              >
                {children.map((child) => (
                  <option key={child.id} value={child.id}>{child.name}</option>
                ))}
              </select>
            </label>
          ) : null}
        </div>
      </header>

      <p className={styles.privacy}>{labels.privacy}</p>

      {state === "loading" ? <p role="status">{labels.loading}</p> : null}
      {state === "empty" ? <p className={styles.empty}>{labels.noChildren}</p> : null}
      {state === "error" ? (
        <div className={styles.error} role="alert">
          <span>{labels.error}</span>
          <button type="button" onClick={() => void loadChildren()}>{labels.retry}</button>
        </div>
      ) : null}

      {state === "ready" && analytics ? (
        <div className={styles.content}>
          <section className={styles.hero} aria-labelledby="parent-child-title">
            <div>
              <p className={styles.eyebrow}>{labels.child}</p>
              <h2 id="parent-child-title">{selected?.name ?? analytics.child.name}</h2>
            </div>
            {analytics.state === "active" ? (
              <p dir="auto">{localize(analytics.academic_context.track_title, locale)} · {analytics.academic_context.year_level}</p>
            ) : (
              <p>{labels.noContext}</p>
            )}
          </section>

          <section aria-labelledby="parent-activity-title">
            <h2 id="parent-activity-title">{labels.activity}</h2>
            <div className={styles.metrics}>
              <article><span>{labels.attempts}</span><strong>{analytics.activity.attempts_started}</strong></article>
              <article><span>{labels.answered}</span><strong>{analytics.activity.answered_questions}</strong></article>
              <article><span>{labels.accuracy}</span><strong>{analytics.activity.accuracy_percent === null ? labels.noAccuracy : percent(analytics.activity.accuracy_percent)}</strong></article>
              <article><span>{labels.practiceTime}</span><strong>{minutes(analytics.activity.practice_time_seconds)} {labels.minutes}</strong></article>
              <article><span>{labels.activeDays}</span><strong>{analytics.activity.active_days}</strong></article>
            </div>
          </section>

          <section className={styles.grid} aria-labelledby="parent-mastery-title">
            <div className={styles.panel}>
              <h2 id="parent-mastery-title">{labels.mastery}</h2>
              <h3>{labels.subjects}</h3>
              {analytics.mastery.subjects.length === 0 ? <p className={styles.empty}>{labels.noMastery}</p> : (
                <ul className={styles.summaryList}>
                  {analytics.mastery.subjects.map((subject) => (
                    <li key={subject.id}>
                      <span dir="auto">{localize(subject.title, locale)}</span>
                      <strong>{percent(subject.average_mastery_percent)}</strong>
                    </li>
                  ))}
                </ul>
              )}
              <h3>{labels.skills}</h3>
              <SkillList items={analytics.mastery.skills} locale={locale} empty={labels.noMastery} />
            </div>

            <div className={styles.panel}>
              <h2>{labels.attention}</h2>
              <SkillList items={analytics.mastery.attention} locale={locale} empty={labels.noMastery} />
              <h2>{labels.strong}</h2>
              <SkillList items={analytics.mastery.strong} locale={locale} empty={labels.noMastery} />
            </div>
          </section>

          <section className={styles.panel} aria-labelledby="parent-revision-title">
            <div className={styles.sectionHeading}>
              <h2 id="parent-revision-title">{labels.revision}</h2>
              <strong>{labels.dueNow}: {analytics.revision_attention.due_count}</strong>
            </div>
            {analytics.revision_attention.items.length === 0 ? (
              <p className={styles.empty}>{labels.noRevision}</p>
            ) : (
              <ul className={styles.revisionList}>
                {analytics.revision_attention.items.map((item) => (
                  <li key={item.skill_id}>
                    <div>
                      <strong dir="auto">{localize(item.skill_title, locale)}</strong>
                      <span dir="auto">{localize(item.subject.title, locale)}</span>
                    </div>
                    <div>
                      <strong>{percent(item.score_percent)}</strong>
                      <span>{labels.due}: {new Intl.DateTimeFormat(locale === "ar" ? "ar-KW" : locale === "fr" ? "fr-FR" : "en-US", { dateStyle: "medium" }).format(new Date(item.due_at))}</span>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className={styles.panel} aria-labelledby="parent-assessments-title">
            <h2 id="parent-assessments-title">{labels.assessments}</h2>
            {analytics.assessment_history.length === 0 ? (
              <p className={styles.empty}>{labels.noAssessments}</p>
            ) : (
              <div className={styles.tableWrap}>
                <table>
                  <thead><tr><th>{labels.assessments}</th><th>{labels.latest}</th><th>{labels.accuracy}</th></tr></thead>
                  <tbody>
                    {analytics.assessment_history.map((attempt) => (
                      <tr key={attempt.attempt_id}>
                        <td dir="auto">{localize(attempt.title, locale)}</td>
                        <td>{attempt.completed_at ? new Intl.DateTimeFormat(locale === "ar" ? "ar-KW" : locale === "fr" ? "fr-FR" : "en-US", { dateStyle: "medium" }).format(new Date(attempt.completed_at)) : "—"}</td>
                        <td>{percent(attempt.score_percent)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </div>
      ) : null}
    </main>
  );
}
