# ADR-007: Fail-closed advertising eligibility

- Status: Accepted
- Date: 2026-08-20
- Traceability: REQ-P0-010, AC-P0-011, AC-P0-012

## Context

Advertising is a safety-sensitive backend decision. A client-supplied age, zone, stale configuration, or placement flag cannot be allowed to enable ads, and production activation still depends on owner/legal/safety decisions that are unavailable.

## Decision

Laravel owns a single precedence-ordered eligibility service. Clients submit only a placement code. The backend maps supported placements to code-owned zones and denies unknown placements. The zones `account`, `assessment`, `help`, `lesson`, `onboarding`, and `progress` are immutable no-ad zones.

The latest append-only policy version contains the global kill switch, effectiveness window, and per-placement flags. A decision denies in this order: unknown placement; immutable no-ad zone; missing policy; global kill switch; invalid, future, or stale policy; missing/disabled placement; missing, invalid, future, stale, or non-adult age assurance. Only a current `adult` assurance plus every enabled policy layer can produce `ELIGIBLE`.

Age assurance stores only a controlled band, source label, and assurance/expiry timestamps—never a birth date. The authenticated decision response exposes the decision, reason, policy version, placement, zone, and evaluation time, but not the user's age band or assurance source. Responses are `no-store, private` so an eligible result cannot survive a later kill-switch or assurance change. Every evaluated decision writes a minimal audit row and redacted transactional outbox event.

No policy mutation API, ad network SDK, tracking identifier, targeting profile, or production advertising activation is introduced by this slice.

## Consequences

Missing, stale, corrupt, or owner-unapproved state always resolves to ads off. Safety-zone changes require a reviewed backend release rather than mutable placement configuration. Decision audits add bounded operational data whose production retention still requires owner approval.
