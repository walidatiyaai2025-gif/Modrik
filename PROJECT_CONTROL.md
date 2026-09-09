# MODRIK Project Control Plane

Updated: 2026-09-09
Last reconciled baseline: `9f51632c8b27be75603fcdd0d478394be7cb49bb`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve or provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict resolution and release preparation proceed autonomously where tooling allows. Red CI is merge-blocking. Clients and Admin surfaces consume Backend/domain authority and must not redefine Auth, Academic, Assessment, Sync, Content, Safety, notification or publication policy merely to expose UI.

External or owner-only dependencies must be recorded explicitly as `OWNER_LAST`, `DEFERRED_EXTERNAL`, `DECISION REQUIRED` or BLOCKED according to the governing evidence. Those classifications are never equivalent to PASS and never block unrelated cloud-actionable work.

## Capability governance — `GOV-SURFACE-001`

Every capability remains exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

PR #234 / Issue #233 integrated an executable capability-surface validator into `contracts:check`. PR #239 records the locked Windows launch exclusion as `client.windows: deferred_disabled`. PR #236 / Issue #235 records `student.notifications.center` as `user_facing` / `present` after Backend/Web/Mobile implementation and exact-head acceptance.

PR #279 / Issue #277 reconciled runtime operational status with that accepted Notification Center capability: the first-party Notification Center reports `present` independently of auxiliary Firebase/FCM transport readiness. FCM remains separately `disabled` or `enabled_pending_transport`; no external push success is fabricated.

The current matrix has no remaining `audit_required` capability row. Unsupported mutation/activation gaps remain explicit as `backend_contract_missing`, `not_implemented_or_activated`, `p1_activation_gated`, `activation_gated` or another truthful deferred status; those labels do not grant implementation authority by themselves.

## Deployment governance — `GOV-DEPLOY-001`

`docs/project/DEPLOYMENT_CONSTITUTION.md` is locked engineering governance for Demo deployment and for any future cPanel/LiteSpeed/CloudLinux deployment path derived from it.

The deployment control plane is desired-state based, not restart-command based:

- exact canonical `main` SHA is the immutable release identity;
- package/runtime identity is artifact-owned;
- Demo Web desired state remains `demo.modrik.org` + `public_html/demo.modrik.org` + Node 22.23.2-compatible CloudLinux runtime + production mode;
- canonical LiteSpeed Next startup is the packaged standalone `server.js` identified by `web/WEB_APPLICATION_ROOT.txt`; `startup.cjs` is compatibility/rollback only;
- actual CloudLinux application root/domain/version/mode/startup/status must be read and reconciled/validated before activation;
- root/domain/version drift is fail-closed unless the active Issue explicitly owns a hosting migration;
- startup drift may be reconciled only to the artifact-derived standalone `server.js`, with read-back proof;
- copied live payload must pass exact-Node loopback preflight before hosting activation;
- origin exact-SHA convergence and public Landing/Student/Admin/API smoke remain mandatory;
- rollback must restore both Web payload and any mutated runtime registration such as startup-file;
- upload, package, Selector `success`, restart return code or manual cPanel restart never constitute deployment success by themselves;
- routine deployment must not depend on manual cPanel intervention;
- LiteSpeed runtime diagnostics use LiteSpeed evidence (`stderr.log`, `lsnode` where observable) and must not assume Apache Passenger process/log behavior.

Any deployment PR must update executable contract coverage and preserve these invariants. CI must fail if the repository silently returns to wrapper-first startup or weakens exact-release acceptance.

## Reconciled integration checkpoint

The previous runtime-auth and academic-selection work is no longer active work:

- #259 / #271 / #261 / #263 are integrated/closed through the terminal composed PR #313. Historical component PRs that were superseded by that composed candidate must not be reopened as duplicate implementation.
- #305 / PR #306 integrated Backend-owned year-scoped learner self-selection; Student Web chooses school year → track while reset/archive history semantics remain Backend-owned.
- #307 and #308 are integrated through the composed PR #313 stack, covering Backend/Admin track availability lifecycle and Mobile Year → Track parity.
- #310 / PR #341 integrated canonical localized academic-year metadata and operator-curated track ordering at `119a1821aa237ba5194e9d7529915700db27c02c`.
- #342 / PR #343 restored fail-closed Bootstrap CI after npm security advisories at `ddfc611f1cb6801c24cf1cfaec8dbcc2352a7481` without weakening audit policy.
- #309 / PR #344 retired the superseded physical `academic_track_authorizations` persistence at implementation merge `38660e6bc11b4deb422c667a4af27021b6cb7833`; Issue #309 is CLOSED / COMPLETED. PR #345 then reconciled `CURRENT_STATE.md`, `TASKS.md` and the dedicated closure evidence into baseline `9f51632c8b27be75603fcdd0d478394be7cb49bb`.
- #318 installer/update engineering is already integrated: deterministic unified package, Setup Wizard, transactional install/update engine, Dashboard Update Center, rollback/failure safety and fail-closed hosting handoff. Its remaining scope is live-hosting acceptance, not replacement implementation.

The detailed history remains in `CURRENT_STATE.md`, `TASKS.md`, `CHANGELOG.md`, merged PRs and Issue timelines. Historical failed CI/deployment runs remain evidence and are not rewritten as successful merely because later repairs passed.

