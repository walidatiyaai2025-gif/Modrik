import assert from "node:assert/strict";
import { GET, POST, PUT } from "../src/app/api/learning/[...path]/route";

process.env.MODRIK_API_BASE_URL ??= "http://127.0.0.1:8000";
process.env.MODRIK_FIXTURE_BEARER_TOKEN ??= "modrik-local-fixture-token";
const runId = crypto.randomUUID();

type Handler = (
  request: Request,
  context: { params: Promise<{ path: string[] }> },
) => Promise<Response> | Response;

async function request<T>(
  handler: Handler,
  path: string,
  body?: object,
  idempotencyKey?: string,
): Promise<T> {
  const headers = new Headers({ Accept: "application/json" });
  if (body !== undefined) headers.set("Content-Type", "application/json");
  if (idempotencyKey) headers.set("Idempotency-Key", idempotencyKey);
  const response = await handler(
    new Request(`http://localhost/api/learning/${path}`, {
      method: handler === GET ? "GET" : handler === PUT ? "PUT" : "POST",
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    }),
    { params: Promise.resolve({ path: path.split("/") }) },
  );
  const payload = (await response.json()) as { data?: T; code?: string; detail?: string };
  assert.equal(response.ok, true, `${path}: ${payload.code ?? response.status} ${payload.detail ?? ""}`);
  assert.notEqual(payload.data, undefined, `${path}: missing response data`);

  return payload.data as T;
}

const session = await request<{ user_id: string }>(GET, "session");
assert.match(session.user_id, /^[0-9A-HJKMNP-TV-Z]{26}$/);

const context = await request<{ state: string }>(GET, "academic-context");
assert.equal(context.state, "active");

const lesson = await request<{ practice_quiz_id: string; blocks: unknown[] }>(
  GET,
  "lessons/01J00000000000000000000003",
);
assert.equal(lesson.blocks.length, 2);

const attempt = await request<{
  id: string;
  questions: Array<{
    attempt_question_id: string;
    response_contract:
      | { kind: "single_choice"; options: Array<{ id: string }> }
      | { kind: "short_text"; max_length: number };
  }>;
}>(POST, "attempts", { quiz_id: lesson.practice_quiz_id }, `web-smoke-start-${runId}`);
assert.equal(attempt.questions.length, 3);

for (const [index, question] of attempt.questions.entries()) {
  const value = question.response_contract.kind === "single_choice"
    ? question.response_contract.options[0].id
    : "review";
  await request(
    PUT,
    `attempts/${attempt.id}/answers/${question.attempt_question_id}`,
    { expected_revision: 0, value },
    `web-smoke-answer-${index}-${runId}`,
  );
}

const result = await request<{ score: number; max_score: number; attempt: { status: string } }>(
  POST,
  `attempts/${attempt.id}/submit`,
  undefined,
  `web-smoke-submit-${runId}`,
);
assert.equal(result.attempt.status, "graded");
assert.equal(result.score, 3);
assert.equal(result.max_score, 3);

const progress = await request<Array<{ mastery: number }>>(GET, "progress");
assert.equal(progress.length, 1);
assert.equal(progress[0].mastery, 1);

console.log("Fixture smoke passed: session → context → lesson → 3 answers → submit → progress.");
