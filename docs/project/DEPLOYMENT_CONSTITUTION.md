# MODRIK Deployment Constitution

Status: **LOCKED ENGINEERING GOVERNANCE**  
Contract ID: `GOV-DEPLOY-001`  
Applies to: Demo and any future cPanel/LiteSpeed/CloudLinux deployment path derived from it.

This document is a project constitution, not an operational suggestion. A deployment implementation, agent, workflow or manual procedure that conflicts with it is non-compliant until this document is explicitly amended through an owner-authorized, reviewed PR with executable regression coverage.

## 1. Deployment truth

A deployment is successful only when the **public runtime serves the exact immutable source release that was authorized**.

The following are necessary evidence but are never sufficient by themselves:

- source merged to `main`;
- CI green;
- package assembled;
- artifact uploaded;
- FTPS completed;
- Laravel migrated/cached;
- CloudLinux Selector returned `success`;
- a restart command returned zero;
- a manual cPanel restart was clicked;
- files on disk contain the new SHA.

The authoritative success condition is the complete governed acceptance path:

1. exact canonical `main` SHA resolved;
2. immutable artifact built for that SHA;
3. artifact passes local runtime acceptance;
4. server-side exact-Node preflight passes from the copied live payload;
5. hosting runtime registration matches the locked desired state;
6. runtime is restarted/recycled through the supported hosting control plane;
7. cPanel origin serves the exact SHA on required Web routes;
8. public API and portal smoke passes;
9. only then may deployment-success markers advance.

No step may be waived to make a deployment appear green.

## 2. Locked Demo hosting desired state

Until an owner-authorized hosting migration changes this contract, the Demo Web desired state is:

- Host: `demo.modrik.org`.
- Platform: cPanel + CloudLinux Node.js Selector + LiteSpeed Web Server.
- cPanel account Application Root: `public_html/demo.modrik.org`.
- Node line: repository-locked Node `22.23.2` / compatible installed Node 22 runtime.
- Application mode: `production`.
- Application URL/domain: `demo.modrik.org`.
- Web product: Next.js standalone server runtime; **not** a static export.
- Canonical CloudLinux startup file: **root-level `server.js`** in the cPanel Application Root.
- `WEB_APPLICATION_ROOT.txt` must contain `.` for this topology so the deployment runner and CloudLinux resolve the same root startup.
- The root `server.js` is an artifact-owned CommonJS bootstrap that injects immutable release identity, changes into the untouched generated Next standalone application directory, and loads Next's generated `server.js`.
- A nested startup registration such as `apps/web/server.js` is prohibited for this topology even when that is where a monorepo build places Next's generated server internally.
- `startup.cjs` may exist only as a compatibility/rollback bridge and must delegate to root `server.js`; it is not the canonical LiteSpeed startup.
- Backend: Laravel under `api.demo.modrik.org`, preserving its live `.env` and `storage` boundary.

CloudLinux's cPanel Node.js Selector UI defines the startup as a `NAME.js` file, while LiteSpeed documents Next.js standalone `server.js` as the supported startup form. MODRIK therefore exposes one root-level `server.js` that satisfies the Selector filename contract while preserving Next's traced monorepo standalone layout behind it.

The governed incident evidence from 2026-08-23 is binding until disproven: Selector accepted both `startup.cjs` and nested `apps/web/server.js`, but LiteSpeed produced neither a serving `lsnode` runtime nor application `stderr.log`; the exact Node 22 standalone server itself passed loopback preflight. Repeating those registrations is prohibited.

## 3. Immutable artifact contract

Every deployable Web artifact must contain all information required to identify and start itself without mutable per-release cPanel environment edits.

Required Web payload invariants:

- `RELEASE_SHA.txt` at Web payload root;
- `WEB_APPLICATION_ROOT.txt` at Web payload root and its value is exactly `.`;
- canonical root-level `server.js` at Web payload root;
- package-level and Web-level release SHA values are byte-identical;
- canonical root `server.js` injects the packaged release identity before Next runtime loads;
- canonical root `server.js` delegates to the generated Next standalone `server.js` without flattening or deleting traced monorepo dependencies;
- `.next/static` and `public` assets are co-located with the generated Next standalone application as required by Next runtime;
- no live `.env`, credential, token or production data enters the artifact.

A package that cannot boot its **canonical root `server.js`** in CI is undeployable.

## 4. Runtime reconciliation, not blind restart

The hosting configuration is desired state and must be reconciled before activation.

For the target application, deployment automation must read actual CloudLinux Selector state and compare at minimum:

- application root;
- domain/application URL;
- Node version line;
- application mode;
- startup file;
- started/stopped state.

Rules:

- A missing or ambiguous target application is a hard failure; do not create/destroy an application implicitly.
- Root/domain/version drift is a hard failure unless the current Issue explicitly owns that migration.
- Startup-file drift may be reconciled only to root-level `server.js`, followed by read-back verification.
- Selector `success` output is not enough; the generated/observable runtime configuration must match the requested value.
- Do not directly hand-edit Selector-managed `.htaccess` as a normal deployment mechanism.
- Do not repeat unbounded restart loops.

