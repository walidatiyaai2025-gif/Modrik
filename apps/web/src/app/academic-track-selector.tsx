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

const copy = {
  en: {
    label: "Academic track",
    authorized: "Only tracks authorized by the Backend for your account are shown.",
    loading: "Loading authorized academic tracks…",
    empty: "No academic tracks are currently authorized for this account.",
    error: "The academic-track catalogue could not be loaded.",
    offline: "Reconnect to load the authorized catalogue or change academic context.",
    permission: "Your current session cannot read the academic-track catalogue.",
    retry: "Retry catalogue",
    activate: "Activate academic context",
    change: "Change academic track",
    resetTitle: "Changing track is a full academic-context reset",
    resetBody: "The Backend archives the previous context, attempts, and progress instead of deleting history. In-progress work may be abandoned.",
    confirm: "I understand the archival reset consequences.",
    submitReset: "Confirm track change",
    same: "Choose a different authorized track to reset.",
    busy: "Applying Backend academic-context transition…",
    failed: "The Backend rejected the academic-context transition. Review the state and retry the same logical operation.",
    resetRequired: "The Backend requires the explicit reset flow for this transition.",
  },
  ar: {
    label: "المسار الأكاديمي",
    authorized: "تظهر فقط المسارات التي صرّح بها الخادم لحسابك.",
    loading: "جارٍ تحميل المسارات الأكاديمية المصرح بها…",
    empty: "لا توجد مسارات أكاديمية مصرح بها لهذا الحساب حاليًا.",
    error: "تعذر تحميل قائمة المسارات الأكاديمية.",
    offline: "أعد الاتصال لتحميل القائمة المصرح بها أو تغيير السياق الأكاديمي.",
    permission: "لا تسمح جلستك الحالية بقراءة قائمة المسارات الأكاديمية.",
    retry: "إعادة تحميل القائمة",
    activate: "تفعيل السياق الأكاديمي",
    change: "تغيير المسار الأكاديمي",
    resetTitle: "تغيير المسار هو إعادة ضبط كاملة للسياق الأكاديمي",
    resetBody: "يقوم الخادم بأرشفة السياق السابق والمحاولات والتقدّم بدل حذف السجل، وقد تُنهى الأعمال الجارية.",
    confirm: "أفهم نتائج الأرشفة وإعادة الضبط.",
    submitReset: "تأكيد تغيير المسار",
    same: "اختر مسارًا مصرحًا مختلفًا لإعادة الضبط.",
    busy: "جارٍ تطبيق الانتقال الأكاديمي المعتمد من الخادم…",
    failed: "رفض الخادم الانتقال الأكاديمي. راجع الحالة وأعد محاولة نفس العملية المنطقية.",
    resetRequired: "يتطلب الخادم مسار إعادة الضبط الصريح لهذا الانتقال.",
  },
  fr: {
    label: "Parcours académique",
    authorized: "Seuls les parcours autorisés par le Backend pour votre compte sont affichés.",
    loading: "Chargement des parcours académiques autorisés…",
    empty: "Aucun parcours académique n’est actuellement autorisé pour ce compte.",
    error: "Le catalogue des parcours académiques n’a pas pu être chargé.",
    offline: "Reconnectez-vous pour charger le catalogue autorisé ou changer de contexte académique.",
    permission: "Votre session actuelle ne peut pas lire le catalogue des parcours.",
    retry: "Recharger le catalogue",
    activate: "Activer le contexte académique",
    change: "Changer de parcours académique",
    resetTitle: "Changer de parcours réinitialise entièrement le contexte académique",
    resetBody: "Le Backend archive l’ancien contexte, les tentatives et la progression au lieu de supprimer l’historique. Un travail en cours peut être abandonné.",
    confirm: "Je comprends les conséquences de l’archivage et de la réinitialisation.",
    submitReset: "Confirmer le changement",
    same: "Choisissez un autre parcours autorisé pour réinitialiser.",
    busy: "Application de la transition académique autoritaire du Backend…",
    failed: "Le Backend a refusé la transition académique. Vérifiez l’état et réessayez la même opération logique.",
    resetRequired: "Le Backend exige le flux explicite de réinitialisation pour cette transition.",
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
}: {
  context: AcademicContext | null;
  locale: Locale;
  offline: boolean;
  onTransitioned: () => Promise<void>;
}) {
  const labels = copy[locale];
  const [state, setState] = useState<CatalogueState>(offline ? "offline" : "loading");
  const [tracks, setTracks] = useState<AcademicTrack[]>([]);
  const [selectedId, setSelectedId] = useState("");
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

  const currentTrackId = context?.state === "active" ? context.academic_track_id : null;
  const selectedTrack = useMemo(
    () => tracks.find((track) => track.id === selectedId) ?? null,
    [selectedId, tracks],
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
      const preferred = nextTracks.some((track) => track.id === currentTrackId)
        ? currentTrackId
        : nextTracks[0]?.id ?? "";
      setSelectedId(preferred ?? "");
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
      if (error instanceof LearningApiError && error.code === "ACADEMIC_RESET_REQUIRED") {
        setMessage(labels.resetRequired);
      } else {
        setMessage(labels.failed);
      }
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
        <span>{labels.label}</span>
        <select
          value={selectedId}
          disabled={busy || offline}
          onChange={(event) => {
            setSelectedId(event.target.value);
            setConfirmed(false);
            setMessage("");
          }}
        >
          {tracks.map((track) => (
            <option key={track.id} value={track.id}>{track.labels[locale]}</option>
          ))}
        </select>
      </label>

      {isReset && (
        <div className="reset-consequence">
          <h3>{labels.resetTitle}</h3>
          <p>{labels.resetBody}</p>
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
