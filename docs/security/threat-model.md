# P0 threat model

## Assets and trust boundaries

Canonical assets are student accounts, academic history, answers, attempts, progress, official curriculum, provider links, content-preparation archives, and administrative privileges. Trust boundaries exist at public clients/API, Admin/API, third-party identity providers, optional AI helpers, ZIP import, database/queue, and cPanel deployment.

## Priority threats and controls

| Threat | Default controls | Verification |
| --- | --- | --- |
| Account takeover/linking confusion | Verified provider subject plus provider, explicit authenticated linking, recent-auth checks for sensitive changes, session revocation, rate limits, no email-only auto-linking. | Auth abuse-case integration tests. |
| Horizontal authorization | Backend derives actor from session and scopes every resource query; opaque IDs are not authorization. | Cross-user API tests. |
| Attempt manipulation | Server-only seed/order, persisted immutable positions and snapshots, state-machine guards, correct answers stripped from student payloads. | AC-P0-002..005 tests. |
| Replay/duplicate offline writes | Scoped idempotency records, canonical request hash, optimistic answer revision, transactional domain write plus outbox. | Timeout/replay/conflict tests. |
| Malicious returned ZIP | File count/size/ratio limits, normalized paths, no symlinks/traversal, media allowlist, per-file SHA-256, schema and semantic validation, staging before publication. | Golden malicious/invalid archives. |
| Unauthorized official content | Content Team/Admin roles, review state, provenance/rights field, no automatic UGC promotion. | Policy and workflow tests. |
| Minor targeting or unsafe ads/community | Unknown/stale age defaults safe/off, global kill switch, immutable no-ad zones, community disabled until P1 approval, no DMs. | Policy precedence tests. |
| PII/secrets leakage | Structured allowlisted logging, opaque identifiers, no raw answers/tokens/emails by default, secret scanning, synthetic fixtures. | Log inspection and repository scans. |
| Dependency/build compromise | Lockfiles, exact runtime pins, Composer/npm audits, dependency review, minimal CI permissions. | CI gates and periodic update PRs. |
| Availability/job duplication | Database queue, bounded retries/backoff, unique jobs/idempotency, chunk checkpoints, outbox, dead-letter visibility. | Worker interruption and retry tests. |

## Data minimization

Firebase is auxiliary and receives no canonical product state. Optional Admin AI is disabled without configuration and receives no student PII by default. Retention, backup, RPO, and RTO values remain owner decisions; until approved, code must support archival/deletion workflows without claiming a production retention policy.

## Security-release blockers

Production identity provider credentials, legal controller/contact facts, age/ad/community activation, backup/retention/RPO/RTO, app identifiers/signing, and real-content rights require owner evidence. Their absence blocks only the affected production release path.