## 5. Exact-Node preflight is mandatory

After the live Web payload is copied but before public activation, deployment must launch the exact live canonical **root `server.js`** using the same Node binary selected by the hosting runtime, on a bounded loopback-only port.

It must verify:

- Landing runtime marker;
- exact full release SHA;
- short build badge;
- no global error boundary;
- process remains healthy for the bounded probe.

The temporary process must always be terminated. Preflight failure is a deployment failure and triggers transactional Web recovery.

This isolates application/package failure from hosting activation failure and must never be removed to shorten deploy time.

## 6. Activation and convergence

After preflight and desired-state reconciliation:

1. reconcile CloudLinux startup to root-level `server.js` and verify Selector read-back;
2. normalize the hosting restart marker permissions required by LiteSpeed/Selector;
3. request one canonical CloudLinux restart through the documented CageFS end-user path;
4. probe the cPanel origin for exact release identity;
5. if needed, perform one bounded `stop -> start` recycle;
6. probe again;
7. if exact identity still does not converge, fail closed and rollback.

Manual cPanel restart is an emergency diagnostic or recovery tool only. It is never accepted as normal release evidence and must not be required for routine deployment.

## 7. Transactional rollback boundary

Before Web mutation, deployment must back up both:

- previous Web payload;
- previous hosting runtime registration values that the deployment is authorized to mutate, including startup file.

If any post-mutation gate fails:

- restore previous Web payload;
- restore previous startup-file registration;
- normalize restart marker;
- request restart of the restored application;
- do not roll back database migrations automatically unless a specific migration contract proves that rollback safe;
- do not advance deployment-success markers.

A rollback that restores files but leaves runtime registration pointing at the failed release is incomplete.

## 8. LiteSpeed diagnostics

Diagnostics must follow the actual hosting implementation.

- The official LiteSpeed Node Selector integration may not expose Apache Passenger processes/log behavior.
- Application `stderr.log` under the application root is a primary LiteSpeed Node startup diagnostic source when available.
- Safe process inspection should look for LiteSpeed Node runtime (`lsnode`) as well as Node processes, without exposing environment variables, secrets or arbitrary request data.
- Diagnostic logging is observability; inability to configure an optional custom log must not itself make an otherwise healthy release undeployable.
- All emitted diagnostics must be bounded and redact credentials/tokens/cookies/secrets.

If exact-Node preflight passes, desired state matches, Selector activation returns success, but no LiteSpeed Node runtime can be spawned, classify the event as a hosting-runtime blocker. Before escalating outside the repository, verify the startup registration conforms to this root `NAME.js` contract; do not regress to `.cjs` or nested startup paths.

## 9. External acceptance

A Demo release is not complete until external smoke confirms, against the same exact SHA:

- API health;
- public Landing `/`;
- Student Portal `/student`;
- Admin boundary/identity required by the governed workflow;
- expected route/runtime markers;
- absence of the global Web error boundary.

Cache-busting/no-cache requests and direct-origin verification remain required where the workflow defines them.

## 10. CI and merge law

Any PR changing deployment packaging, runtime startup, cPanel/CloudLinux integration, release identity, rollback or smoke must:

- update executable deployment contract coverage in the same PR;
- pass exact-head Bootstrap CI;
- pass Demo cPanel Package acceptance;
- pass relevant Web runtime acceptance;
- never weaken exact-SHA or route gates;
- enter `main` through a focused PR.

`GOV-DEPLOY-001` itself is enforced by repository contract tests. Removing its references from `AGENTS.md` / `PROJECT_CONTROL.md`, restoring wrapper-first `.cjs` startup, restoring nested CloudLinux startup paths, or removing root `server.js` runtime checks must make CI fail.

## 11. Prohibited recurring failure patterns

The following are explicitly prohibited:

- declaring deployment success from upload/build/restart status alone;
- treating a stale public runtime as a cache issue without origin evidence;
- changing release gates to match stale output;
- relying on a mutable release environment variable when the release can be artifact-owned;
- using `startup.cjs` as the canonical LiteSpeed Next startup;
- registering a nested startup path such as `apps/web/server.js` for the locked Demo Application Root;
- changing `WEB_APPLICATION_ROOT.txt` away from `.` without an explicit topology migration;
- blind repeated restart/stop/start loops without state reconciliation;
- assuming Passenger logs/processes exist because CloudLinux uses Passenger-compatible directives;
- leaving new files active after a failed runtime transition;
- restoring files without restoring mutated runtime registration;
- requiring routine manual cPanel intervention for every deployment.

## 12. Change control

Changing this constitution requires all of:

1. concrete hosting/runtime evidence;
2. owner authorization when topology/cutover authority changes;
3. focused PR;
4. updated executable contract tests;
5. exact-head CI;
6. no reduction in release identity, rollback or public acceptance guarantees.

The goal is repeatable deployment: the same governed input SHA must produce the same artifact contract, the same hosting desired state, and an objectively verifiable public runtime result.
