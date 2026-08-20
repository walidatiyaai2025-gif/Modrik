# Student Web Auth accessibility and state matrix — P0-WEB-AUTH-002

Scope: Issue #30. This matrix covers the Student Web consumption of the already-merged Auth Issue #15 contract. Backend identity/provider verification, collision policy, migrations and authorization remain unchanged.

## Automated evidence

| Contract | Automated evidence |
| --- | --- |
| Opaque production session | `web-session.test.tsx` requires HttpOnly + SameSite cookie storage and verifies `access_token`, `token_type` and bearer material are stripped from browser JSON. |
| Revoked/expired session | `auth-api.test.tsx` verifies Backend `401 AUTHENTICATION_REQUIRED` remains a typed client state; `SessionExpiredNotice` is an assertive screen-reader alert. The BFF clears the Web session cookie on 401. |
| Email verification | `auth-api.test.tsx` verifies only the existing one-time `{token}` body is posted to `/api/auth/email/verify`; no client verification authority is introduced. |
| Provider pending boundary | `auth-api.test.tsx` verifies the entry point requests only the existing Backend provider intent. `auth-workspace.test.tsx` verifies the pending state is announced; no client ID/secret exists in the intent or source configuration. |
| Enumeration resistance | `auth-workspace.test.tsx` checks recovery confirmation copy is conditional and does not assert that an account exists or does not exist. Backend problem responses are not rewritten into existence-revealing client copy. |
| AR/EN/FR + RTL/LTR | `auth-workspace.test.tsx` requires identical complete copy keys for all three locales and Arabic RTL / English-French LTR direction. |
| Keyboard/screen-reader foundation | Server-render smoke requires skip navigation, live status, `aria-busy`, branded loading state and no credential material. Interactive UI uses native buttons, inputs, forms, labels and navigation semantics. |
| Password policy | Web registration/reset/change inputs enforce the existing Backend 12–128 character policy; Backend remains authoritative. |

## Manual account lifecycle matrix

Run against a Backend with production-shaped Auth enabled and provider production configuration intentionally absent unless the case explicitly supplies owner-approved external provider configuration.

| Case | Required Web result |
| --- | --- |
| First visit, no session | Branded Loading state is announced, then the login surface appears. No learning API is called with a fixture bearer unless fixture mode is explicitly enabled. |
| Registration | Name/e-mail/password form creates an account through the existing endpoint; password has 12–128 client constraint; returned bearer token never appears in DOM, browser JSON, localStorage or sessionStorage. Unverified status is announced. |
| Password login | Invalid-known and invalid-unknown credentials render the same generic rejection copy. Successful login stores only an HttpOnly server-set Web cookie. |
| Verification | One-time token may be supplied from `?verify_token=` or manually. Success is announced and the URL token is removed with `history.replaceState`. Invalid/expired token renders a generic request failure without exposing server internals. |
| Verification resend | Authenticated unverified account can request resend. Success copy says a message is sent only if still needed; rate-limit/error remains retryable UI state without account disclosure. |
| Recovery | Validly shaped e-mail always receives the same accepted confirmation copy. The screen does not distinguish absent, unverified, provider-only or eligible accounts. |
| Password reset | One-time token may be supplied from `?reset_token=` or manually. Success returns to login because Backend revokes every existing session. |
| Session bootstrap | Existing HttpOnly session authenticates `/api/auth/session` and learning requests server-side. Browser code never reads the opaque token. |
| Session expiry/revocation | Periodic/visibility recheck receives Backend 401, clears local account/session presentation, announces that the session ended, and returns to login. |
| Logout current | Current session is revoked through Backend, HttpOnly cookie is cleared, and login is shown. |
| Revoke others | Session list reloads after Backend revocation. Empty state explicitly reports no other active sessions. |
| Revoke all | Backend revokes all sessions, Web cookie is cleared and the current browser returns to login. |
| Recent-auth required | Sensitive Backend 403/recent-auth response opens a password confirmation panel. Retrying sensitive work occurs only after Backend reauthentication succeeds; Web never self-grants recent-auth. |
| Password change | Current/new password are sent to the existing endpoint. Success copy notes Backend policy revokes other sessions; session list refreshes. |
| Account deletion | User must type exact `DELETE`; Backend remains responsible for recent-auth and logical anonymization/revocation. Success clears session presentation. |
| Google login / Apple login | Web creates Backend state/nonce intent only. Without owner-approved production provider client setup, UI announces Provider setup pending; no fake redirect/client ID/secret is generated. |
| Google/Apple link | Authenticated Web creates link intent only. Backend recent-auth requirement is surfaced as Permission state. Provider collision/linking policy is not duplicated in Web. |
| Provider callback error/pending | `503 PROVIDER_CONFIGURATION_PENDING`, invalid assertion, collision and generic provider failures render bounded provider/error state; raw provider tokens or backend security detail are never displayed. |

## Accessibility, locale and responsive matrix

| Case | Required result |
| --- | --- |
| Keyboard only | Skip link is first useful focus target; locale, forms, provider entry, account navigation, session controls and danger actions are reachable in DOM order. Enter/Space work through native controls and there is no trap. |
| Screen reader | Loading/offline/success/provider-pending use polite status; request/session failures use alert where immediate attention is required; labels identify every credential/token field; navigation landmarks have names. |
| 200% zoom / large default text | Split login layout collapses to one column as needed; account grid stacks; labels/buttons/session rows reflow without clipped essential text or fixed-height containers. |
| Reduced motion | Global `prefers-reduced-motion` rules remain effective; no Auth state depends on animation. |
| English | Complete account copy, LTR. |
| French | Complete account copy, LTR; long labels reflow without overlap. |
| Arabic | Complete account copy, RTL with logical CSS spacing; e-mail/tokens/DELETE retain explicit LTR where necessary. |
| High contrast preference | Auth cards/forms/notices receive stronger borders; visible focus remains token-based. |

## Required data/failure states

| State | Expected behavior |
| --- | --- |
| Loading | Branded session-bootstrap status, `aria-busy=true`. |
| Empty | Active-session panel explicitly states that no other active sessions exist; absent profile detail is described rather than fabricated. |
| Error | Bounded generic error with no stack/provider secret/account-existence disclosure. |
| Offline | Prominent account offline banner; account mutations/provider intents are disabled while the existing learning workspace retains its separately owned offline behavior. |
| Retry | Session list and other retryable account reads expose a native Retry action. |
| Permission | Unverified e-mail and recent-auth requirements use dedicated permission presentation; Web does not bypass Backend gates. |
| Provider pending | Dedicated non-error pending configuration state explains that external provider configuration is absent and Web will not invent it. |

## External production blocker

The repository intentionally contains no Google/Apple production client IDs, secrets, callback configuration, signing material or bundle identifiers. Owner-approved external provider configuration is required before a real provider SDK/redirect can proceed past the Backend intent boundary. This does not block password lifecycle/session UX or the fail-closed provider-pending state.
