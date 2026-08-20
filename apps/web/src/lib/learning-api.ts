export type Locale = "ar" | "en" | "fr";
export type LocalizedText = Partial<Record<Locale, string>>;

export type Session = {
  user_id: string;
  locale: Locale;
  roles: string[];
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

export type Lesson = {
  id: string;
  curriculum_node_id: string;
  content_version: number;
  title: LocalizedText;
  practice_quiz_id: string;
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

type Envelope<T> = { data: T; meta: { request_id: string } };
type ProblemDetails = {
  status: number;
  code: string;
  detail?: string;
  retryable: boolean;
};

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

async function requestData<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api/learning/${path}`, {
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
  session: () => requestData<Session>("session"),
  academicContext: () => requestData<AcademicContext>("academic-context"),
  activateAcademicContext: (academicTrackId: string, idempotencyKey: string) =>
    requestData<AcademicContext>(
      "academic-context/activate",
      command("POST", { academic_track_id: academicTrackId }, idempotencyKey),
    ),
  resetAcademicContext: (academicTrackId: string, idempotencyKey: string) =>
    requestData<AcademicContext>(
      "academic-context/reset",
      command("POST", { academic_track_id: academicTrackId }, idempotencyKey),
    ),
  lesson: (lessonId: string) => requestData<Lesson>(`lessons/${lessonId}`),
  progress: () => requestData<Progress[]>("progress"),
  startAttempt: (quizId: string, idempotencyKey: string) =>
    requestData<Attempt>("attempts", command("POST", { quiz_id: quizId }, idempotencyKey)),
  attempt: (attemptId: string) => requestData<Attempt>(`attempts/${attemptId}`),
  answer: (
    attemptId: string,
    attemptQuestionId: string,
    expectedRevision: number,
    value: string,
    idempotencyKey: string,
  ) =>
    requestData<{ revision: number; value: string; answered_at: string }>(
      `attempts/${attemptId}/answers/${attemptQuestionId}`,
      command("PUT", { expected_revision: expectedRevision, value }, idempotencyKey),
    ),
  submit: (attemptId: string, idempotencyKey: string) =>
    requestData<AttemptResult>(`attempts/${attemptId}/submit`, command("POST", undefined, idempotencyKey)),
};
