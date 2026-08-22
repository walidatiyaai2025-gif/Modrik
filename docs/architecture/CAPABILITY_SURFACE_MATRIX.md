# MODRIK capability → visual surface matrix

Status: Issue #177 audit baseline

This matrix prevents a backend capability from being mistaken for a missing UI and prevents an operator-facing capability from becoming reachable only by a hidden URL or remembered identifier.

| Capability / authority | Visual surface | Discoverability | Verdict |
| --- | --- | --- | --- |
| Authentication: register, login, verification, recovery/reset | Student Web / Mobile account access | Account/Auth flows | Visible |
| Session management, password change, provider linking, account deletion | Student Web / Mobile Account | Account navigation and sensitive-action confirmation | Visible |
| Academic catalogue/context activation/reset | Student Web / Mobile learning shell | Academic-context selector/reset controls | Visible |
| Lesson/read experience | Student Web / Mobile learning shell | Study navigation | Visible |
| Authoritative assessment attempt/start/answer/submit | Student Web / Mobile learning shell | Practice navigation | Visible |
| Progress snapshots | Student Web / Mobile learning shell | Progress navigation | Visible |
| Offline answer synchronization / durable conflict reconciliation | Client/background behavior | Offline/retry/conflict states in learning UI; no standalone mutation menu because sync is not user authority | Intentional background service |
| Advertising eligibility/placement decision | Backend policy boundary | Automatic fail-closed placement decision; no manual student/admin override | Intentional policy service |
| Content preparation settings, versioned prompt, bundle, returned ZIP | Admin | **Content Preparation** navigation | Visible |
| Saved preparation request discovery/history | Admin | **Preparation Requests** navigation | **Gap fixed by Issue #177** |
| Content rights evidence review | Admin | **Content Rights Review** navigation | Visible |
| Dry-run/diff, approve/reject/request-fix, canonical import, publish, retry/audit | Admin | **Content Review** navigation | Visible |
| Runtime/correlation diagnostics and outbox visibility | Admin | **Runtime Inspector** only when `observability.inspector_enabled=true` and role is Admin | Intentionally gated |
| Idempotency, outbox dispatch, canonical hashes, publication transactions | Backend infrastructure | Surfaced through operation/checkpoint/error state and Runtime Inspector; no direct mutation menu | Intentional internal authority |
| Cross-module capability inventory | Admin | **System Capabilities / وظائف النظام** navigation | **Gap fixed by Issue #177** |
| Public Landing / Help / learner and admin guidance / trust templates | Public Web | Public-site navigation/routes; legal-finalization boundaries remain explicit | Visible public surface |
| Deployment release identity | Admin topbar | `Build <12-char SHA>` with full SHA tooltip | **Gap fixed by Issue #177** |
| Deployment release identity | Student/Public Web | Global top build badge using the immutable SHA embedded by Demo deployment | **Gap fixed by Issue #177** |

## Navigation parity rule

A capability requires a first-class navigation entry when an authenticated human role is expected to initiate or browse that capability as a normal workflow. Background synchronization, policy engines, idempotency controls, immutable assessment authority, and transactional infrastructure must **not** receive manual UI controls that would move authority into the client.

The Admin **System Capabilities** page is the visual parity registry: every implemented module is listed there even when the correct product decision is `background`, `policy`, `internal`, or `gated`. Human-operable capabilities link to their real surfaces.

Any future P0/Pilot capability added to Backend routes or operator services must update this matrix and either:

1. identify its visible Admin/Student/Mobile/Public surface and navigation path, or
2. explicitly classify it as an intentional background/internal/policy/gated boundary with the user-visible status/failure state that represents it.

## Issue #177 verified gaps

The first Demo review exposed a real discoverability defect: `ContentPreparationWizard` could reload a stored request using `?request=<id>`, but the Admin had no history/list surface to discover those IDs. Issue #177 adds a Preparation Requests page so stored settings, prompt/bundle workflow, and returned ZIP continuation are reachable from navigation.

The review also exposed a broader confidence gap: an operator could not distinguish a truly missing screen from a capability that is intentionally backend-owned. Issue #177 adds a read-only System Capabilities registry that lists interactive, Student, background, policy, internal, and gated modules without creating unsafe manual controls.

The same review requested an immutable deployed-build indicator. The Demo deployment records the deployed Git SHA for Laravel and embeds the same release SHA into the Student Web build. Admin renders it in the Filament topbar, and Web renders a global build badge; both display the short SHA and retain the full SHA as verification metadata so stale browser/cache output is immediately recognizable.
