# MODRIK Demo portals

The cPanel Demo opens on `https://demo.modrik.org/` and exposes two explicit evaluation entry points.

## Student portal

- URL: `https://demo.modrik.org/student`
- The landing-page Student and Sign in actions both lead here.
- This is the existing production-shaped Student Auth/Learning workspace.
- Fixture bearer credentials remain server-side and must never be exposed in browser-visible markup or public environment variables.

For Demo evaluation only, the existing synthetic learner can be given a normal email/password login by adding both values to the deployed Backend `.env`:

```text
MODRIK_DEMO_STUDENT_EMAIL=<demo learner email>
MODRIK_DEMO_STUDENT_PASSWORD=<unique strong password, 12-128 characters>
```

The learner bootstrap updates the existing fixture learner ID after `LearningSliceSeeder`; it does not create a second unrelated learner, so the synthetic academic context/content/progress links remain attached to the same account. The seeder is fail-closed outside `MODRIK_FIXTURE_MODE=true`, requires both values together, validates the email and password length, and rejects an email already owned by another account.

## System Admin portal

- URL: `https://api.demo.modrik.org/admin/login`
- The landing-page Admin action uses `MODRIK_ADMIN_PORTAL_URL`, which should be set in the cPanel Node application to the URL above.
- The existing Filament role gate remains authoritative: only `admin` and `content_team` accounts are admitted after authentication.

For Demo evaluation only, an Admin account can be bootstrapped by adding both values to the deployed Backend `.env`:

```text
MODRIK_DEMO_ADMIN_EMAIL=<demo admin email>
MODRIK_DEMO_ADMIN_PASSWORD=<unique strong password, minimum 12 characters>
```

Do not commit or share either live Demo password. After changing the Backend `.env`, run the PHP 8.4 CLI commands from the Backend root:

```text
php artisan optimize:clear
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The Demo Admin seeder is active only while `MODRIK_FIXTURE_MODE=true` through the normal `DatabaseSeeder` path. If neither Admin environment value is supplied, it is a no-op. If only one is supplied, or the password is shorter than 12 characters, it fails closed.

## Landing page

`https://demo.modrik.org/` is intentionally not an authenticated workspace. It is the public Demo landing surface with AR/EN/FR switching and links to the two portals above.
