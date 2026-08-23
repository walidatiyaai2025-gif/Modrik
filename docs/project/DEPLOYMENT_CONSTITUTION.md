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
- cPanel account application root: `public_html/demo.modrik.org`.
- Node line: repository-locked Node `22.23.2` / compatible installed Node 22 runtime.
- Application mode: `production`.
- Application URL/domain: `demo.modrik.org`.
- Web product: Next.js standalone server runtime; **not** a static export.
- Canonical startup: the generated Next standalone `server.js` inside the packaged standalone application path recorded by `web/WEB_APPLICATION_ROOT.txt`.
- `startup.cjs` may exist only as a compatibility/rollback bridge; it is not the canonical LiteSpeed startup for new successful deployments.
- Backend: Laravel under `api.demo.modrik.org`, preserving its live `.env` and `storage` boundary.

LiteSpeed officially supports CloudLinux Selector-generated mod_passenger configuration but uses its own implementation internally. Its documented Next.js path is to compile standalone and use the generated `server.js` as the startup script. Deployment logic must therefore reason about LiteSpeed/CloudLinux state, not assume Apache Passenger process/log behavior.

## 3. Immutable artifact contract

Every deployable Web artifact must contain all information required to identify and start itself without mutable per-release cPanel environment edits.

Required Web payload invariants:

- `RELEASE_SHA.txt` at Web payload root;
- `WEB_APPLICATION_ROOT.txt` at Web payload root;
- `RELEASE_SHA.txt` beside the canonical standalone `server.js`;
- package-level and Web-level release SHA values are byte-identical;
- canonical standalone `server.js` injects the packaged release identity before Next runtime loads;
- `.next/static` and `public` assets are co-located as required by Next standalone runtime;
- no live `.env`, credential, token or production data enters the artifact.

A package that cannot boot its **direct canonical standalone `server.js`** in CI is undeployable.

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
- Startup-file drift may be reconciled only to the artifact-derived canonical standalone `server.js`, followed by read-back verification.
- Selector `success` output is not enough; the generated/observable runtime configuration must match the requested value.
- Do not directly hand-edit Selector-managed `.htaccess` as a normal deployment mechanism.
- Do not repeat unbounded restart loops.

## 5. Exact-Node preflight is mandatory

After the live Web payload is copied but before public activation, deployment must launch the exact live canonical standalone `server.js` using the same Node binary selected by the hosting runtime, on a bounded loopback-only port.

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

1. normalize the hosting restart marker permissions required by LiteSpeed/Selector;
2. request one canonical CloudLinux restart through the documented CageFS end-user path;
3. probe the cPanel origin for exact release identity;
4. if needed, perform one bounded `stop -> start` recycle;
5. probe again;
6. if exact identity still does not converge, fail closed and rollback.

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

If exact-Node preflight passes, desired state matches, Selector activation returns success, but no LiteSpeed Node runtime can be spawned, classify the event as a hosting-runtime blocker rather than modifying application code blindly.

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

`GOV-DEPLOY-001` itself is enforced by repository contract tests. Removing its references from `AGENTS.md` / `PROJECT_CONTROL.md`, restoring wrapper-first startup, or removing direct standalone runtime checks must make CI fail.

## 11. Prohibited recurring failure patterns

The following are explicitly prohibited:

- declaring deployment success from upload/build/restart status alone;
- treating a stale public runtime as a cache issue without origin evidence;
- changing release gates to match stale output;
- relying on a mutable release environment variable when the release can be artifact-owned;
- using `startup.cjs` as the canonical LiteSpeed Next startup after the direct standalone contract is available;
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
