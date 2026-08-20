# P0 threat model

## Assets and trust boundaries

Canonical assets are student accounts, academic history, answers, attempts, progress, official curriculum, provider links, content-preparation archives, and administrative privileges. Trust boundaries exist at public clients/API, Admin/API, third-party identity providers, optional AI helpers, ZIP import, database/queue, and cPanel deployment.

## Priority threats and controls

| Threat | Default controls | Verification |
| --- | --- | --- |
| Account takeover/linking confusion | Stable provider subject plus provider, explicit authenticated linking, recent-auth checks for sensitive changes, session revocation, rate limits, and no email-only auto-linking. Provider email is metadata rather than identity. | Auth provider collision, Apple relay/hidden-email, recent-auth and session-revocation integration tests. |
| OAuth/OIDC callback replay or substitution | One-time expiring provider intents; only hashed state/nonce persisted; callback verifier contract requires provider, signature, issuer, audience, expiry, nonce and stable subject validation. Default provider transport fails closed until externally configured. | Invalid/expired intent, nonce/cryptographic validation and configuration-pending tests. |
| Credential enumeration and reset abuse | Public login uses one invalid-credential shape and dummy password verification when no usable password account exists. Public recovery always returns the same accepted shape for existing, missing, ineligible and rate-suppressed accounts. Verification resend/login/recovery use hashed-context rate-limit keys. | Known-vs-unknown response equality, rate-limit and reset-token abuse tests. |
| Stolen bearer/session replay | Random opaque session credentials are returned once; only SHA-256 token digests and HMAC request-context hashes are stored. Explicit expiry/revocation and recent-auth timestamps bound sensitive operations. Reset/deletion revoke all sessions; password change/provider unlink revoke other sessions. | Raw-token non-persistence, current/other/all revocation, password-reset revocation and deleted-account denial tests. |
| Horizontal authorization | Backend derives actor from session and scopes every resource query; opaque IDs are not authorization. | Cross-user API tests. |
| Academic-history loss or cross-track bleed | User-row serialization, reset-only track changes, explicit context IDs on attempts/progress, archive markers, transition audit, and active-context read filters. | REQ-P0-002 lifecycle/replay/preservation tests. |
| Attempt manipulation | Server-only seed/order, persisted immutable positions and snapshots, state-machine guards, correct answers stripped from student payloads. | AC-P0-002..005 tests. |
| Replay/duplicate offline writes | Scoped idempotency records, canonical request hash, optimistic answer revision, transactional domain write plus outbox. | Timeout/replay/conflict tests. |
| Malicious returned ZIP | Compressed and uncompressed byte, entry, file-count, and ratio limits; normalized relative paths; no symlinks/traversal/duplicates; exact manifest membership; media allowlist; per-file SHA-256; schema/binding/semantic validation; durable rejected state; staging before publication. | Traversal, Unix-symlink, compression-bomb, binding, hash, and semantic archive tests. |
| Unauthorized official content | Content Team/Admin route guard, backend-owned preparation bindings, provenance/rights state, fixture-only automatic staging, and no staging-to-publication write path. | Student-role denial, rights-boundary, and zero-curriculum-write workflow tests. |
| Minor targeting or unsafe ads/community | Backend-owned placement/zone mapping; missing/invalid/stale/non-adult age assurance defaults off; versioned configuration defaults absent/off; global kill switch; immutable no-ad zones; no birth date/tracking profile; community disabled until P1 approval, no DMs. | AC-P0-011/012 precedence, cross-user scoping, audit-redaction, and config-off tests. |
| Paid-AI availability or student-data egress | Paid AI disabled by default; no P0 provider transport; learning core has no AI dependency; optional context is backend-allowlisted to locale and synthetic content references while identity, answers, progress, and credentials are prohibited. | AC-P0-015 complete-core test with outbound HTTP forbidden; disabled-boundary and context-minimization tests; repository contract validation. |
| PII/secrets leakage | Structured allowlisted logging, opaque identifiers, no raw answers/tokens/emails by default, session IP/user-agent context HMAC-hashed, provider assertions never persisted, provider subjects scrubbed on deletion, secret scanning, synthetic fixtures. | Auth persistence assertions, deletion anonymization, log inspection and repository scans. |
| Dependency/build compromise | Lockfiles, exact runtime pins, Composer/npm audits, dependency review, minimal CI permissions. | CI gates and periodic update PRs. |
| Availability/job duplication | Database queue, 1–500 bounded cron batches, per-event row locks/published recheck, stable event IDs, exponential retries, five-attempt cap, sanitized failure fingerprints, and explicit deferred/exhausted counters. | Overlap-safe completed replay, interruption/retry, backoff, exhaustion, redaction, and MariaDB worker tests. |

## Account deletion and retention boundary

P0 account deletion is an atomic logical deletion that keeps the backend user ID for referential history while replacing direct account identity values with non-routable tombstones, disabling password authentication, revoking sessions/tokens, consuming provider intents, and scrubbing provider email/subject data. This prevents a deletion request from silently corrupting attempts/progress or leaving usable credentials behind. Final retention and hard-purge periods remain owner/legal decisions; Auth code does not invent them.

## Data minimization

Firebase is auxiliary and receives no canonical product state. Optional AI is disabled by default, has no provider transport in P0, and receives no student PII by default. Its machine-readable allowlist/prohibition contract is `docs/security/optional-ai-boundary.yaml`. Auth security events persist only opaque backend IDs/event type and optional HMAC context; raw passwords, one-time/session tokens, provider assertions, provider subjects, email, IP, and user-agent strings are excluded. Retention, backup, RPO, and RTO values remain owner decisions; until approved, code must support archival/deletion workflows without claiming a production retention policy.

## Security-release blockers

Production Google/Apple IDs, secrets, callback configuration, Apple signing material, provider transport/JWKS verification deployment, legal controller/contact facts, age/ad/community activation, backup/retention/RPO/RTO, app identifiers/signing, and real-content rights require owner evidence. Their absence blocks only the affected production release path; the default Auth provider verifier remains fail-closed.
