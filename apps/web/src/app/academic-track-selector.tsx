"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  learningApi,
  LearningApiError,
  type AcademicContext,
  type AcademicTrack,
  type Locale,
} from "@/lib/learning-api";

type CatalogueState = "loading" | "ready" | "empty" | "error" | "offline" | "permission";

export type AcademicContextRecovery = "reconcile_context" | "reload_catalogue" | "none";

export function academicContextRecovery(code: string): AcademicContextRecovery {
  if ([
    "ACADEMIC_CONTEXT_RESET_REQUIRED",
    "ACADEMIC_CONTEXT_ALREADY_ACTIVE",
    "ACADEMIC_CONTEXT_ONBOARDING_REQUIRED",
    "ACADEMIC_CONTEXT_UNCHANGED",
  ].includes(code)) {
    return "reconcile_context";
  }

  if (code === "RESOURCE_NOT_FOUND") {
    return "reload_catalogue";
  }

  return "none";
}

export const academicTrackCopy = {
  en: {
    label: "Academic track",
    yearLabel: "School year",
    yearHelp: "Choose your school year first. Every available track for that year will appear automatically.",
    authorized: "Choose your school year, then choose any academic track available for that year.",
    loading: "Loading school years and academic tracks…",
    empty: "No academic tracks are available for the configured school years right now.",
    error: "We couldn’t load school years and academic tracks. Nothing has changed. Try again.",
    offline: "Reconnect to load school years and academic tracks or change your track.",
    permission: "We can’t load academic tracks with this session. Sign in again, then try again.",
    retry: "Try again",
    activate: "Start with this track",
    change: "Change academic track",
    resetTitle: "Before you change academic track",
    resetBody: "Your previous academic track, attempts, and progress will be archived—not deleted. Any work still in progress may be left unfinished.",
    syncWarning: "Sync all pending answers and changes before you continue.",
    confirm: "I understand what will happen when I change tracks.",
    submitReset: "Change academic track",
    same: "Choose a different available track to continue.",
    busy: "Updating your academic track…",
    failed: "We couldn’t update your academic track. Nothing changed. Check your connection and that the selected track is still available, then try again.",
    resetRequired: "Your academic context changed elsewhere. We refreshed it from MODRIK so you can continue from the current state.",
    trackUnavailable: "That academic track is no longer available. The list has been refreshed; choose an available track.",
  },
  ar: {
    label: "المسار الأكاديمي",
    yearLabel: "السنة الدراسية",
    yearHelp: "اختر السنة الدراسية أولًا. ستظهر تلقائيًا كل المسارات المتاحة لهذه السنة.",
    authorized: "اختر السنة الدراسية، ثم اختر أي مسار أكاديمي متاح لهذه السنة.",
    loading: "جارٍ تحميل السنوات الدراسية والمسارات الأكاديمية…",
    empty: "لا توجد مسارات أكاديمية متاحة للسنوات الدراسية المسجلة حاليًا.",
    error: "تعذر تحميل السنوات الدراسية والمسارات الأكاديمية. لم يتغير شيء. حاول مرة أخرى.",
    offline: "اتصل بالإنترنت لتحميل السنوات الدراسية والمسارات الأكاديمية أو تغيير مسارك.",
    permission: "لا يمكن تحميل المسارات الأكاديمية بهذه الجلسة. سجّل الدخول من جديد ثم حاول مرة أخرى.",
    retry: "حاول مرة أخرى",
    activate: "ابدأ بهذا المسار",
    change: "تغيير المسار الأكاديمي",
    resetTitle: "قبل تغيير المسار الأكاديمي",
    resetBody: "سيتم أرشفة مسارك الأكاديمي السابق ومحاولاتك وتقدّمك، ولن يتم حذفها. وقد يبقى أي عمل جارٍ غير مكتمل.",
    syncWarning: "زامن جميع الإجابات والتغييرات المعلّقة قبل المتابعة.",
    confirm: "أفهم ما سيحدث عند تغيير المسار.",
    submitReset: "تغيير المسار الأكاديمي",
    same: "اختر مسارًا آخر متاحًا للمتابعة.",
    busy: "جارٍ تحديث مسارك الأكاديمي…",
    failed: "تعذر تحديث مسارك الأكاديمي. لم يتغير شيء. تحقق من اتصالك ومن أن المسار المختار ما زال متاحًا، ثم حاول مرة أخرى.",
    resetRequired: "تغيّرت حالتك الأكاديمية من مكان آخر. حدّثنا الحالة من MODRIK لتكمل من الوضع الحالي.",
    trackUnavailable: "هذا المسار لم يعد متاحًا. تم تحديث القائمة؛ اختر مسارًا متاحًا.",
  },
  fr: {
    label: "Parcours académique",
    yearLabel: "Année scolaire",
    yearHelp: "Choisissez d’abord votre année scolaire. Tous les parcours disponibles pour cette année apparaîtront automatiquement.",
    authorized: "Choisissez votre année scolaire, puis n’importe quel parcours académique disponible pour cette année.",
    loading: "Chargement des années scolaires et des parcours académiques…",
    empty: "Aucun parcours académique n’est disponible pour les années scolaires configurées pour le moment.",
    error: "Nous n’avons pas pu charger les années scolaires et les parcours académiques. Rien n’a changé. Réessayez.",
    offline: "Reconnectez-vous pour charger les années scolaires et les parcours académiques ou changer de parcours.",
    permission: "Cette session ne permet pas de charger les parcours académiques. Reconnectez-vous à votre compte, puis réessayez.",
    retry: "Réessayer",
    activate: "Commencer avec ce parcours",
    change: "Changer de parcours académique",
    resetTitle: "Avant de changer de parcours académique",
    resetBody: "Votre ancien parcours académique, vos tentatives et votre progression seront archivés, pas supprimés. Tout travail en cours pourra rester inachevé.",
    syncWarning: "Synchronisez toutes les réponses et modifications en attente avant de continuer.",
    confirm: "Je comprends ce qui se passera quand je changerai de parcours.",
    submitReset: "Changer de parcours académique",
    same: "Choisissez un autre parcours disponible pour continuer.",
    busy: "Mise à jour de votre parcours académique…",
    failed: "Nous n’avons pas pu mettre à jour votre parcours académique. Rien n’a changé. Vérifiez votre connexion et que le parcours choisi est toujours disponible, puis réessayez.",
    resetRequired: "Votre contexte académique a changé ailleurs. Nous l’avons actualisé depuis MODRIK pour continuer avec l’état courant.",
    trackUnavailable: "Ce parcours n’est plus disponible. La liste a été actualisée ; choisissez un parcours disponible.",
  },
} as const;

