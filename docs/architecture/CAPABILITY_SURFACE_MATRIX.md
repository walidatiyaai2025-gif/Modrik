# MODRIK capability → visual surface matrix

Status: Issue #177 audit baseline

This matrix prevents a backend capability from being mistaken for a missing UI and prevents an operator-facing capability from becoming reachable only by a hidden URL or remembered identifier.

| Capability / authority | Visual surface | Discoverability | Verdict |
| --- | --- | --- | --- |
| Authentication: register, login, verification, recovery/reset | Student Web account access | `/student` login/account experience | Visible |
| Session management, password change, provider linking, account deletion | Student Web → Account | Top student Account navigation | Visible |
| Academic catalogue/context activation/reset | Student Web learning workspace | Home / academic-context controls | Visible |
| Lesson/read experience | Student Web learning workspace | Study navigation | Visible |
| Authoritative assessment attempt/start/answer/submit | Student Web learning workspace | Practice navigation | Visible |
| Progress snapshots | Student Web learning workspace | Progress navigation | Visible |
| Offline answer synchronization / durable conflict reconciliation | Client/background behavior | Offline/retry/conflict states in learning UI; no standalone menu because sync is not user authority | Intentional background service |
| Advertising eligibility/placement decision | Backend policy boundary | Automatic placement decision; no manual student/admin switch because clients must not own ad policy | Intentional policy service |
| Content preparation settings, versioned prompt, bundle, returned ZIP | Admin | **Content Preparation** navigation | Visible |
| Saved preparation request discovery/history | Admin | **Preparation Requests** navigation | **Gap fixed by Issue #177** |
| Content rights evidence review | Admin | **Content Rights Review** navigation | Visible |
| Dry-run/diff, approve/reject/request-fix, canonical import, publish, retry/audit | Admin | **Content Review** navigation | Visible |
| Runtime/correlation diagnostics and outbox visibility | Admin | **Runtime Inspector** when `observability.inspector_enabled=true` and role is Admin | Intentionally gated |
| Idempotency, outbox dispatch, canonical hashes, publication transactions | Backend infrastructure | Surfaced through operation/checkpoint/error state and Runtime Inspector; no direct mutation menu | Intentional internal authority |
| Deployment release identity | Admin topbar | `Build <12-char SHA>` with full SHA tooltip | **Gap fixed by Issue #177** |
| Deployment release identity | Student Web | Build-time release SHA is embedded by Demo deploy workflow; visual placement tracked under Issue #177 | In progress |

## Navigation parity rule

A capability requires a first-class navigation entry when an authenticated human role is expected to initiate or browse that capability as a normal workflow. Background synchronization, policy engines, idempotency controls, immutable assessment authority, and transactional infrastructure must **not** receive manual UI controls that would move authority into the client.

Any future P0/Pilot capability added to Backend routes or operator services must update this matrix and either:

1. identify its visible Admin/Student surface and navigation path, or
2. explicitly classify it as an intentional background/internal/policy boundary with the user-visible status/failure state that represents it.

## Issue #177 verified gaps

The first Demo review exposed a real discoverability defect: `ContentPreparationWizard` could reload a stored request using `?request=<id>`, but the Admin had no history/list surface to discover those IDs. Issue #177 adds a Preparation Requests page so stored settings, prompt/bundle workflow, and returned ZIP continuation are reachable from navigation.

The same review requested an immutable deployed-build indicator. The Demo deployment now records the deployed Git SHA for Laravel and embeds the SHA into the Student Web build; the Admin topbar renders the short build SHA with the full release in its tooltip so browser/cache confusion can be identified immediately.
