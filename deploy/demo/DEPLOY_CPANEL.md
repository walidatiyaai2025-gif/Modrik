# MODRIK Demo — cPanel deployment

Target: `https://demo.modrik.org`

Owner-confirmed Web document root: `/public_html/demo.modrik.org`  
Expected absolute account path: `/home/solscool/public_html/demo.modrik.org/`

This deployment is **evaluation only**. It does not replace `modrik.org`, does not alter root `.cpanel.yml`, and does not declare Production Ready.

`docs/project/DEPLOYMENT_CONSTITUTION.md` (`GOV-DEPLOY-001`) is authoritative for deployment invariants. This file is the operational cPanel runbook and must not weaken that constitution.

## Required topology

The Student Web is a Next.js server application, not a static export. Its same-origin `/api/auth/*` and `/api/learning/*` BFF routes must execute on Node.js.

Locked Demo layout:

- `demo.modrik.org` → Next.js standalone Node application through CloudLinux Node Selector + LiteSpeed.
- `api.demo.modrik.org` → Laravel Backend/API/Admin with its document root ending in `/public`.
- Next environment `MODRIK_API_BASE_URL=https://api.demo.modrik.org`.

A different Backend origin requires an explicitly authorized hosting change. Do not attempt to replace the BFF with a static-only build.

## 1. Package contents

The CI artifact contains:

```text
demo-cpanel/
  DEPLOY.md
  RELEASE_SHA.txt
  web/
    RELEASE_SHA.txt
    WEB_APPLICATION_ROOT.txt
    startup.cjs                 # compatibility/rollback only
    .env.demo.example
    ...Next standalone traced runtime...
    <WEB_APPLICATION_ROOT>/
      server.js                 # canonical LiteSpeed startup
      RELEASE_SHA.txt
      .next/
      public/
  backend/
    .env.demo.example
    artisan
    app/
    bootstrap/
    config/
    database/
    public/
    routes/
    storage/
    vendor/
    ...
```

There is intentionally no live `.env` and no production secret in the ZIP.

The three release identities (`demo-cpanel/RELEASE_SHA.txt`, `web/RELEASE_SHA.txt`, and the release file beside the canonical standalone `server.js`) must be identical.

## 2. Web — `demo.modrik.org`

In cPanel File Manager:

1. Clear only the contents of the Demo document root if it contains a placeholder page. Do **not** touch `/public_html/modrik.org`.
2. Copy the contents of `demo-cpanel/web/` into:
   `/home/solscool/public_html/demo.modrik.org/`
3. Keep the whole standalone tree intact. Do not move only `server.js`; traced monorepo dependencies may live in ancestor directories inside the Web payload.
4. Read `WEB_APPLICATION_ROOT.txt`. If it contains `apps/web`, the canonical startup file is `apps/web/server.js`. If it contains `.`, the canonical startup is `server.js`.

In **Setup Node.js App** / CloudLinux Node Selector:

- Node version: **22.x**, matching repository Node `22.23.2` where installed.
- Application mode: `Production`.
- Application root: `public_html/demo.modrik.org`.
- Application URL: `demo.modrik.org`.
- Startup file: **`<WEB_APPLICATION_ROOT>/server.js`** from the packaged metadata.

`startup.cjs` is retained only so a failed deployment can restore an older registration safely. It is not the canonical LiteSpeed startup for a new release.

This direct `server.js` requirement follows LiteSpeed's CloudLinux Node Selector guidance for Next.js standalone deployments. LiteSpeed consumes Passenger-compatible directives but implements the Node runtime differently behind the scenes; do not diagnose it as Apache Passenger merely because `.htaccess` contains `Passenger*` directives.

Set stable server-side environment variables from `web/.env.demo.example` as applicable:

```text
NODE_ENV=production
MODRIK_API_BASE_URL=https://api.demo.modrik.org
```

Do not add a per-release SHA environment variable. The release identity is packaged into the immutable artifact and injected by the canonical standalone `server.js` bootstrap.

Let LiteSpeed/CloudLinux provide the socket/port runtime integration. The Next standalone server must not depend on a manually exposed public custom port.

Routine deployments must use the governed automation to reconcile startup state and restart. Manual cPanel **RESTART** is emergency diagnostic/recovery only, not normal deployment acceptance.

### If `Setup Node.js App` is missing

Stop. Uploading `.next` or HTML files alone will not produce the full MODRIK Demo because Auth/Learning BFF routes require a server process. The hosting account must expose a compatible Node application runtime before this package can be used as a functional Demo.

## 3. Runtime desired-state verification

Before activation, the deployment automation must verify the existing CloudLinux application instead of blindly restarting it.

Expected values:

```text
application root: public_html/demo.modrik.org
domain/url:       demo.modrik.org
mode:             production
node:             Node 22 compatible with repository 22.23.2
startup file:     <WEB_APPLICATION_ROOT>/server.js
status:           started (after activation)
```

A missing/ambiguous application, wrong root, wrong domain, or wrong Node line is a hard failure unless the active Issue explicitly owns a hosting migration. Do not implicitly destroy/recreate the application.

The startup file is the only normal deploy-time runtime-registration reconciliation: set it to the artifact-derived standalone `server.js`, then read the generated Selector/`.htaccess` state back and prove it changed before restarting.

## 4. Exact-Node live-payload preflight

After the new Web payload is copied and before public activation, the deploy runner launches the actual copied `<WEB_APPLICATION_ROOT>/server.js` using the exact Node binary configured for the cPanel application, on a bounded `127.0.0.1` port.

The preflight must prove:

