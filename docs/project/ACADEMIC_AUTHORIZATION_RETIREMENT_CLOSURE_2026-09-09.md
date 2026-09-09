# Academic Authorization Retirement — Integration Closure Evidence

Date: 2026-09-09
Issue: #309 — P2-ACADEMIC-AUTH-CLEANUP
Implementation PR: #344
Canonical work line: `task/p2-academic-auth-cleanup-309`

## Integrated implementation

PR #344 merged into `main` as `38660e6bc11b4deb422c667a4af27021b6cb7833` from exact accepted head `2c43629b061dc9fb619d879547cad73e8165b330`.

The integrated change retires the superseded `academic_track_authorizations` persistence surface only. It preserves `academic_tracks`, `user_academic_contexts`, `academic_context_transitions`, attempts, progress and curriculum history.

Implemented closure evidence:
- forward migration drops the superseded table;
- rollback recreates the former compatibility schema without restoring runtime authority;
- stale LearningSlice, Pilot and test writes were removed;
- executable repository-wide guards reject residual/reintroduced production, fixture/test, Web, Mobile, QA/script and external-contract consumers;
- SQLite and MariaDB 10.11 migration/full-suite paths validate the retirement and rollback behavior.

## Exact-head acceptance before integration

Exact PR head `2c43629b061dc9fb619d879547cad73e8165b330`:
- Bootstrap CI #1382 — SUCCESS, including the complete governed aggregate;
- Unified Release Package #103 — SUCCESS;
- Demo cPanel Package #467 — SUCCESS;
- reviews blocking integration — none;
- unresolved review threads — none.

Historical red candidate runs remain evidence of the residual-consumer defects that were repaired on the same canonical branch; they are not rewritten as successful.

## Exact-main verification after integration

Exact implementation main `38660e6bc11b4deb422c667a4af27021b6cb7833`:
- Bootstrap CI #1383 / run `34396451810` — SUCCESS;
- contracts/control-state/runtime-fixture guard — SUCCESS;
- Backend SQLite full suite — SUCCESS;
- MariaDB 10.11 migration round-trip + full Backend suite — SUCCESS;
- Web audit/lint/typecheck/tests/build — SUCCESS;
- Mobile analyze/tests/release-signing gate — SUCCESS;
- Secret Scan — SUCCESS;
- Pilot normal acceptance — SUCCESS;
- Pilot strict all-PASS acceptance — SUCCESS;
- final governed required-matrix aggregate — SUCCESS;
- Unified Release Package #104 / run `34396451769` — SUCCESS.

Dependency Review is expectedly skipped on the push-triggered Bootstrap run; it passed on the PR-triggered exact-head run #1382.

## Closure decision

Issue #309 is CLOSED / COMPLETED and PR #344 is MERGED. Repository evidence supports closure of this work item.

This closure does not advance the recorded Demo deployment SHA, does not authorize production cutover, and does not close unrelated live-hosting work.

## Explicit remaining boundaries

- #260 remains OPEN. Latest repository evidence requires root/WHM-level LiteSpeed remediation/verification and a fresh governed Demo deployment proving exact API, Web, Admin, Landing and Student release identity plus external smoke.
- #318 remains OPEN. Unified Installer engineering slices are integrated, but its live-hosting acceptance remains coupled to #260.
- Real-content rights/academic values and production legal/security/provider/cutover inputs remain owner/external gates.

`VERIFIED_FINAL_COMPLETE` is intentionally NOT claimed for the overall MODRIK project.
