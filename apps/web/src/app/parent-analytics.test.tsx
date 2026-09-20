import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { parentApi } from "../lib/parent-api";

const workspace = readFileSync(new URL("./parent-analytics-workspace.tsx", import.meta.url), "utf8");
const authWorkspace = readFileSync(new URL("./auth-workspace.tsx", import.meta.url), "utf8");
const css = readFileSync(new URL("./globals.css", import.meta.url), "utf8");
const bff = readFileSync(new URL("./api/learning/[...path]/route.ts", import.meta.url), "utf8");

test("Parent Web consumes only the parent-scoped linked-child analytics contract", async () => {
  const originalFetch = globalThis.fetch;
  const calls: string[] = [];
  globalThis.fetch = async (input) => {
    calls.push(String(input));
    if (String(input).endsWith("/parent/children")) {
      return Response.json({
        data: {
          children: [{
            id: "01J000000000000000000000C1",
            name: "Child One",
            locale: "en",
            academic_context: null,
          }],
        },
        meta: { request_id: "parent-children" },
      });
    }
    return Response.json({
      data: {
        state: "no_active_context",
        child: { id: "01J000000000000000000000C1", name: "Child One", locale: "en" },
        activity: {
          attempts_started: 0,
          attempts_completed: 0,
          answered_questions: 0,
          graded_questions: 0,
          correct_questions: 0,
          accuracy_percent: null,
          practice_time_seconds: 0,
          active_days: 0,
          last_activity_at: null,
        },
        assessment_history: [],
        mastery: { skills: [], topics: [], subjects: [], strong: [], attention: [] },
        revision_attention: {
          algorithm_version: "spaced-repetition-v1",
          due_count: 0,
          attention_count: 0,
          items: [],
        },
        meta: { request_id: "parent-analytics" },
      },
      meta: { request_id: "parent-analytics-envelope" },
    });
  };

  try {
    const children = await parentApi.children();
    assert.equal(children.length, 1);
    await parentApi.analytics(children[0].id);
  } finally {
    globalThis.fetch = originalFetch;
  }

  assert.deepEqual(calls, [
    "/api/learning/parent/children",
    "/api/learning/parent/children/01J000000000000000000000C1/analytics",
  ]);
});

test("Parent workspace is role-routed, multilingual, truthful, and non-competitive", () => {
  assert.match(authWorkspace, /roles\.includes\("parent"\)/);
  assert.match(authWorkspace, /<ParentAnalyticsWorkspace initialLocale=\{locale\}/);
  assert.match(workspace, /data-parent-analytics="workspace"/);
  assert.match(workspace, /analytics\.state === "active"/);
  assert.match(workspace, /labels\.noContext/);
  assert.match(workspace, /accuracy_percent === null/);
  assert.match(workspace, /mastery\.attention/);
  assert.match(workspace, /revision_attention\.due_count/);
  assert.match(workspace, /assessment_history/);
  assert.match(workspace, /لا تتم مقارنة أو ترتيب الإخوة/);
  assert.match(workspace, /Aucun classement entre frères et sœurs/);
  assert.doesNotMatch(workspace, /siblingRank|leaderboard|compareChildren/);
});

test("Parent BFF allowlist and layout keep direct child routes bounded and responsive", () => {
  assert.match(bff, /\^parent\\\/children\$/);
  assert.match(bff, /parent\/children\/\$\{ulid\}\/analytics/);
  assert.match(css, /min-height: 44px/);
  assert.match(css, /overflow-x: auto/);
  assert.match(css, /max-width: 100%/);
  assert.match(css, /@media \(max-width: 420px\)/);
});