## Active repository-verifiable work at this checkpoint

At this checkpoint there are no open pull requests. The open Issue queue is intentionally narrow:

### #260 — `DEFERRED_EXTERNAL` / `OWNER_LAST` host remediation and Demo acceptance

Repository/user-space deployment implementation is integrated. The last source-backed host evidence records LiteSpeed Web Server `6.3.6` Build `6`, while the connected cPanel user cannot perform the required root remediation (`sudo_available=false`; LSWS root not writable). Repository/user-space Node 22, Next standalone, CloudLinux Selector/CageFS, permissions, rollback and exact-release gates must not be bypassed or weakened.

Required owner/provider action:

1. use root/WHM or the hosting provider to update/force-update LiteSpeed to a supported build containing the Node process-management fix; the current Issue evidence requires `BUILD >= 8` for the 6.3.6 line, or a newer supported fixed build;
2. verify LiteSpeed `VERSION`/`BUILD` and healthy restart at the host level;
3. verify the protected cPanel origin/diagnostic is consistently available and no longer returning the recorded intermittent 503 condition;
4. only then authorize a fresh governed Demo deployment from the then-current exact `main`;
5. require protected deployment success plus exact API, Landing `/`, Student `/student`, Admin and release-identity external smoke before recording a newer deployed SHA or closing #260.

This evidence is not PASS until those external actions and the fresh governed deployment actually succeed.

### #318 — `DEFERRED_EXTERNAL` live-hosting slices coupled to #260

The installer/update engineering slices remain integrated. Slices E/F remain open only for live-hosting acceptance coupled to #260. No replacement installer/update branch is authorized.

After #260 host remediation and fresh governed deployment evidence exist, #318 may be reconciled only if the installer/update hosting bridge activates the exact authorized release without routine manual cPanel restart, protected API/Landing/Student/Admin/static health and exact release identity are green, and failure/rollback/shared-persistent-state invariants remain intact. Until then #318 remains OPEN and live-hosting completion must not be fabricated.

### Other owner/external gates

Real-content evaluation remains gated by owner-approved academic scope and evidence-backed content rights. Production activation remains separately gated by final owner/security/legal/provider inputs. Missing inputs block only their affected tasks and do not authorize guessed values.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green.

The governed matrix includes control-state validation, capability-surface validation, repository contracts/REQ/AC/schemas, OpenAPI lint, design tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, Pilot normal/strict acceptance and relevant browser/runtime/demo acceptance.

Release/deployment changes must preserve exact Web/Admin Build SHA verification from PR #232, Landing/Student route/runtime acceptance from PR #248, the pre-success remote marker/release validation integrated by PR #252, and `GOV-DEPLOY-001` desired-state/runtime rollback guarantees.

PR #344 exact head `2c43629b061dc9fb619d879547cad73e8165b330` passed Bootstrap #1382, Unified Release Package #103 and Demo cPanel Package #467 before integration. Its implementation merge `38660e6bc11b4deb422c667a4af27021b6cb7833` then passed push-triggered Bootstrap #1383, including normal/strict Pilot and the final governed aggregate, plus Unified Release Package #104.

PR #345 exact head `47fe6116253a3f837ef531ec33a4cbde8f9b2809` passed Bootstrap #1387 including control-state validation and the complete governed aggregate before integration. Fresh exact-main workflow state after later reconciliation merges must always be read from GitHub and is not predicted by this checkpoint.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with the established cPanel boundary.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully completed package assembly, audit retention, FTPS upload, protected one-shot bridge execution, cleanup and external Demo smoke. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Source-control integration has advanced beyond that deployed SHA. Source integration, package success and manual restart evidence do not by themselves mean the Demo serves the newest commit. The next authorized deployment must resolve its own immutable canonical-main SHA and pass API health, exact Web/Admin build identity, public Landing `/` identity and Student Portal `/student` identity before deployment-success markers are recorded.

Demo authorization does not imply production `modrik.org` cutover or Production Ready status.

## External inputs still explicit

These remain owner/external gates and do not block unrelated engineering:

- `OWNER_LAST` — exact owner-approved curriculum/academic scope values where not already supplied through an authorized workflow;
- `OWNER_LAST` / `DEFERRED_EXTERNAL` — evidence-backed content-rights approval for official publication;
- `OWNER_LAST` — final legal entity/controller/contact/jurisdiction facts and approved wording;
- `OWNER_LAST` / `DEFERRED_EXTERNAL` — production provider/Firebase/store identifiers, credentials, callbacks and signing where enabled;
- `OWNER_LAST` — production age/ad/community activation policy;
- `OWNER_LAST` — RPO/RTO, backup retention and data-retention decisions;
- `OWNER_LAST` — production hosting and `modrik.org` cutover approval.

None of these classifications is PASS without the required evidence.

## Completion language

Domain implementation completion is recorded as:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

Only the Integration Captain may declare a Wave closed after integrated-main verification and repository closure evidence are complete. No capability or release task is complete until its required authority classification and exact-head regression evidence are present. `VERIFIED_FINAL_COMPLETE` must never be claimed without actual final evidence.
