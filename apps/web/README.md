# MODRIK Student Web

Desktop/laptop-first Next.js 16 application for the student learning experience. It consumes `@modrik/design-tokens`; it is not the temporary public Coming Soon release.

Use Node.js 22.23.2 and npm 10.9.8 from the repository pins.

```bash
npm ci
cp .env.example .env.local
npm run lint
npm run typecheck
npm run test
npm run build
```

## Student workspace

Issue #17 / P0-WEB-001 owns the presentation and application-client layer in `apps/web`. The workspace is deliberately desktop-first rather than a stretched phone shell and contains four first-class views: dashboard/home, study, practice and progress. The active academic context is presented with its reset consequences, but the Web client does not invent a board/syllabus/track catalogue that the Backend does not expose.

AR, EN and FR are complete UI locales. Arabic switches the workspace to RTL; lesson/question/option text uses content-aware direction so mixed Arabic/Latin material remains readable. Native buttons, fieldsets, landmarks, headings, skip navigation, visible focus, live status/error regions, reduced-motion CSS and fluid layouts form the accessibility baseline. The manual verification matrix is in `docs/qa/student-web-accessibility-matrix.md`.

## Backend authority

The same-origin `/api/learning/*` Route Handler reads the fixture bearer token only on the Next server and proxies only allowlisted learning endpoints. Fixture credentials are never `NEXT_PUBLIC_` variables.

Practice is Backend-authoritative: `POST /attempts` sends only the quiz identifier plus an idempotency key, never a seed, question list or order. An in-progress attempt ID may be remembered locally only as a resume pointer; reconnect uses `GET /attempts/{id}` and renders the persisted question/option order exactly as returned. Scoring, academic lifecycle decisions, mastery and assessment authority remain Backend-owned.

For the fixture slice, migrate/seed and serve the Backend first. With the Backend running, `npm run smoke:fixture` proves session → context → lesson → answers → submit → progress over HTTP.

The typed server proxy also allowlists academic-context activation/reset for onboarding consumers. Real board/syllabus choices remain absent until owner-approved data and a Backend selection contract exist. Web Issue #17 documents that dependency rather than hardcoding synthetic production choices.

The application cannot depend on Vercel-only core behavior. Deployment remains target-neutral until cPanel capabilities are proven. Before Next.js changes, follow `AGENTS.md` and read the matching bundled guide in `node_modules/next/dist/docs/`.