function operationStorageKey(action: "activate" | "reset", trackId: string) {
  return `modrik.academic-context.${action}.${trackId}`;
}

function stableOperationKey(action: "activate" | "reset", trackId: string) {
  const storageKey = operationStorageKey(action, trackId);
  const existing = window.localStorage.getItem(storageKey);
  if (existing) return existing;
  const value = `modrik-academic-${action}-${crypto.randomUUID()}`;
  window.localStorage.setItem(storageKey, value);
  return value;
}

function acknowledgeOperation(action: "activate" | "reset", trackId: string) {
  window.localStorage.removeItem(operationStorageKey(action, trackId));
}

export default function AcademicTrackSelector({
  context,
  locale,
  offline,
  onTransitioned,
  onContextReconciled,
}: {
  context: AcademicContext | null;
  locale: Locale;
  offline: boolean;
  onTransitioned: () => Promise<void>;
  onContextReconciled: () => Promise<void>;
}) {
  const labels = academicTrackCopy[locale];
  const [state, setState] = useState<CatalogueState>(offline ? "offline" : "loading");
  const [tracks, setTracks] = useState<AcademicTrack[]>([]);
  const [selectedYearKey, setSelectedYearKey] = useState("");
  const [selectedId, setSelectedId] = useState("");
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

  const currentTrackId = context?.state === "active" ? context.academic_track_id : null;
  const selectedTrack = useMemo(
    () => tracks.find((track) => track.id === selectedId) ?? null,
    [selectedId, tracks],
  );
  const yearOptions = useMemo(() => {
    const options = new Map<string, string>();
    for (const track of tracks) {
      if (!options.has(track.year.key)) {
        options.set(track.year.key, track.year.label);
      }
    }
    return Array.from(options, ([key, label]) => ({ key, label }));
  }, [tracks]);
  const visibleTracks = useMemo(
    () => tracks.filter((track) => track.year.key === selectedYearKey),
    [selectedYearKey, tracks],
  );

  const loadCatalogue = useCallback(async () => {
    if (offline || !navigator.onLine) {
      setState("offline");
      return;
    }
    setState("loading");
    setMessage("");
    try {
      const nextTracks = await learningApi.academicTracks();
      setTracks(nextTracks);
      const currentTrack = nextTracks.find((track) => track.id === currentTrackId) ?? null;
      const preferredYear = currentTrack?.year.key ?? nextTracks[0]?.year.key ?? "";
      const preferredTracks = nextTracks.filter((track) => track.year.key === preferredYear);
      setSelectedYearKey(preferredYear);
      setSelectedId(currentTrack?.id ?? preferredTracks[0]?.id ?? "");
      setConfirmed(false);
      setState(nextTracks.length === 0 ? "empty" : "ready");
    } catch (error) {
      if (!navigator.onLine) {
        setState("offline");
      } else if (error instanceof LearningApiError && (error.status === 401 || error.status === 403)) {
        setState("permission");
      } else {
        setState("error");
      }
    }
  }, [currentTrackId, offline]);

  useEffect(() => {
    const timer = window.setTimeout(() => void loadCatalogue(), 0);
    return () => window.clearTimeout(timer);
  }, [loadCatalogue]);

  async function applySelection() {
    if (!selectedTrack || offline || busy) return;
    const action = context?.state === "active" ? "reset" : "activate";
    if (action === "reset" && (selectedTrack.id === currentTrackId || !confirmed)) return;

    setBusy(true);
    setMessage("");
    try {
      const key = stableOperationKey(action, selectedTrack.id);
      if (action === "reset") {
        await learningApi.resetAcademicContext(selectedTrack.id, key);
      } else {
        await learningApi.activateAcademicContext(selectedTrack.id, key);
      }
      acknowledgeOperation(action, selectedTrack.id);
      await onTransitioned();
      await loadCatalogue();
    } catch (error) {
      if (error instanceof LearningApiError) {
        const recovery = academicContextRecovery(error.code);
        if (recovery === "reconcile_context") {
          acknowledgeOperation(action, selectedTrack.id);
          await onContextReconciled();
          setMessage(labels.resetRequired);
          return;
        }
        if (recovery === "reload_catalogue") {
          acknowledgeOperation(action, selectedTrack.id);
          await loadCatalogue();
          setMessage(labels.trackUnavailable);
          return;
        }
      }
      setMessage(labels.failed);
    } finally {
      setBusy(false);
    }
  }

  if (state !== "ready") {
    const stateMessage = {
      loading: labels.loading,
      empty: labels.empty,
      error: labels.error,
      offline: labels.offline,
      permission: labels.permission,
      ready: "",
    }[state];
    return (
      <div className="empty-panel" role={state === "error" || state === "permission" ? "alert" : "status"} aria-live="polite">
        <p>{stateMessage}</p>
        {state !== "loading" && (
          <button type="button" className="secondary-button" disabled={offline} onClick={() => void loadCatalogue()}>
            {labels.retry}
          </button>
        )}
      </div>
    );
  }

  const isReset = context?.state === "active";
  return (
    <div className="academic-track-selector">
      <p className="muted-copy">{labels.authorized}</p>
      <label className="text-answer-label">
        <span>{labels.yearLabel}</span>
        <select
          value={selectedYearKey}
          disabled={busy || offline}
          onChange={(event) => {
            const nextYearKey = event.target.value;
            const nextTracks = tracks.filter((track) => track.year.key === nextYearKey);
            const currentInYear = nextTracks.find((track) => track.id === currentTrackId);
            setSelectedYearKey(nextYearKey);
            setSelectedId(currentInYear?.id ?? nextTracks[0]?.id ?? "");
            setConfirmed(false);
            setMessage("");
          }}
        >
          {yearOptions.map((year) => (
            <option key={year.key} value={year.key}>{year.label}</option>
          ))}
        </select>
        <small className="muted-copy">{labels.yearHelp}</small>
      </label>
      <label className="text-answer-label">
        <span>{labels.label}</span>
        <select
          value={selectedId}
          disabled={busy || offline || visibleTracks.length === 0}
          onChange={(event) => {
            setSelectedId(event.target.value);
            setConfirmed(false);
            setMessage("");
          }}
        >
          {visibleTracks.map((track) => (
            <option key={track.id} value={track.id}>{track.labels[locale]}</option>
          ))}
        </select>
      </label>

      {isReset && (
        <div className="reset-consequence">
          <h3>{labels.resetTitle}</h3>
          <p>{labels.resetBody}</p>
          <p>{labels.syncWarning}</p>
          <label className="answer-option">
            <input
              type="checkbox"
              checked={confirmed}
              disabled={busy || selectedId === currentTrackId}
              onChange={(event) => setConfirmed(event.target.checked)}
            />
            <span>{labels.confirm}</span>
          </label>
          {selectedId === currentTrackId && <p className="muted-copy">{labels.same}</p>}
        </div>
      )}

      <button
        type="button"
        className="primary-button"
        disabled={busy || offline || !selectedId || (isReset && (selectedId === currentTrackId || !confirmed))}
        onClick={() => void applySelection()}
      >
        {busy ? labels.busy : isReset ? labels.submitReset : labels.activate}
      </button>
      {message && <p className="inline-error" role="alert">{message}</p>}
    </div>
  );
}
