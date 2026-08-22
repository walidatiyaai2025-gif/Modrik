# Demo Deployment Checkpoint — 2026-08-22

## Result

The authorized MODRIK evaluation deployment to `demo.modrik.org` completed successfully on GitHub Actions workflow run `32563427725`, attempt 2.

The deployment checked out canonical `main` and resolved the immutable release SHA:

`c82604443c5d6b3100e8df03f8fb37f089fc2853`

This is the deployed Demo source checkpoint for this run.

## Successful gates

The deployment job completed all required stages successfully:

- deployment secrets presence validation;
- canonical `main` checkout;
- immutable release SHA resolution;
- Student Web dependency install and production build;
- Backend production Composer install;
- verified cPanel package assembly;
- deployment package retention for audit;
- FTPS client installation;
- protected one-shot deployment bridge preparation;
- package and bridge upload over the configured FTPS path;
- protected deployment bridge execution;
- one-shot deployment file cleanup;
- external post-deploy smoke for `https://api.demo.modrik.org/up` and `https://demo.modrik.org/`.

## Previous blocker closed

Attempt 1 of the same authorized workflow run failed before FTPS upload because the Backend Admin Vite build was absent from the package boundary.

PR #196 fixed that defect by making `scripts/package-demo-cpanel.sh` deterministically build and verify the Backend Admin assets when the manifest is missing. Attempt 2 passed the package assembly gate and the complete remote deployment path.

## Boundaries

This checkpoint records Demo/evaluation deployment evidence only.

It does not authorize or imply:

- production `modrik.org` cutover;
- production credentials or signing completion;
- final legal approval;
- real curriculum/content-rights approval;
- production age/ad/community policy approval;
- Production Ready status.

The Demo remains limited to fixture/synthetic or separately owner-approved evaluation content under existing repository gates.
