# Authentication and account contract

Traces: `REQ-P0-001` / `AC-P0-013`. Implementation decision: `docs/decisions/ADR-009-backend-auth-sessions-and-provider-linking.md`.

## Canonical identity

Laravel owns the MODRIK user ID, account lifecycle state, authorization state, sessions, and provider links. Email/password, Google, and Apple are authentication identities attached to that user; Firebase is not the canonical authentication database.

Email lookup uses `email_normalized`. The original routable address may be retained for account communication, but public logs/errors/security events do not persist raw email. Provider-only identities that do not expose a usable email receive an internal non-routable `@accounts.invalid` placeholder; clients receive `null`, not that placeholder, as the account email.

## Email/password lifecycle

- Registration creates an active password account with an unverified email, emits a one-time verification token, and returns a backend-owned opaque session.
- Password accounts must verify email before protected learning mutations. Read-only account/session operations and verification/resend remain available while unverified.
- Verification resend is authenticated, rate-limited, and revokes the previous unused verification token.
- Login uses one stable `INVALID_CREDENTIALS` shape for nonexistent, deleted, provider-only, and wrong-password accounts. Failed-login rate limiting is keyed by hashed normalized email plus hashed request context; no raw email is placed in the limiter key.
- Public password recovery always returns the same accepted response for eligible, missing, ineligible, and rate-suppressed accounts. Recovery tokens are one-time and expire.
- Successful password reset enables password authentication if necessary, consumes the reset token, revokes sibling reset tokens, and revokes every existing session.
- Password change requires a recent authenticated production session plus the current password, rotates the password hash, revokes outstanding reset tokens, and revokes other sessions.
- Password policy is 12–128 characters for P0; framework password hashing remains authoritative.

## Session boundary

P0 API/mobile sessions are opaque bearer credentials. The raw token is returned once and only its SHA-256 digest is persisted. Session rows store expiry, last-use, authentication freshness and revocation metadata. IP/user-agent context is HMAC-hashed rather than persisted as raw PII.

Supported session actions:

- list current active sessions without token material;
- logout/revoke the current session;
- revoke every other session;
- revoke every session;
- reauthenticate the current password session to refresh the recent-authentication timestamp.

A password reset or account deletion revokes all sessions. Provider unlink and password change revoke other sessions. The configurable P0 defaults are a 30-day session TTL and a 10-minute recent-authentication window.

A future first-party Web cookie may resolve to the same backend-owned session authority. Any production cookie implementation must be Secure, HttpOnly, SameSite-appropriate, rotated, and CSRF-protected for cookie-authenticated mutations.

## Google/Apple provider behavior

`provider + stable subject` is the binding key. Provider email is mutable metadata only.

- Provider intents return one-time state + nonce while persisting only their hashes and expiry.
- Provider callback verification must cover state, nonce, provider, signature, issuer, audience, expiry and stable subject.
- A known provider subject resolves the already-linked MODRIK profile even if provider email changes, disappears, or Apple later returns/withholds a private relay address.
- A new provider subject is never auto-linked merely because a verified provider email matches an existing MODRIK account. That path returns `PROVIDER_LINK_REQUIRED`; the user must authenticate the existing account and create a recent-auth explicit link intent.
- A subject linked to another profile returns `PROVIDER_IDENTITY_CONFLICT`; it is never silently moved.
- Unlinking requires recent production authentication and is denied when it would remove the last usable recovery identity.
- Apple relay addresses are recorded only as provider metadata and never become the durable provider identity key.

`ProviderIdentityVerifier` is the adapter boundary for Google/Apple transport and cryptographic verification. The default implementation fails closed with `PROVIDER_CONFIGURATION_PENDING`. Production Google client ID/secret/callback URL and Apple client/Team/Key IDs, private key, callback URL, store identifiers and signing material remain external owner inputs. None are invented or committed.

## Safe account deletion

Deletion requires recent production authentication plus explicit `DELETE` confirmation. The operation is atomic and auditable:

- mark the canonical account `deleted` while preserving its backend user ID for referential history;
- replace direct account email/name/password material with non-routable tombstone values and disable password authentication;
- revoke every session and one-time token;
- consume open provider intents;
- scrub provider email and stable subject values while retaining only non-provider tombstone references;
- write an Auth security event without raw PII or credentials.

Final hard-purge/retention periods are owner/legal inputs and remain outside P0 Auth authority.

## Enumeration, abuse and logging

- Public invalid-login responses do not distinguish account existence or password/provider state.
- Public recovery responses do not distinguish account existence, eligibility, or rate suppression.
- Verification resend is authenticated, so rate-limit status can be explicit.
- Security events use opaque user/session IDs and HMAC context where needed; raw tokens, passwords, provider assertions, provider subjects, raw email, IPs and user agents are not written to logs/events.
- Provider callback errors expose stable problem codes, not provider tokens or cryptographic details.

## BOOT-008 synthetic fixture boundary

BOOT-008 still does not claim production account authentication. Learning APIs may accept the single configured synthetic bearer only when `MODRIK_FIXTURE_MODE=true`; the flag and token both fail closed when absent. Production Auth lifecycle endpoints use `auth.production` and never accept the fixture bearer. The Next.js fixture proxy continues to read its synthetic token server-side; it is neither prefixed `NEXT_PUBLIC_` nor returned to the browser.
