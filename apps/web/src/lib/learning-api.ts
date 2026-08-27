import { diagnosticFetch } from "./runtime-diagnostics";

export type Locale = "ar" | "en" | "fr";
export type LocalizedText = Partial<Record<Locale, string>>;

export type Session = {
  user_id: string;
  locale: Locale;
  roles: string[];
};

export type AcademicTrack = {
  id: string;
  year: { key: string; label: string };
  labels: Record<Locale, string>;
};

export type AcademicContext =
  | { state: "onboarding_required" }
  | {
      state: "active";
      context_id: string;
      academic_track_id: string;
      year_level: string;
      activated_at: string;
    };

export type CatalogueAssessment = {
  id: string;
  kind: "practice" | "quiz" | "mock_exam";
  blueprint_version: number;
  title: LocalizedText;
};

export type CatalogueLesson = {
  id: string;
  slug: string;
  content_version: number;
  title: LocalizedText;
  published_at: string | null;
};

export type CatalogueNode = {
  id: string;
  reference: string;
  type: "subject" | "unit" | "topic" | string;
  title: LocalizedText;
  lessons: CatalogueLesson[];
  assessments: CatalogueAssessment[];
  children: CatalogueNode[];
};

export type ContentCatalogue =
  | {
      state: "onboarding_required";
      subjects: [];
      counts: { subjects: 0; lessons: 0; assessments: 0 };
    }
  | {
      state: "active";
      context: {
        context_id: string;
        academic_track_id: string;
        track_reference: string;
        year_level: string;
        track_title: LocalizedText;
      };
      subjects: CatalogueNode[];
      counts: { subjects: number; lessons: number; assessments: number };
    };

export type Lesson = {
  id: string;
  curriculum_node_id: string;
  content_version: number;
  title: LocalizedText;
  practice_quiz_id: string | null;
  blocks: Array<{
    id: string;
    position: number;
    type: string;
    content: LocalizedText;
  }>;
};

export type ChoiceOption = { id: string; label: LocalizedText };
export type ResponseContract =
  | { kind: "single_choice"; options: ChoiceOption[] }
  | { kind: "short_text"; max_length: number };

export type AttemptQuestion = {
  attempt_question_id: string;
  position: number;
  type: "single_choice" | "short_text";
  prompt: LocalizedText;
  response_contract: ResponseContract;
  current_answer: null | { revision: number; value: string; answered_at: string };
};

export type Attempt = {
  id: string;
  academic_context_id: string;
  quiz_id: string;
  status: "in_progress" | "submitted" | "graded" | "abandoned";
  blueprint_version: number;
  ordering_algorithm: "modrik-fy-v1";
  started_at: string;
  completed_at: string | null;
  archived_at: string | null;
  questions: AttemptQuestion[];
};

export type AttemptResult = { attempt: Attempt; score: number; max_score: number };
export type Progress = {
  academic_context_id: string;
  curriculum_node_id: string;
  mastery: number;
  source_version: number;
  calculated_at: string;
};

export type StudentNotificationAction = "study" | "practice" | "progress" | "academic" | "account";
export type StudentNotification = {
  id: string;
  kind: string;
  title: Record<Locale, string>;
  body: Record<Locale, string>;
  action: StudentNotificationAction | null;
  occurred_at: string;
  read_at: string | null;
  is_read: boolean;
};
export type StudentNotificationInbox = {
  items: StudentNotification[];
  unread_count: number;
};

type Envelope<T> = { data: T; meta: { request_id: string } };
type ProblemDetails = {
  status: number;
  code: string;
  detail?: string;
  retryable: boolean;
};

type LearningDiagnosticOperation =
  | "learning:session"
  | "learning:academic-tracks"
  | "learning:academic-context"
  | "learning:academic-context-activate"
  | "learning:academic-context-reset"
  | "learning:content-catalogue"
  | "learning:lesson"
  | "learning:progress"
  | "learning:notifications"
  | "learning:notification-read"
  | "learning:notifications-read-all"
  | "learning:attempt-start"
  | "learning:attempt"
  | "learning:answer"
  | "learning:submit";

