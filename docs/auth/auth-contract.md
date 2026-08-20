# Authentication and account contract

## Canonical identity

Laravel owns the MODRIK user ID and authorization state. Email/password, Google, and Apple records are identities linked to that user; Firebase is not the canonical authentication database.

## Required behavior

- Normalize email for lookup while preserving a display-safe original where required.
- Require email verification for password accounts before protected learning mutations.
- Never auto-link solely because an unverified or differently sourced email string matches.
- Linking and unlinking require an authenticated, recent session and must not leave the account without a usable recovery identity.
- Provider callbacks validate state, nonce, issuer, audience, signature, expiry, and the provider-specific stable subject.
- Password recovery, password change, provider unlink, deletion request, and suspected compromise revoke relevant sessions.
- User enumeration is suppressed in public login/recovery responses and logs.
- Admin and Content Team authorization is explicit and server-side; possession of an Admin URL is not access.

## Session boundary

The initial Web/API implementation may use secure same-site cookies for first-party Web and scoped opaque bearer tokens for Mobile. Exact token implementation needs its implementation ADR. All production cookies require Secure, HttpOnly, appropriate SameSite, rotation, and CSRF protection for cookie-authenticated mutations.

Production Google/Apple client IDs, secrets, callback URLs, store bundle identifiers, and signing material are BLOCKED owner inputs and never enter source control.

## BOOT-008 synthetic fixture boundary

BOOT-008 does not claim production account authentication. For deterministic local/CI validation only, Laravel may accept one configured synthetic bearer token when `MODRIK_FIXTURE_MODE=true`. The middleware fails closed when the flag or token is absent, authenticates only the synthetic fixture user, and must never be enabled in production. The Next.js proxy reads the token server-side; it is neither prefixed `NEXT_PUBLIC_` nor returned to the browser.
