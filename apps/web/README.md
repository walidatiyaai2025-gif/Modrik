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

For the BOOT-008 local slice, migrate/seed and serve the Backend first. The same-origin `/api/learning/*` Route Handler reads the fixture bearer token only on the Next server and proxies the allowlisted learning endpoints. With the Backend running, `npm run smoke:fixture` proves session → context → lesson → answers → submit → progress over HTTP. Fixture credentials are never `NEXT_PUBLIC_` variables.

The application cannot depend on Vercel-only core behavior. Deployment remains target-neutral until cPanel capabilities are proven. Before Next.js changes, follow `AGENTS.md` and read the matching bundled guide in `node_modules/next/dist/docs/`.