- Landing marker renders;
- exact full release SHA renders;
- short Build SHA renders;
- the global error boundary is absent;
- the temporary process remains reachable.

The temporary process is always terminated. If this preflight fails, the public hosting runtime is not touched further and the previous Web payload is restored.

## 5. Backend — `api.demo.modrik.org`

Create/retain the cPanel subdomain/domain entry:

- hostname: `api.demo.modrik.org`
- application folder: `/home/solscool/public_html/api.demo.modrik.org/`
- **document root:** `/home/solscool/public_html/api.demo.modrik.org/public/`

Extract the contents of `demo-cpanel/backend/` into the application folder so that these paths exist:

```text
/home/solscool/public_html/api.demo.modrik.org/artisan
/home/solscool/public_html/api.demo.modrik.org/vendor/autoload.php
/home/solscool/public_html/api.demo.modrik.org/public/index.php
```

The public web root must be the Laravel `public/` directory, not the Backend project root.

Select PHP **8.4** for `api.demo.modrik.org` in MultiPHP Manager (or the host's equivalent).

Ensure these are writable by the cPanel account/runtime:

```text
storage/
bootstrap/cache/
```

## 6. MariaDB

In cPanel **MySQL® Databases** / **Database Wizard**:

1. create one Demo database;
2. create one Demo database user with a strong unique password;
3. grant that user the required privileges on only the Demo database;
4. put the final cPanel-prefixed database name/user/password in the Backend `.env`.

Do not commit these values to GitHub or copy them into issue comments.

## 7. Backend environment

Copy:

```text
backend/.env.demo.example -> backend/.env
```

Replace every `REPLACE_*` value. At minimum set:

- `APP_URL=https://api.demo.modrik.org`
- a generated `APP_KEY`;
- Demo MariaDB database/user/password;
- required internal security secrets that the current Backend template declares.

Keep:

```text
APP_ENV=production
APP_DEBUG=false
MODRIK_PAID_AI_ENABLED=false
```

External provider values may remain blank where current contracts explicitly permit disabled transport; those adapters must fail closed until real owner configuration exists.

## 8. Backend activation commands

Use the host's actual PHP 8.4 CLI binary. From:

```text
/home/solscool/public_html/api.demo.modrik.org
```

run through the governed deploy path:

```text
<PHP84_BIN> artisan migrate --force
<PHP84_BIN> artisan optimize:clear
<PHP84_BIN> artisan config:cache
<PHP84_BIN> artisan route:cache
<PHP84_BIN> artisan view:cache
```

Do not add ad-hoc seed/reset commands to routine deployment unless the current Demo data contract explicitly requires them.

## 9. Queue and scheduler

Use cPanel Cron Jobs with the host's exact PHP 8.4 CLI binary and absolute Backend path where the current release still requires scheduled/queued work.

Do not guess the PHP binary path. Use the PHP 8.4 CLI command shown by cPanel or hosting support.

## 10. Activation/restart sequence

The governed runner performs exactly this bounded sequence:

1. backup current Web payload and current startup-file registration;
2. copy new Web payload;
3. update Backend while preserving `.env` and `storage`;
4. run Laravel migration/cache work;
5. pass direct standalone exact-Node preflight;
6. reconcile CloudLinux startup to `<WEB_APPLICATION_ROOT>/server.js` and read it back;
7. normalize `<app-root>/tmp/restart.txt` permissions;
8. request one CageFS-backed CloudLinux restart;
9. verify direct cPanel origin exact SHA;
10. if needed, perform one bounded stop/start recycle and verify again;
11. run public API/Landing/Student/Admin smoke;
12. only then write deployment-success markers.

Do not add repeated/unbounded restart attempts.

## 11. LiteSpeed diagnostics

When exact-Node preflight passes but public activation fails:

- inspect application-root `stderr.log` when present;
- inspect safe LiteSpeed Node runtime evidence (`lsnode`) without exposing environment variables or arbitrary request data;
- treat custom Passenger log output as compatibility evidence only, not the sole source of truth;
- redact tokens, cookies, secrets, passwords and authorization values from any emitted diagnostic.

If exact-Node preflight passes, runtime desired state matches, Selector restart reports success, and LiteSpeed still cannot spawn a serving runtime, classify it as a hosting-runtime blocker rather than changing application code blindly.

## 12. SSL

Verify cPanel AutoSSL for both:

- `demo.modrik.org`
- `api.demo.modrik.org`.

Do not switch the Web BFF to an HTTP Backend origin on an HTTPS Demo.

## 13. Post-deploy smoke

Backend/API:

- `https://api.demo.modrik.org/up` returns the expected health response;
- migrations complete without error;
- no Laravel debug page is exposed;
- Admin boundary loads and remains authorization-protected;
- Admin build/release identity matches the requested release where the governed workflow requires it.

Web:

- `https://demo.modrik.org/` returns the exact requested release identity;
- `https://demo.modrik.org/student` returns the same exact release identity;
- Landing and Student route markers are correct;
- no global `This screen could not be completed.` boundary is rendered;
- static CSS/assets load;
- required runtime/BFF acceptance remains controlled.

Origin verification and external public verification are both required. Public success may not be inferred from files on disk.

## 14. Rollback

Rollback is transactional across code **and runtime registration**.

On any post-Web-mutation failure:

1. restore the previous Web payload;
2. restore the previous CloudLinux startup-file registration;
3. normalize the restart marker;
4. request restart of the restored application;
5. leave deployment-success markers unchanged;
6. do not reverse database migrations automatically unless that exact rollback has been proven safe; prefer forward repair.

The main-domain Coming Soon deployment is separate and must not be modified during Demo rollback.
