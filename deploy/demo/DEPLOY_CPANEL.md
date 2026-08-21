# MODRIK Demo — cPanel deployment

Target: `https://demo.modrik.org`

Owner-confirmed Web document root: `/public_html/demo.modrik.org`  
Expected absolute account path: `/home/solscool/public_html/demo.modrik.org/`

This deployment is **evaluation only**. It does not replace `modrik.org`, does not alter root `.cpanel.yml`, and does not declare Production Ready.

## Required topology

The Student Web is a Next.js server application, not a static export. Its same-origin `/api/auth/*` and `/api/learning/*` BFF routes must execute on Node.js.

Recommended cPanel layout:

- `demo.modrik.org` → Next.js standalone Node application.
- `api.demo.modrik.org` → Laravel Backend/API/Admin with its document root ending in `/public`.
- Next environment `MODRIK_API_BASE_URL=https://api.demo.modrik.org`.

A different Backend origin is acceptable if cPanel already provides one and it is reachable over HTTPS. Do not attempt to replace the BFF with a static-only build.

## 1. Package contents

The CI artifact contains:

```text
demo-cpanel/
  DEPLOY.md
  RELEASE_SHA.txt
  WEB_APPLICATION_ROOT.txt
  web/
    startup.cjs
    .env.demo.example
    ...Next standalone traced runtime...
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

## 2. Web — `demo.modrik.org`

In cPanel File Manager:

1. Clear only the contents of the Demo document root if it contains a placeholder page. Do **not** touch `/public_html/modrik.org`.
2. Copy the contents of `demo-cpanel/web/` into:
   `/home/solscool/public_html/demo.modrik.org/`
3. Keep the whole standalone tree intact. Do not move only `server.js`; traced monorepo dependencies may live in ancestor directories inside the Web payload.

In **Setup Node.js App** (or the cPanel Node/Passenger equivalent):

- Node version: **22.x**, matching the repository Node 22 line where available.
- Application mode: `Production`.
- Application root: `public_html/demo.modrik.org`.
- Application URL: `demo.modrik.org`.
- Startup file: `startup.cjs`.

Set server-side environment variables from `web/.env.demo.example`:

```text
NODE_ENV=production
HOSTNAME=0.0.0.0
MODRIK_API_BASE_URL=https://api.demo.modrik.org
MODRIK_FIXTURE_MODE=true
MODRIK_FIXTURE_BEARER_TOKEN=<same long random Demo token used by Backend>
```

Let cPanel/Passenger provide its assigned port when it injects `PORT`; if the panel requires a value, use the value allocated by the Node application UI rather than exposing a public custom port.

Restart the Node application after every environment or Web-package change.

### If `Setup Node.js App` is missing

Stop. Uploading `.next` or HTML files alone will not produce the full MODRIK Demo because Auth/Learning BFF routes require a server process. The hosting account must expose a compatible Node application runtime before this package can be used as a functional Demo.

## 3. Backend — recommended `api.demo.modrik.org`

Create a cPanel subdomain/domain entry:

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

## 4. MariaDB

In cPanel **MySQL® Databases** / **Database Wizard**:

1. create one Demo database;
2. create one Demo database user with a strong unique password;
3. grant that user the required privileges on only the Demo database;
4. put the final cPanel-prefixed database name/user/password in the Backend `.env`.

Do not commit these values to GitHub or copy them into issue comments.

## 5. Backend environment

Copy:

```text
backend/.env.demo.example -> backend/.env
```

Replace every `REPLACE_*` value. At minimum set:

- `APP_URL=https://api.demo.modrik.org`
- a generated `APP_KEY`;
- Demo MariaDB database/user/password;
- a long random `MODRIK_IDEMPOTENCY_SECRET`;
- a long random `MODRIK_AUTH_HASH_SECRET`;
- a long random `MODRIK_FIXTURE_BEARER_TOKEN` identical to the Web Node variable when fixture mode is enabled.

Keep:

```text
APP_ENV=production
APP_DEBUG=false
MODRIK_PAID_AI_ENABLED=false
```

Google/Apple provider values may remain blank; those adapters must fail closed until real owner configuration exists.

### Demo fixture mode

For the first visual/evaluation deployment, the package template intentionally allows synthetic fixture mode. This uses synthetic repository fixtures only and lets the learning workspace be exercised without pretending real curriculum or production identity exists.

Because a fixture bearer grants synthetic learner access, protect the Demo from uncontrolled public use while fixture mode is enabled (for example with cPanel access restriction/Directory Privacy or another host-level access control). Never reuse the Demo fixture token anywhere else.

## 6. Backend activation commands

Use the host's actual PHP 8.4 CLI binary. From:

```text
/home/solscool/public_html/api.demo.modrik.org
```

run:

```text
<PHP84_BIN> artisan optimize:clear
<PHP84_BIN> artisan migrate --force
<PHP84_BIN> artisan db:seed --force
<PHP84_BIN> artisan config:cache
<PHP84_BIN> artisan route:cache
<PHP84_BIN> artisan view:cache
```

`db:seed --force` is appropriate only for this synthetic Demo setup. Do not interpret fixture seed data as production curriculum.

If `APP_KEY` has not yet been generated, run the host PHP 8.4 binary with:

```text
<PHP84_BIN> artisan key:generate --force
```

before caching configuration.

## 7. Queue and scheduler

Use cPanel Cron Jobs with the host's exact PHP 8.4 CLI binary and absolute Backend path. The established P0 model uses the database queue; Redis/permanent daemons are not required.

```text
* * * * * cd /home/solscool/public_html/api.demo.modrik.org && <PHP84_BIN> artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/solscool/public_html/api.demo.modrik.org && <PHP84_BIN> artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Do not guess the PHP binary path. Use the PHP 8.4 CLI command shown by cPanel or hosting support.

## 8. SSL

Run/verify cPanel AutoSSL for both:

- `demo.modrik.org`
- the selected Backend host (recommended `api.demo.modrik.org`).

Do not switch the Web BFF to an HTTP Backend origin on an HTTPS Demo.

## 9. Post-deploy smoke

Backend first:

- `https://api.demo.modrik.org/health` returns the expected health response;
- migrations complete without error;
- no Laravel debug page is exposed;
- Admin boundary loads and remains authorization-protected.

Then Web:

- `https://demo.modrik.org` loads without 502/503;
- public routes/assets/logo/fonts load;
- Auth BFF returns controlled responses rather than `AUTH_SERVICE_UNAVAILABLE`;
- Learning BFF reaches the Backend;
- academic context → lesson/study → practice/attempt → progress can be exercised using the selected Demo identity/fixture mode;
- AR/RTL, EN/LTR and FR/LTR render correctly;
- narrow 320/360px and 200% text do not reintroduce critical horizontal clipping;
- offline/retry/session-expiry states remain reachable and controlled.

## 10. Rollback

Keep the previous Demo ZIP/release folder. To roll back:

1. restore the previous Web payload and restart the Node app;
2. restore the previous Backend code package;
3. clear/cache Laravel configuration again;
4. do not reverse database migrations unless that exact migration rollback has been proven safe; prefer forward repair.

The main-domain Coming Soon deployment is separate and must not be modified during Demo rollback.
