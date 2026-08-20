# ADR-008: paid AI is optional and data-minimized

- Status: Accepted
- Date: 2026-08-20
- Traceability: REQ-P0-013, AC-P0-015

## Context

Study, practice, authoritative attempts, answers, submission, and progress must remain available without a paid AI account or network dependency. A later optional assistant would create a new third-party trust boundary and must not inherit access to student records by convenience.

## Decision

Paid AI is disabled by default and no provider transport is implemented in P0. The learning core cannot call the optional-AI boundary. Automated integration coverage runs the complete synthetic learning flow with the switch off and Laravel outbound HTTP forbidden.

Any future optional adapter must enter through the backend boundary, require explicit activation, and receive only the allowlisted `locale`, `subject_reference`, and `lesson_reference` strings. User identifiers, contact details, age evidence, academic-context identifiers, attempts, answers, progress, mastery, credentials, and cookies are prohibited by default. Provider choice, endpoints, keys, and production activation remain owner-controlled inputs.

## Consequences

Core availability and assessment authority do not depend on paid AI. Adding a provider is a separate reviewed change requiring an owner-approved use case, privacy/provider review, an explicit transport, and no-student-PII integration tests. Clients cannot activate a provider or construct outbound context independently.
