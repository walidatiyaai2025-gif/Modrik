# P0 Admin / Content publication acceptance matrix

Scope: Issue #19 / P0-ADMIN-001 on top of the integrated Content Preparation staging boundary.

| Acceptance concern | Required evidence |
| --- | --- |
| Operator authorization | Only `admin` and `content_team` can operate official-content workflow; unauthorized/student users are denied before publication mutations. |
| UGC isolation | Arbitrary/UGC identifiers have no path to `content_publications` or official curriculum. |
| Preparation binding | Returned ZIP is accepted only for its originating `preparation_request_id` and matching `settings_hash` / `schema_version`. |
| Versioned prompt/bundle | Preparation Wizard exposes the persisted versioned prompt and bundle binding; regeneration produces a new request/hash rather than mutating the prior binding. |
| Archive validation | Existing Content Preparation safety/schema/hash/semantic/rights validation remains authoritative before Admin review/import. |
| Stale settings | Superseded request/import fails visibly with `PREPARATION_REGENERATION_REQUIRED`; generic state errors must not mask the required stale outcome. |
| Dry-run/diff | Deterministic summary reports create/reuse/conflict counts and stable blocking codes; blocked summary cannot proceed to approved import. |
| Review queue | `approved`, `rejected`, `request_fix`; reason required for reject/request-fix; actor/reason/timestamps and transitions are append-only audited. |
| Existing academic track | Canonical import requires a pre-existing Backend-owned `academic_tracks.code`; missing/mismatched scope fails closed. Admin never creates or guesses a board/syllabus/version. |
| Canonical import idempotency | Exact replay returns the same durable publication operation and does not duplicate curriculum rows or publication items. |
| Immutable canonical conflicts | Existing canonical IDs/references with changed content return stable conflict and do not mutate existing published authority. |
| Publication idempotency | Exact publication replay returns the existing publication and creates no duplicate `content_publications`, audit publication record, or publication outbox event. |
| Changed snapshot conflict | Validated snapshot/hash mismatch fails before publication and leaves published state unchanged. |
| Atomic rollback | Failure inside canonical import/publication transaction rolls back domain mutations; sanitized failed checkpoint is recorded separately for recovery visibility. |
| Safe retry | After the underlying condition is repaired, retry reuses the durable operation safely and converges without duplication. |
| Supersession | Regeneration supersedes stale non-published request/import/draft operation deterministically; older published lesson versions supersede only after successful newer publication. |
| Audit/outbox | Staging/review/import/publication/failure/supersession have immutable audit evidence and redacted outbox events; no raw credentials, student answers or exception text. |
| AR/EN/FR + direction | Admin labels available in Arabic, English and French; Arabic operator UI is RTL and EN/FR LTR where applicable. |
| SQLite | Full Backend PHPUnit including Admin workflow passes on SQLite. |
| MariaDB 10.11.18 | Fresh migrations, synthetic fixture seed and full Backend suite pass. |
| Repository gates | Contracts/OpenAPI/tokens, Composer validate/audit, Pint, Larastan level 8, Web, Flutter Mobile, Gitleaks and dependency review all remain green. |

## Explicit regression assertions

Issue #19 tests must retain direct coverage for the following and must not be weakened to accept a different failure merely to obtain green CI:

1. only Admin/Content Team can operate official publication;
2. unauthorized users cannot publish;
3. arbitrary/UGC IDs cannot become official curriculum;
4. returned ZIP remains bound to its originating preparation request;
5. settings/hash mismatch cannot silently proceed;
6. stale requests visibly require `PREPARATION_REGENERATION_REQUIRED`;
7. exact publication replay creates no duplicate;
8. changed snapshot/payload conflict does not mutate published state;
9. publication failure rolls back atomically;
10. retries remain safe;
11. superseding/version behavior is deterministic;
12. publication uses an existing Backend-owned academic track;
13. no board/syllabus value is synthesized by Admin.

Manual real-content publication remains blocked until owner-controlled curriculum and rights inputs are available.