export class LearningApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly retryable: boolean,
  ) {
    super(message);
    this.name = "LearningApiError";
  }
}

async function requestData<T>(operation: LearningDiagnosticOperation, path: string, init?: RequestInit): Promise<T> {
  const response = await diagnosticFetch(operation, `/api/learning/${path}`, {
    ...init,
    headers: {
      Accept: "application/json, application/problem+json",
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...init?.headers,
    },
    cache: "no-store",
  });
  const payload: unknown = await response.json();
  if (!response.ok) {
    const problem = payload as ProblemDetails;
    throw new LearningApiError(
      response.status,
      problem.code ?? "LEARNING_REQUEST_FAILED",
      problem.detail ?? "The learning request failed.",
      problem.retryable ?? response.status >= 500,
    );
  }

  return (payload as Envelope<T>).data;
}

function command(method: "POST" | "PUT", body: object | undefined, idempotencyKey: string): RequestInit {
  return {
    method,
    headers: { "Idempotency-Key": idempotencyKey },
    body: body === undefined ? undefined : JSON.stringify(body),
  };
}

export const learningApi = {
  session: () => requestData<Session>("learning:session", "session"),
  academicTracks: () =>
    requestData<{ tracks: AcademicTrack[] }>("learning:academic-tracks", "academic-tracks").then(({ tracks }) => tracks),
  academicContext: () => requestData<AcademicContext>("learning:academic-context", "academic-context"),
  activateAcademicContext: (academicTrackId: string, idempotencyKey: string) =>
    requestData<AcademicContext>(
      "learning:academic-context-activate",
      "academic-context/activate",
      command("POST", { academic_track_id: academicTrackId }, idempotencyKey),
    ),
  resetAcademicContext: (academicTrackId: string, idempotencyKey: string) =>
    requestData<AcademicContext>(
      "learning:academic-context-reset",
      "academic-context/reset",
      command("POST", { academic_track_id: academicTrackId }, idempotencyKey),
    ),
  contentCatalogue: (subjectReference?: string) => {
    const query = subjectReference ? `?subject_reference=${encodeURIComponent(subjectReference)}` : "";
    return requestData<ContentCatalogue>("learning:content-catalogue", `content-catalogue${query}`);
  },
  lesson: (lessonId: string) => requestData<Lesson>("learning:lesson", `lessons/${lessonId}`),
  progress: () => requestData<Progress[]>("learning:progress", "progress"),
  notifications: () => requestData<StudentNotificationInbox>("learning:notifications", "notifications"),
  markNotificationRead: (notificationId: string) =>
    requestData<StudentNotification>(
      "learning:notification-read",
      `notifications/${notificationId}/read`,
      { method: "PUT" },
    ),
  markAllNotificationsRead: () =>
    requestData<{ updated_count: number; unread_count: number }>(
      "learning:notifications-read-all",
      "notifications/read-all",
      { method: "PUT" },
    ),
  startAttempt: (quizId: string, idempotencyKey: string) =>
    requestData<Attempt>("learning:attempt-start", "attempts", command("POST", { quiz_id: quizId }, idempotencyKey)),
  attempt: (attemptId: string) => requestData<Attempt>("learning:attempt", `attempts/${attemptId}`),
  answer: (
    attemptId: string,
    attemptQuestionId: string,
    expectedRevision: number,
    value: string,
    idempotencyKey: string,
  ) =>
    requestData<{ revision: number; value: string; answered_at: string }>(
      "learning:answer",
      `attempts/${attemptId}/answers/${attemptQuestionId}`,
      command("PUT", { expected_revision: expectedRevision, value }, idempotencyKey),
    ),
  submit: (attemptId: string, idempotencyKey: string) =>
    requestData<AttemptResult>(
      "learning:submit",
      `attempts/${attemptId}/submit`,
      command("POST", undefined, idempotencyKey),
    ),
};
