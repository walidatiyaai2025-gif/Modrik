# MODRIK Student Web

Desktop/laptop-first Next.js 16 application for the student learning experience. It consumes `@modrik/design-tokens`; it is not the temporary public Coming Soon release.

Use Node.js 22.23.2 and npm 10.9.8 from the repository pins.

```bash
npm ci
npm run lint
npm run typecheck
npm run test
npm run build
```

The application cannot depend on Vercel-only core behavior. Deployment remains target-neutral until cPanel capabilities are proven. Before Next.js changes, follow `AGENTS.md` and read the matching bundled guide in `node_modules/next/dist/docs/`.
