# MODRIK Children Ready Definition

Umbrella: #352

`CHILDREN_READY = YES` is a strict evidence-backed gate for the Year 6–7 learning experience. It is independent from Production/hosting readiness.

## Mandatory gates

All items below must be PASS on the authoritative integrated composition.

### Identity & academic context
- Student login/session works with real Backend auth.
- Student selects/retains authorized Year -> Track context.
- Cross-user access is rejected.
- Academic history is preserved across governed reset/change flows.

### Curriculum & content
- Year/Track/Subject/Unit/Topic/Skill hierarchy is authoritative and queryable.
- Required pilot subjects have published Skill coverage.
- Student-deliverable questions are `published` only.
- Every published question has valid skill mapping and provenance state.
- Content-rights/publication gate is truthful and fail-closed.
- No sample/demo/fake success is presented as real pilot content.

### Question Bank
- Supported question types render and submit correctly.
- Correct answers are not leaked before permitted reveal.
- Import validation rejects malformed/mismatched/stale packs.
- Duplicate handling is deterministic.
- Bulk review/publish flows are permission-safe and audited.
- Question reports/quality review can suspend bad content without data loss.

### Quiz/assessment runtime
- Practice session creation works.
- Server owns selection/order/seed where applicable.
- Same-attempt resume remains immutable where governed.
- Answer submission is idempotent/retry-safe.
- Scoring is Backend-authoritative.
- Persistence/reread occurs before truthful success.
- Exam mode enforces its no-hint/no-explanation policy until allowed.

### Mastery & adaptive study
- Student x Skill mastery is persisted and versioned.
- Recent performance, difficulty and hint/attempt signals are handled deterministically.
- Mastery history is preserved.
- Recalculation is idempotent.
- Weak/critical skills are selected correctly.
- Spaced repetition produces due reviews deterministically.
- Mistake Notebook moves through recovery states.
- Daily Plan is reproducible from persisted inputs.
- Diagnostic produces a baseline without assigning fake mastery to untested skills.

### Student UX
- Today's Mission works end to end.
- Continue Learning works.
- Skill/topic practice works.
- My Mistakes works.
- My Progress works.
- Empty/loading/error/offline/retry states are truthful.
- No required student flow depends on paid AI.

### Parent UX
- Parent sees only linked/authorized children.
- Each child is shown independently; no sibling ranking.
- Study activity, accuracy, mastery, trends and attention areas match Backend data.
- Empty/new-student state is understandable and does not fabricate conclusions.

### Arabic / English / accessibility
- Arabic RTL and English LTR pass on Student Web and Flutter.
- Admin surfaces added by this program satisfy current localization governance.
- Mixed-direction math/equations remain readable inside RTL.
- Default student question text is >= 18px equivalent.
- Large/extra-large text does not clip critical content.
- Touch targets are >= 44 logical px where applicable.
- 360/390/412 mobile widths plus tablet/desktop acceptance have no critical horizontal overflow.
- Directional icons/navigation behave correctly.

### Admin controls
- Content Workbench is discoverable.
- Prompt Library is discoverable and versioned.
- Feature controls are discoverable, permission-safe and audited.
- Job controls expose truthful status/history.
- Kill switches degrade safely without corrupting student state.
- Security/integrity invariants remain `internal_non_editable` rather than being exposed as unsafe toggles.

### Security / integrity / QA
- SQLite and MariaDB suites required by repository governance pass.
- Web type/lint/tests/build pass.
- Flutter analyze/tests and required native compile proof pass.
- Secret/security/dependency gates pass.
- Cross-user/direct-ID/IDOR cases fail closed.
- CSRF/session rules remain correct for applicable Web/Admin mutations.
- No production child PII exists in repository fixtures.
- Exact-head CI is green before integration.
- Exact-main post-merge verification is green before program closure.

### Real pilot acceptance
- One real Year 6 pilot profile and one real Year 7 pilot profile are configured with owner-approved scope.
- Real approved question content is available for both.
- Both profiles can complete: login -> Today's Mission -> answer -> score -> mastery update -> mistake/revision update -> progress view.
- Parent account can see truthful progress for both profiles independently.
- Open mandatory learning blockers = 0.

## Machine status

Canonical status is stored in `governance/MODRIK_STUDENT_READINESS.json`.

A computed readiness score of 100 is not sufficient if any mandatory gate is `pending`, `blocked`, `failed`, `unknown`, or `not_evidenced`.

## Allowed declarations

When all mandatory gates pass:

`MODRIK CHILDREN READY — 100%`

Do not substitute this for Demo/Production deployment completion. Current deployment gates continue under their own repository governance.