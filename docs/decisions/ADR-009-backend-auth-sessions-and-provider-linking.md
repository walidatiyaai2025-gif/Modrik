# ADR-009 — Backend-owned Auth sessions and provider linking

- Status: Accepted for P0 implementation
- Date: 2026-08-20
- Owners: P0-AUTH-001 / Issue #15
- Traces: REQ-P0-001, AC-P0-013

## Context

MODRIK needs a production-shaped account lifecycle while retaining Laravel as the canonical identity and authorization authority. BOOT-008 fixture authentication is intentionally synthetic, and production Google/Apple identifiers, secrets, callback URLs, Apple signing material, and store identifiers are still owner-controlled inputs. The implementation therefore needs useful production contracts without inventing provider configuration or turning fixture credentials into real authentication.

## Decision

### Backend-owned opaque sessions

- Production API sessions use a random opaque bearer token returned once at session creation.
- Only a SHA-256 digest is persisted in `auth_sessions`; raw tokens, IP addresses, and user-agent strings are not stored.
- IP/user-agent context is HMAC-hashed for abuse/security correlation only.
- Sessions have explicit expiry, last-use, recent-authentication, revocation timestamp, and revocation reason.
- Password reset and account deletion revoke every session. Password change and provider unlink revoke other sessions while preserving the explicitly authenticated current session where the operation contract allows it.
- P0 defaults are configurable externally: 30-day session TTL and a 10-minute recent-authentication window.
- Cookie-based first-party Web sessions may be added later, but any such cookie must be Secure, HttpOnly, SameSite-appropriate, CSRF-protected, and must resolve to the same backend-owned account/session authority.

### Password and one-time tokens

- Framework password hashing is the sole password storage mechanism.
- Email-verification and password-reset tokens are random one-time values; only SHA-256 digests are stored.
- Issuing a replacement token revokes earlier unconsumed tokens for the same purpose.
- Password reset is one-time and revokes every existing session.
- Public recovery is enumeration-resistant: an existing eligible account, missing account, and rate-suppressed request receive the same accepted response.
- Login performs a dummy password verification when no usable password account exists so the public invalid-credential path does not deliberately expose account existence through response shape.

### Provider linking

- `provider + stable subject` is the provider identity key. Provider email is mutable metadata, never the provider identity key.
- A matching provider subject always resolves the already-linked MODRIK profile even if a later provider response hides the email, changes it, or Apple uses/changes a private relay address.
- A verified provider email that matches an existing MODRIK account does **not** auto-link a previously unseen provider subject. The client receives an explicit-link-required conflict and must authenticate the existing account first.
- Linking/unlinking requires a recent production session. A provider identity already linked to another MODRIK profile is a hard collision and cannot be moved implicitly.
- Unlinking is denied if it would leave the account with neither a verified password recovery identity nor another active provider identity.
- Provider intents persist only hashed state and nonce. Callbacks must prove state freshness plus provider, signature, issuer, audience, expiry, nonce, and stable subject validation.
- `ProviderIdentityVerifier` is the transport/cryptographic adapter boundary. The default binding fails closed with `PROVIDER_CONFIGURATION_PENDING`. Production Google/Apple IDs, secrets, callback URLs, Team/Key IDs, private keys, and provider SDK/JWKS transport remain external inputs; none are fabricated in source.

### Safe account deletion

- P0 deletion is an auditable logical deletion so academic attempts/progress and other referential history are not silently destroyed.
- The canonical user row is retained as a tombstone, direct account PII is replaced with non-routable internal values, password authentication is disabled, provider email/subjects are scrubbed, open provider intents/tokens are invalidated, and every session is revoked atomically.
- Final retention/purge periods remain an owner/legal decision and are not guessed by the Auth implementation.

### Fixture boundary

- `/v1` learning APIs resolve a production session first and may fall back to the single BOOT-008 fixture bearer only when `MODRIK_FIXTURE_MODE=true` and the configured fixture token matches.
- Auth lifecycle endpoints use production-session middleware only. A fixture bearer can never list/revoke production sessions, link providers, change passwords, or delete an account.

## Consequences

- Mobile and other API clients can safely hold an opaque token without making Laravel session tables or provider SDKs canonical identity sources.
- Provider transport can be wired later without changing the linking/collision/account model.
- Provider-only users with no routable email can exist without inventing a recoverable email address; their internal `@accounts.invalid` address is never presented as a real user email.
- Real Google/Apple end-to-end activation remains blocked until owner-provided configuration and provider transport review are complete.
- Deletion preserves referential integrity but does not settle legal retention; release approval still requires the external retention decision.
