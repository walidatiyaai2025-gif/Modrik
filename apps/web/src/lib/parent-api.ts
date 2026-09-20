import { diagnosticFetch } from "./runtime-diagnostics";

export type ParentLocale = "ar" | "en" | "fr";
export type ParentLocalizedText = Partial<Record<ParentLocale, string>>;

export type ParentChild = {
  id: string;
  name: string;
  locale: string;
  academic_context: null | {
    context_id: string;
    year_level: string;
    track_title: ParentLocalizedText;
  };
};

export type ParentMasterySkill = {
  skill_id: string;
  skill_reference: string;
  skill_title: ParentLocalizedText;
  topic: null | { id: string; reference: string; title: ParentLocalizedText };
  subject: { id: string; reference: string; title: ParentLocalizedText };
  score_percent: number;
  display_band: "critical" | "weak" | "developing" | "strong" | string;
  confidence: number;
  evidence_count: number;
  last_evidence_at: string | null;
  calculated_at: string | null;
  trend: Array<{
    occurred_at: string;
    score_percent: number | null;
    confidence: number | null;
    state_version: number | null;
  }>;
};

export type ParentAnalytics =
  | {
      state: "no_active_context";
      child: { id: string; name: string; locale: string };
      activity: ParentActivity;
      assessment_history: [];
      mastery: ParentMastery;
      revision_attention: ParentRevisionAttention;
    }
  | {
      state: "active";
      child: { id: string; name: string; locale: string };
      academic_context: {
        context_id: string;
        academic_track_id: string;
        track_reference: string;
        year_level: string;
        track_title: ParentLocalizedText;
      };
      activity: ParentActivity;
      assessment_history: ParentAssessment[];
      mastery: ParentMastery;
      revision_attention: ParentRevisionAttention;
    };

export type ParentActivity = {
  attempts_started: number;
  attempts_completed: number;
  answered_questions: number;
  graded_questions: number;
  correct_questions: number;
  accuracy_percent: number | null;
  practice_time_seconds: number;
  active_days: number;
  last_activity_at: string | null;
};

export type ParentAssessment = {
  attempt_id: string;
  kind: string;
  title: ParentLocalizedText;
  status: string;
  score: number | null;
  max_score: number | null;
  score_percent: number | null;
  started_at: string;
  completed_at: string | null;
};

export type ParentMastery = {
  skills: ParentMasterySkill[];
  topics: Array<{
    id: string;
    reference: string;
    title: ParentLocalizedText;
    skill_count: number;
    average_mastery_percent: number | null;
  }>;
  subjects: Array<{
    id: string;
    reference: string;
    title: ParentLocalizedText;
    skill_count: number;
    average_mastery_percent: number | null;
  }>;
  strong: ParentMasterySkill[];
  attention: ParentMasterySkill[];
};

export type ParentRevisionAttention = {
  algorithm_version: string;
  due_count: number;
  attention_count: number;
  items: Array<{
    skill_id: string;
    skill_reference: string;
    skill_title: ParentLocalizedText;
    subject: ParentMasterySkill["subject"];
    display_band: string;
    score_percent: number;
    last_evidence_at: string;
    due_at: string;
    due_now: boolean;
    algorithm_version: string;
    schedule_mode: "current_mastery_base_interval";
  }>;
};

type Envelope<T> = { data: T; meta: { request_id: string } };
type Problem = { status?: number; code?: string; detail?: string; retryable?: boolean };

export class ParentApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly retryable: boolean,
  ) {
    super(message);
    this.name = "ParentApiError";
  }
}

async function requestData<T>(operation: string, path: string): Promise<T> {
  const response = await diagnosticFetch(operation, `/api/learning/${path}`, {
    headers: { Accept: "application/json, application/problem+json" },
    cache: "no-store",
  });
  const payload: unknown = await response.json();
  if (!response.ok) {
    const problem = payload as Problem;
    throw new ParentApiError(
      response.status,
      problem.code ?? "PARENT_ANALYTICS_REQUEST_FAILED",
      problem.detail ?? "Parent analytics could not be loaded.",
      problem.retryable ?? response.status >= 500,
    );
  }
  return (payload as Envelope<T>).data;
}

export const parentApi = {
  children: () =>
    requestData<{ children: ParentChild[] }>("parent:children", "parent/children")
      .then((data) => data.children),
  analytics: (childId: string) =>
    requestData<ParentAnalytics>("parent:analytics", `parent/children/${childId}/analytics`),
};
