# MODRIK Student Web

Desktop/laptop-first Next.js 16 application for the student learning experience plus release-candidate public information surfaces. It consumes `@modrik/design-tokens`; it is **not** the temporary public Coming Soon deployment.

Use Node.js 22.23.2 and npm 10.9.8 from the repository pins.

```bash
npm ci
cp .env.example .env.local
npm run lint
npm run typecheck
npm run test
npm run build
```

## Production account and session UX

Issue #30 / `P0-WEB-AUTH-002` consumes the already-merged Auth Issue #15 contracts without redefining Auth authority. Registration, password login, email verification/resend, enumeration-resistant recovery, password reset, session bootstrap/expiry, logout, other/all-session revocation, recent-auth confirmation, password change, safe deletion confirmation, and Google/Apple login/link entry points are Web presentation/client concerns only.

Browser JavaScript never receives or stores the Backend opaque bearer token. The same-origin `/api/auth/*` Next Route Handler accepts the existing Backend response, removes `access_token` / `token_type` before returning JSON to the browser, and stores the opaque token in an HttpOnly, SameSite=Lax, path-scoped cookie; production cookies also carry `Secure`. Unsafe Auth and learning mutations must pass same-origin Origin/Fetch-Site validation. A Backend 401 clears the session cookie and the UI returns to an explicit expired/revoked-session state. The `/api/learning/*` bridge prefers this production session and uses the synthetic fixture bearer only when `MODRIK_FIXTURE_MODE=true`.

The Web app does not fabricate an account-profile read contract that the Backend does not expose. After a reload, `/v1/session` is sufficient to authenticate and authorize the workspace, while e-mail/provider profile detail remains absent until an existing Backend response supplies it. Likewise, Google/Apple buttons create the existing Backend-owned state/nonce intent only. Production client IDs, callbacks, secrets and signing material remain external owner inputs; until configured, the UI shows a provider-pending state rather than inventing provider configuration.

Verification links may open the Web app with `?verify_token=...`; password reset links may use `?reset_token=...`. These are one-time Backend tokens and are consumed only through the existing Auth endpoints. Recovery confirmation copy deliberately does not disclose whether an account exists.

AR, EN and FR copy is complete for the account flows. Arabic switches the account shell to RTL; native forms/buttons, labels/autocomplete, skip navigation, visible focus, polite status and assertive error regions, reduced-motion behavior, logical CSS properties and fluid layouts cover keyboard, screen-reader and large-text foundations. The dedicated manual/automated matrix is `docs/qa/student-web-auth-matrix.md`.

## Student workspace

Issue #17 / P0-WEB-001 owns the presentation and application-client layer in `apps/web`. The workspace is deliberately desktop-first rather than a stretched phone shell and contains four first-class views: dashboard/home, study, practice and progress. The active academic context is presented with its reset consequences. The Backend-owned authorized track catalogue is defined by merged Issue #21; Web catalogue selection is consumed only through Issue #33 and must not invent board/syllabus/version values or eligibility rules.

AR, EN and FR are complete UI locales. Arabic switches the workspace to RTL; lesson/question/option text uses content-aware direction so mixed Arabic/Latin material remains readable. Native buttons, fieldsets, landmarks, headings, skip navigation, visible focus, live status/error regions, reduced-motion CSS and fluid layouts form the accessibility baseline. The manual verification matrix is in `docs/qa/student-web-accessibility-matrix.md`.

## Public release-candidate surfaces

Issue #32 / P0-RELEASE-001 adds the public information routes `/landing`, `/help`, `/admin-guide`, `/about`, `/goal`, `/vision`, `/mission`, `/disclaimer`, `/privacy`, `/terms`, `/safety`, `/cookies`, `/content-policy`, `/account-deletion`, `/support`, and `/contact`.

These routes are generated from one typed multilingual content contract in `src/public-site/`. AR/EN/FR copy, RTL/LTR direction, route links, SEO canonical/language alternates, legal blocker IDs and index policy are tested together. The Web public logo is a byte-for-byte reuse of the canonical Coming Soon horizontal SVG; public CSS consumes the canonical design-token variables.

Legal/support/contact surfaces that still depend on owner/legal facts visibly identify themselves as unapproved templates and set `robots.index=false`. The release code does not invent legal entity/controller/contact, jurisdiction, vendors/transfers, retention, age/guardian policy, safety escalation contacts, copyright/takedown contact, support hours, legal version or effective date. `docs/legal/public-pages-matrix.md`, `docs/release/release-inputs.md` and `docs/qa/public-release-matrix.md` define that boundary.

The root Student Web route remains the application workspace. `deploy/coming-soon/` remains the canonical dependency-free public shell under ADR-005; Issue #32 does not perform a domain/cPanel cutover. Coming Soon Smoke is triggered by changes to the release-candidate public application so regressions in the canonical shell remain visible without modifying the shell itself.

## Backend authority

The same-origin `/api/learning/*` Route Handler proxies only allowlisted learning endpoints. With a production Web session it forwards the HttpOnly opaque session bearer server-side. Synthetic BOOT-008 fallback reads `MODRIK_FIXTURE_BEARER_TOKEN` only when `MODRIK_FIXTURE_MODE=true`; fixture credentials are never `NEXT_PUBLIC_` variables or returned to the browser.

Practice is Backend-authoritative: `POST /attempts` sends only the quiz identifier plus an idempotency key, never a seed, question list or order. An in-progress attempt ID may be remembered locally only as a resume pointer; reconnect uses `GET /attempts/{id}` and renders the persisted question/option order exactly as returned. Scoring, academic lifecycle decisions, mastery and assessment authority remain Backend-owned.

For the fixture slice, migrate/seed and serve the Backend first. With the Backend running, `npm run smoke:fixture` proves session → context → lesson → answers → submit → progress over HTTP. Set `MODRIK_FIXTURE_MODE=true` only in this synthetic local/CI environment.

The typed server proxy allowlists academic-context activation/reset for onboarding consumers. Merged Issue #21 owns the authorized catalogue contract; Issue #33 owns Web/Mobile consumption. Real board/syllabus/version inputs remain owner-controlled and must not be hardcoded.

The application cannot depend on Vercel-only core behavior. Deployment remains target-neutral until cPanel capabilities are proven. Before Next.js changes, follow `AGENTS.md`, `PROJECT_CONTROL.md`, and read the matching bundled guide in `node_modules/next/dist/docs/`.
